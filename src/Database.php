<?php

declare(strict_types=1);

namespace App;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\PhpFile;
use Doctrine\Migrations\DependencyFactory;
use Doctrine\Migrations\Tools\Console\ConsoleRunner;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Подключение к PostgreSQL по DATABASE_URL, миграции и счётчик посещений главной страницы.
 */
final class Database
{
    // Doctrine Migrations сам базу не блокирует. Сессионная advisory-блокировка с постоянным ключом не даёт двум
    // экземплярам приложения применять миграции одновременно: второй ждёт, пока первый закончит, и затем видит, что
    // новых миграций нет.
    private const int MIGRATION_LOCK_KEY = 7_200_202_611;

    // Один запрос и создаёт строку счётчика, и увеличивает её, поэтому одновременные запросы не теряют посещения.
    private const string COUNT_VISIT = <<<'SQL'
        INSERT INTO page_visits (path, total) VALUES ('/', 1)
        ON CONFLICT (path) DO UPDATE SET total = page_visits.total + 1
        RETURNING total
        SQL;

    /**
     * Адрес вида postgresql://user:password@host:port/name. Порт необязателен, Render его не указывает.
     */
    public static function connect(string $url): Connection
    {
        $parser = new DsnParser(['postgres' => 'pdo_pgsql', 'postgresql' => 'pdo_pgsql']);

        return DriverManager::getConnection($parser->parse($url));
    }

    public static function dependencyFactory(Connection $connection): DependencyFactory
    {
        return DependencyFactory::fromConnection(
            new PhpFile(dirname(__DIR__) . '/migrations.php'),
            new ExistingConnection($connection),
        );
    }

    /**
     * Применяет новые миграции из каталога migrations командой migrations:migrate и возвращает её код завершения.
     */
    public static function migrate(Connection $connection, ?OutputInterface $output = null): int
    {
        $connection->executeStatement('SELECT pg_advisory_lock(?)', [self::MIGRATION_LOCK_KEY]);
        try {
            $cli = new Application('Doctrine Migrations');
            $cli->setAutoExit(false);
            ConsoleRunner::addCommands($cli, self::dependencyFactory($connection));
            $input = new ArrayInput([
                'command' => 'migrations:migrate',
                '--no-interaction' => true,
                '--allow-no-migration' => true,
            ]);

            return $cli->run($input, $output);
        } finally {
            $connection->executeStatement('SELECT pg_advisory_unlock(?)', [self::MIGRATION_LOCK_KEY]);
        }
    }

    /**
     * Увеличивает счётчик главной страницы и возвращает новое значение.
     */
    public static function countVisit(Connection $connection): int
    {
        return (int) $connection->fetchOne(self::COUNT_VISIT);
    }
}
