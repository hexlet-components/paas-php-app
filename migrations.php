<?php

declare(strict_types=1);

// Настройки Doctrine Migrations. Команда vendor/bin/doctrine-migrations читает этот файл и migrations-db.php.
return [
    'migrations_paths' => [
        'App\Migrations' => __DIR__ . '/migrations',
    ],
];
