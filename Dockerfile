FROM dunglas/frankenphp:1.12-php8.5-trixie

ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1

RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        opcache \
        pcntl \
        zip \
        bcmath \
        gd

RUN apt-get update \
    && apt-get install -y --no-install-recommends git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/ /usr/local/etc/php/conf.d/

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && composer run-script post-autoload-dump \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000

ENTRYPOINT ["sh", "docker/entrypoint.sh"]
CMD php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=${PORT:-8000}
