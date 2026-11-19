<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

const HEADING = 'Привет от Хекслета!';

/**
 * Значение переменной окружения; пустая и незаданная переменные дают запасное значение.
 */
function env(string $name, string $fallback): string
{
    $value = getenv($name);

    return is_string($value) && $value !== '' ? $value : $fallback;
}

/**
 * Ответ приложения по пути запроса: статус, тип содержимого и тело.
 *
 * @return array{int, string, string}
 */
function respond(string $path): array
{
    if ($path === '/') {
        $message = htmlspecialchars(
            string: env(name: 'SERVER_MESSAGE', fallback: 'Hello, PaaS!'),
            flags: ENT_QUOTES | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
        );

        return [200, 'text/html; charset=utf-8', '<h1>' . HEADING . '</h1><p>' . $message . '</p>'];
    }

    if ($path === '/error') {
        try {
            throw new RuntimeException('Oops! Something went wrong!');
        } catch (RuntimeException $error) {
            \Sentry\captureException($error);
            // Процесс PHP живёт один запрос, поэтому отправка идёт до ответа.
            \Sentry\flush();

            return [500, 'text/plain; charset=utf-8', $error->getMessage()];
        }
    }

    return [404, 'text/plain; charset=utf-8', 'not found'];
}

// Без SENTRY_DSN SDK ничего не отправляет.
$dsn = env(name: 'SENTRY_DSN', fallback: '');
\Sentry\init(['dsn' => $dsn === '' ? null : $dsn]);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$parsedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$path = is_string($parsedPath) ? $parsedPath : '/';
[$status, $contentType, $body] = respond(path: $path);

http_response_code(response_code: $status);
header(header: 'Content-Type: ' . $contentType);
echo $body . "\n";
error_log(message: sprintf('%s %s %d', $method, $path, $status));
