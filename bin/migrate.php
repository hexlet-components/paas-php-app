<?php

declare(strict_types=1);

use App\Database;

require dirname(__DIR__) . '/vendor/autoload.php';

// Запускается перед сервером в команде старта. Без DATABASE_URL приложение работает без базы, и применять нечего.
$url = getenv('DATABASE_URL');
if (!is_string($url) || $url === '') {
    exit(0);
}

exit(Database::migrate(Database::connect($url)));
