<?php

declare(strict_types=1);

use App\Database;

// Подключение для команды vendor/bin/doctrine-migrations в терминале, адрес берётся из DATABASE_URL.
$url = getenv('DATABASE_URL');
if (!is_string($url) || $url === '') {
    throw new RuntimeException('DATABASE_URL не задан');
}

return Database::connect($url);
