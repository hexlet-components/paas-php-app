<div align="center">

<a href="https://hexlet.io/?utm_source=github&utm_medium=link&utm_campaign=paas-php-app">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Hexlet/brand-assets/master/images/svg/hexlet_wordmark_white_rus.svg">
        <img src="https://raw.githubusercontent.com/Hexlet/brand-assets/master/images/svg/hexlet_wordmark_primary_rus.svg" alt="Хекслет" height="64">
    </picture>
</a>

# PHP-приложение для курса «Деплой на PaaS»

[![CI](https://github.com/hexlet-components/paas-php-app/actions/workflows/main.yml/badge.svg)](https://github.com/hexlet-components/paas-php-app/actions/workflows/main.yml)
[![Check](https://github.com/hexlet-components/paas-php-app/actions/workflows/check.yml/badge.svg)](https://github.com/hexlet-components/paas-php-app/actions/workflows/check.yml)

</div>

Компонент создаёт и поддерживает команда [Хекслета](https://hexlet.io/?utm_source=github&utm_medium=link&utm_campaign=paas-php-app), образовательного проекта.

Учебное приложение курса [«Деплой на PaaS»](https://ru.hexlet.io/courses/paas-deploy) для тех, кто проходит курс на PHP. Студент делает fork, публикует приложение на Render и учится на нём конфигурации, обновлениям и диагностике. Такие же приложения есть для других стеков курса, у всех одинаковое поведение по HTTP. Изменение маршрутов, ответов или формата лога ломает шаги курса, поэтому такие правки сначала сверяются с уроками.

## Поведение

| Запрос | Ответ |
| --- | --- |
| `GET /` | `200`, заголовок «Привет от Хекслета!», значение `SERVER_MESSAGE`, с `DATABASE_URL` ещё счётчик посещений |
| `GET /error` | `500`, событие уходит в Sentry, процесс продолжает работать |
| другой путь | `404` |

Сервер слушает `0.0.0.0` на порту из `PORT` (по умолчанию `3000`) и печатает строку `GET / 200` на каждый запрос (PHP пишет её в stderr, в контейнере она попадает в лог Apache). Sentry SDK включается переменной `SENTRY_DSN`, без неё события не отправляются. Приложение написано на микрофреймворке [Slim 4](https://www.slimframework.com/) с PSR-7 из `slim/psr7`, маршруты лежат в `app.php`, работа с базой в `src/Database.php`.

## Публикация на Render

| Поле | Значение |
| --- | --- |
| Language | Docker |
| Dockerfile Path | `./Dockerfile` |
| Docker Command | пусто, запуск идёт по `CMD` образа (миграции, затем `apache2-foreground`) |
| Environment | `PORT=3000`, `SERVER_MESSAGE`, по желанию `SENTRY_DSN` и `DATABASE_URL` |

Версию PHP задаёт строка `FROM php:8.5-apache` в `Dockerfile`. Зависимости Composer ставит отдельная стадия сборки.

## База данных

С переменной `DATABASE_URL` главная страница считает посещения в таблице `page_visits` и показывает строку «Посещений: N». Адрес имеет вид `postgresql://user:password@host:port/name`, порт необязателен. На Render это Internal Database URL базы из того же региона. Без переменной приложение работает без базы, и страница остаётся прежней. Посещением считается запрос `GET /`. Запрос `HEAD /`, которым платформа проверяет запуск, счётчик не увеличивает.

Миграции [Doctrine Migrations](https://www.doctrine-project.org/projects/migrations.html) лежат в `migrations`, настройки в `migrations.php`. Команда запуска сначала выполняет `php bin/migrate.php`, и только потом стартует сервер. Без `DATABASE_URL` скрипт ничего не делает. Doctrine Migrations сам базу не блокирует, поэтому `src/Database.php` берёт сессионную advisory-блокировку PostgreSQL на время миграций. Из двух экземпляров, запущенных разом, миграцию применяет один, а второй ждёт и видит её уже применённой. Для работы с базой PHP нужно расширение `pdo_pgsql`, в образ его ставит `Dockerfile`. Новая миграция создаётся командой `vendor/bin/doctrine-migrations generate`, адрес базы команда берёт из `DATABASE_URL`.

Тесты с базой идут, когда задан `TEST_DATABASE_URL`, и очищают эту базу. Без переменной они пропускаются. В CI базу даёт сервис PostgreSQL из `main.yml`.

## Запуск

```sh
mise run setup
mise run test
PORT=3000 SERVER_MESSAGE="Первая публикация" mise run start
```

Образ собирается и запускается так же, как на Render.

```sh
docker build -t paas-php-app .
docker run --rm -e PORT=3000 -e SERVER_MESSAGE="Первая публикация" -p 3000:3000 paas-php-app
```

`make build` ставит зависимости, как стадия сборки `Dockerfile`. `make start` запускает приложение встроенным сервером PHP, а в образе запросы обслуживает Apache. Эти две цели работают без mise, нужны только PHP 8.5 и Composer. Установка PHP описана в [инструкции Хекслета](https://github.com/Hexlet/ru-instructions/blob/main/php.md), Composer ставится по [официальной инструкции](https://getcomposer.org/download/).

`make <задача>` вызывает ту же задачу mise.

## Проверки

`mise run check` гоняет тот же набор, что CI: Mago (формат, линтер, статический анализ), OSV-Scanner, разметку, опечатки, ссылки, workflows и секреты.
