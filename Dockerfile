FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    curl \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

COPY composer.json ./
ENV DATABASE_URL="sqlite:///:memory:"
ENV APP_ENV="dev"
ENV APP_DEBUG=1
RUN composer install --no-progress --no-interaction --no-scripts

COPY . .
RUN composer dump-autoload --optimize && \
    cp vendor/symfony/runtime/Internal/autoload_runtime.template vendor/autoload_runtime.php && \
    sed -i "s/%runtime_class%/'Symfony\\\\Component\\\\Runtime\\\\SymfonyRuntime'/g" vendor/autoload_runtime.php && \
    sed -i 's|%runtime_options%|\[\]|g' vendor/autoload_runtime.php && \
    echo 'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT\noutput_buffering = off\n' > /usr/local/etc/php/conf.d/symfony.ini

COPY docker/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /app/var && chmod 777 /app/var

EXPOSE 80

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -f http://localhost/ || exit 1

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod
ENV APP_DEBUG=0

ENTRYPOINT ["/entrypoint.sh"]
