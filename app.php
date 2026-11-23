<?php

declare(strict_types=1);

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Factory\AppFactory;

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
 * Ответ с телом и типом содержимого; к телу добавляется перевод строки.
 */
function reply(ResponseInterface $response, int $status, string $contentType, string $body): ResponseInterface
{
    $response->getBody()->write($body . "\n");

    return $response->withStatus($status)->withHeader('Content-Type', $contentType);
}

// Без SENTRY_DSN SDK ничего не отправляет.
$dsn = env(name: 'SENTRY_DSN', fallback: '');
\Sentry\init(['dsn' => $dsn === '' ? null : $dsn]);

// Без DATABASE_URL базы нет, и главная страница выходит без счётчика посещений. Миграции применяет bin/migrate.php
// в команде старта, до запуска сервера.
$databaseUrl = env(name: 'DATABASE_URL', fallback: '');
$database = $databaseUrl === '' ? null : Database::connect($databaseUrl);

$app = AppFactory::create();
$app->addRoutingMiddleware();

$app->get('/', static function (ServerRequestInterface $request, ResponseInterface $response) use (
    $database,
): ResponseInterface {
    $message = htmlspecialchars(
        string: env(name: 'SERVER_MESSAGE', fallback: 'Hello, PaaS!'),
        flags: ENT_QUOTES | ENT_SUBSTITUTE,
        encoding: 'UTF-8',
    );
    // Сбой базы уходит в общий обработчик ошибок: событие в Sentry и ответ 500.
    $visits = $database === null ? '' : '<p>Посещений: ' . Database::countVisit($database) . '</p>';

    return reply(
        response: $response,
        status: 200,
        contentType: 'text/html; charset=utf-8',
        body: '<h1>' . HEADING . '</h1><p>' . $message . '</p>' . $visits,
    );
});

$app->get('/error', static function (): never {
    throw new RuntimeException('Oops! Something went wrong!');
});

// Ошибки отдаются простым текстом: неизвестный путь даёт 404, остальные исключения уходят в Sentry и дают 500.
$errorMiddleware = $app->addErrorMiddleware(displayErrorDetails: false, logErrors: false, logErrorDetails: false);
$errorMiddleware->setDefaultErrorHandler(static function (ServerRequestInterface $request, Throwable $error) use (
    $app,
): ResponseInterface {
    $response = $app->getResponseFactory()->createResponse();
    if ($error instanceof HttpException) {
        return reply(response: $response, status: 404, contentType: 'text/plain; charset=utf-8', body: 'not found');
    }

    \Sentry\captureException($error);
    // Процесс PHP живёт один запрос, поэтому отправка идёт до ответа.
    \Sentry\flush();

    return reply(
        response: $response,
        status: 500,
        contentType: 'text/plain; charset=utf-8',
        body: 'Oops! Something went wrong!',
    );
});

// Добавлен последним, поэтому стоит снаружи и видит итоговый статус, в том числе ошибок.
$app->add(static function (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $response = $handler->handle($request);
    error_log(message: sprintf(
        '%s %s %d',
        $request->getMethod(),
        $request->getUri()->getPath(),
        $response->getStatusCode(),
    ));

    return $response;
});

$app->run();
