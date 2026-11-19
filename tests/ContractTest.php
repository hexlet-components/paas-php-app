<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ContractTest extends TestCase
{
    private ServerProcess $app;

    private ServerProcess $sentry;

    private string $sentryLog;

    protected function setUp(): void
    {
        $sentryLog = tempnam(directory: sys_get_temp_dir(), prefix: 'paas-php-sentry');
        if ($sentryLog === false) {
            throw new RuntimeException('Не удалось создать файл приёмника');
        }

        $this->sentryLog = $sentryLog;
        $this->sentry = ServerProcess::start(__DIR__ . '/sentry_receiver.php', ['RECEIVER_LOG' => $sentryLog]);
        $this->app = ServerProcess::start(
            router: dirname(__DIR__) . '/app.php',
            env: [
                'SERVER_MESSAGE' => 'Первая <публикация>',
                'SENTRY_DSN' => 'http://public@127.0.0.1:' . $this->sentry->port . '/1',
            ],
            host: '0.0.0.0',
        );
    }

    protected function tearDown(): void
    {
        $this->app->stop();
        $this->sentry->stop();
        unlink($this->sentryLog);
    }

    #[Test]
    public function homeShowsHeadingAndEscapedMessage(): void
    {
        $response = self::get($this->app->url('/'));

        self::assertSame(200, $response['status']);
        self::assertSame('text/html; charset=utf-8', $response['contentType']);
        self::assertSame("<h1>Привет от Хекслета!</h1><p>Первая &lt;публикация&gt;</p>\n", $response['body']);
    }

    #[Test]
    public function homeFallsBackToDefaultMessage(): void
    {
        $app = ServerProcess::start(router: dirname(__DIR__) . '/app.php', env: []);
        try {
            $response = self::get($app->url('/'));
        } finally {
            $app->stop();
        }

        self::assertSame("<h1>Привет от Хекслета!</h1><p>Hello, PaaS!</p>\n", $response['body']);
    }

    #[Test]
    public function errorIsReportedToSentryAndServerKeepsWorking(): void
    {
        $error = self::get($this->app->url('/error'));

        self::assertSame(500, $error['status']);
        self::assertSame('text/plain; charset=utf-8', $error['contentType']);
        self::assertSame("Oops! Something went wrong!\n", $error['body']);

        $received = (string) file_get_contents($this->sentryLog);
        self::assertSame(1, preg_match_all('#^POST /api/1/(envelope|store)/#m', $received));
        self::assertStringContainsString('Oops! Something went wrong!', $received);

        self::assertSame(200, self::get($this->app->url('/'))['status']);
    }

    #[Test]
    public function unknownPathReturnsNotFound(): void
    {
        $response = self::get($this->app->url('/missing'));

        self::assertSame(404, $response['status']);
        self::assertSame('text/plain; charset=utf-8', $response['contentType']);
        self::assertSame("not found\n", $response['body']);
    }

    #[Test]
    public function everyRequestIsLogged(): void
    {
        self::get($this->app->url('/'));
        self::get($this->app->url('/missing'));

        // Строка пишется после ответа, поэтому ждём её короткое время.
        for ($attempt = 0; $attempt < 40 && !str_contains($this->app->stderr(), 'GET /missing 404'); $attempt++) {
            usleep(microseconds: 50_000);
        }

        self::assertStringContainsString('GET / 200', $this->app->stderr());
        self::assertStringContainsString('GET /missing 404', $this->app->stderr());
    }

    /**
     * @return array{status: int, contentType: string, body: string}
     */
    private static function get(string $url): array
    {
        $curl = curl_init($url);
        curl_setopt(handle: $curl, option: CURLOPT_RETURNTRANSFER, value: true);
        $body = curl_exec($curl);
        if (!is_string($body)) {
            throw new RuntimeException(curl_error($curl));
        }

        return [
            'status' => (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
            'contentType' => (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE),
            'body' => $body,
        ];
    }
}
