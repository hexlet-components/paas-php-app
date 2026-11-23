<?php

declare(strict_types=1);

namespace Tests;

use App\Database;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;

/**
 * Тесты с базой идут, когда задан TEST_DATABASE_URL, например postgresql://postgres@localhost:5432/paas_test.
 * База очищается.
 */
final class DatabaseTest extends TestCase
{
    private string $url;

    private Connection $connection;

    protected function setUp(): void
    {
        $url = getenv('TEST_DATABASE_URL');
        if (!is_string($url) || $url === '') {
            self::markTestSkipped('TEST_DATABASE_URL не задан');
        }

        $this->url = $url;
        $this->connection = Database::connect($url);
        $this->connection->executeStatement('DROP TABLE IF EXISTS page_visits, doctrine_migration_versions');
    }

    #[Test]
    public function concurrentMigrationsApplyOnce(): void
    {
        // Экземпляры приложения это отдельные процессы, как на платформе.
        $processes = [];
        for ($i = 0; $i < 5; $i++) {
            $pipes = [];
            $process = proc_open(
                command: [PHP_BINARY, dirname(__DIR__) . '/bin/migrate.php'],
                descriptor_spec: [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                pipes: $pipes,
                env_vars: array_merge(getenv(), ['DATABASE_URL' => $this->url]),
            );
            if ($process === false) {
                throw new RuntimeException('Не удалось запустить bin/migrate.php');
            }

            $processes[] = $process;
        }

        foreach ($processes as $process) {
            self::assertSame(0, proc_close($process));
        }

        $applied = $this->connection->fetchOne('SELECT count(*) FROM doctrine_migration_versions');
        self::assertSame(1, (int) $applied);
    }

    #[Test]
    public function homeCountsVisits(): void
    {
        self::assertSame(0, Database::migrate($this->connection, new NullOutput()));
        $app = ServerProcess::start(router: dirname(__DIR__) . '/app.php', env: [
            'DATABASE_URL' => $this->url,
            'SERVER_MESSAGE' => 'Первая публикация',
        ]);
        try {
            foreach ([1, 2] as $visit) {
                $body = (string) file_get_contents($app->url('/'));
                self::assertSame(
                    "<h1>Привет от Хекслета!</h1><p>Первая публикация</p><p>Посещений: {$visit}</p>\n",
                    $body,
                );
            }
        } finally {
            $app->stop();
        }
    }
}
