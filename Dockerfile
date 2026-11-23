# Зависимости ставит отдельная стадия: в итоговый образ не попадают composer и кеш.
# Стадия собирается на том же PHP, что и приложение. В образе composer:2 своя
# версия PHP, и на ней composer отвергает зависимости, рассчитанные на PHP 8.5.
FROM php:8.5-cli AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
# composer распаковывает архивы пакетов через unzip.
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --optimize-autoloader

FROM php:8.5-apache
# Драйвер PostgreSQL для PDO собирается из исходников PHP, ему нужны заголовки libpq.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*
ENV PORT=3000
WORKDIR /var/www/html
COPY app.php migrations.php ./
COPY bin ./bin
COPY migrations ./migrations
COPY src ./src
COPY --from=vendor /app/vendor ./vendor
# Apache слушает PORT и отдаёт любой путь в app.php. Остальные файлы закрыты для запросов.
RUN printf '%s\n' 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf '%s\n' \
        '<VirtualHost *:${PORT}>' \
        '    DocumentRoot /var/www/html' \
        '    DirectoryIndex app.php' \
        '    FallbackResource /app.php' \
        '    <DirectoryMatch "^/var/www/html/(bin|migrations|src|vendor)">' \
        '        Require all denied' \
        '    </DirectoryMatch>' \
        '    <Files migrations.php>' \
        '        Require all denied' \
        '    </Files>' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf
EXPOSE 3000
# Сначала миграции базы, затем Apache. Ошибка миграции останавливает запуск,
# без DATABASE_URL шаг миграций ничего не делает.
CMD ["sh", "-c", "php bin/migrate.php && exec apache2-foreground"]
