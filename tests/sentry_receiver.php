<?php

declare(strict_types=1);

// Приёмник вместо Sentry: каждый POST записывается в файл из RECEIVER_LOG строкой
// «метод путь», за которой идёт тело запроса.
$body = (string) file_get_contents('php://input');
if (($_SERVER['HTTP_CONTENT_ENCODING'] ?? '') === 'gzip') {
    $body = (string) gzdecode($body);
}

$request = $_SERVER['REQUEST_METHOD'] . ' ' . $_SERVER['REQUEST_URI'] . "\n" . $body . "\n";
file_put_contents((string) getenv('RECEIVER_LOG'), $request, FILE_APPEND | LOCK_EX);
echo '{}';
