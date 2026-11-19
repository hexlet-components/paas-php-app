# Зависимости ставит отдельная стадия: в итоговый образ не попадают composer и кеш.
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-progress --prefer-dist --optimize-autoloader

FROM php:8.5-apache
ENV PORT=3000
WORKDIR /var/www/html
COPY app.php .
COPY --from=vendor /app/vendor ./vendor
# Apache слушает PORT и отдаёт любой путь в app.php. Каталог vendor закрыт для запросов.
RUN printf '%s\n' 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf '%s\n' \
        '<VirtualHost *:${PORT}>' \
        '    DocumentRoot /var/www/html' \
        '    DirectoryIndex app.php' \
        '    FallbackResource /app.php' \
        '    <DirectoryMatch "^/var/www/html/vendor">' \
        '        Require all denied' \
        '    </DirectoryMatch>' \
        '</VirtualHost>' \
        > /etc/apache2/sites-available/000-default.conf
EXPOSE 3000
CMD ["apache2-foreground"]
