FROM php:8.4-fpm AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock symfony.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-progress \
    --no-interaction \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor

COPY . .

RUN test -f vendor/autoload_runtime.php && \
    grep -q "project_dir" vendor/autoload_runtime.php

RUN echo 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT\noutput_buffering = off\n' > /usr/local/etc/php/conf.d/symfony.ini

COPY docker/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /app/var/cache /app/var/log && chown -R www-data:www-data /app/var

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -f http://localhost/ || exit 1

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod
ENV APP_DEBUG=0

ENTRYPOINT ["/entrypoint.sh"]
