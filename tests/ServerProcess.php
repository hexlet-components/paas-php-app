<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * Встроенный сервер PHP со своим файлом маршрутизации в отдельном процессе.
 */
final class ServerProcess
{
    private function __construct(
        /** @var resource */
        private $process,
        public readonly int $port,
        private readonly string $stderrFile,
    ) {}

    /**
     * @param array<string, string> $env
     */
    public static function start(string $router, array $env, string $host = '127.0.0.1'): self
    {
        $port = self::freePort();
        $stderrFile = tempnam(directory: sys_get_temp_dir(), prefix: 'paas-php-stderr');
        if ($stderrFile === false) {
            throw new RuntimeException('Не удалось создать файл журнала сервера');
        }

        $stdin = fopen('/dev/null', mode: 'r');
        $stdout = fopen('/dev/null', mode: 'w');
        $stderr = fopen($stderrFile, mode: 'w');
        if ($stdin === false || $stdout === false || $stderr === false) {
            throw new RuntimeException('Не удалось открыть потоки сервера');
        }

        $pipes = [];
        $process = proc_open(
            command: [PHP_BINARY, '-S', $host . ':' . $port, $router],
            descriptor_spec: [0 => $stdin, 1 => $stdout, 2 => $stderr],
            pipes: $pipes,
            env_vars: array_merge(getenv(), ['SENTRY_DSN' => '', 'SERVER_MESSAGE' => ''], $env),
        );
        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
        if ($process === false) {
            throw new RuntimeException('Не удалось запустить php -S');
        }

        $server = new self($process, $port, $stderrFile);
        $server->waitUntilStarted();

        return $server;
    }

    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);
        if (is_file($this->stderrFile)) {
            unlink($this->stderrFile);
        }
    }

    public function url(string $path): string
    {
        return 'http://127.0.0.1:' . $this->port . $path;
    }

    public function stderr(): string
    {
        return (string) file_get_contents($this->stderrFile);
    }

    private static function freePort(): int
    {
        $errorCode = 0;
        $errorMessage = '';
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
        if ($socket === false) {
            throw new RuntimeException((string) $errorMessage);
        }

        $name = (string) stream_socket_get_name($socket, remote: false);
        fclose($socket);

        return (int) substr($name, offset: (int) strrpos(haystack: $name, needle: ':') + 1);
    }

    /**
     * Сервер пишет в stderr строку «started», когда начинает принимать запросы.
     */
    private function waitUntilStarted(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (str_contains($this->stderr(), 'started')) {
                return;
            }
            usleep(microseconds: 50_000);
        }

        throw new RuntimeException('Сервер не начал слушать порт ' . $this->port);
    }
}
