FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions \
    pdo_sqlite \
    pdo_mysql \
    pdo_pgsql \
    intl \
    zip \
    opcache

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-progress

COPY . .
RUN composer dump-autoload --optimize

ENV COMPOSER_MEMORY_LIMIT=-1
ENV APP_ENV=prod
ENV APP_DEBUG=0
ENV FRANKENPHP_CONFIG="import /etc/caddy/Caddyfile"

COPY docker/Caddyfile /etc/caddy/Caddyfile

EXPOSE 80
EXPOSE 443

ENTRYPOINT ["frankenphp", "run"]
CMD ["--config", "/etc/caddy/Caddyfile"]
