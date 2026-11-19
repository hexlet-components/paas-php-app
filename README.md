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
| `GET /` | `200`, заголовок «Привет от Хекслета!» и значение `SERVER_MESSAGE` |
| `GET /error` | `500`, событие уходит в Sentry, процесс продолжает работать |
| другой путь | `404` |

Сервер слушает `0.0.0.0` на порту из `PORT` (по умолчанию `3000`) и печатает строку `GET / 200` на каждый запрос (PHP пишет её в stderr, в контейнере она попадает в лог Apache). Sentry SDK включается переменной `SENTRY_DSN`, без неё события не отправляются.

## Публикация на Render

| Поле | Значение |
| --- | --- |
| Language | Docker |
| Dockerfile Path | `./Dockerfile` |
| Docker Command | пусто, запуск идёт по `CMD` образа (`apache2-foreground`) |
| Environment | `PORT=3000`, `SERVER_MESSAGE`, по желанию `SENTRY_DSN` |

Версию PHP задаёт строка `FROM php:8.5-apache` в `Dockerfile`. Зависимости Composer ставит отдельная стадия сборки.

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

`make <задача>` вызывает ту же задачу mise.

## Проверки

`mise run check` гоняет тот же набор, что CI: Mago (формат, линтер, статический анализ), OSV-Scanner, разметку, опечатки, ссылки, workflows и секреты.
