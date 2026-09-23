# =============================================================================
# IntegrationEngine Demo — Multi-stage Dockerfile
# =============================================================================
# This build uses composer.lock to pin exact versions (reproducible builds).
# No dev-dependencies shipped to production. All versions documented below.
#
# BASE IMAGE: php:8.4-fpm
#   - PHP 8.4: Latest stable (LTS until Nov 2027)
#   - FPM: FastCGI Process Manager for Nginx
#
# MAIN DEPENDENCIES: (from composer.lock)
#   - Symfony 7.4 LTS: Web framework (LTS until Nov 2025)
#   - IntegrationEngine v7.0+: TMDB/Stripe/Countries integrations
#   - Mercure 0.8+: Real-time WebSocket hub
#   - symfony/security-bundle 7.4: HTTP Basic auth for webhooks
#
# REMOVED (T-11 — focus on integrations):
#   - doctrine-bundle, doctrine-orm, doctrine-migrations
#   - easycorp/easyadmin-bundle
#   → No persistence layer; demo uses in-memory event transport
# =============================================================================

FROM php:8.4-fpm AS vendor

RUN apt-get update && apt-get install -y --no-install-recommends \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Use Composer 2.x for reliable dependency resolution
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app

RUN chown www-data:www-data /app \
    && mkdir -p /tmp/composer \
    && chown -R www-data:www-data /tmp/composer

ENV COMPOSER_HOME=/tmp/composer

# Copy lock files — ensures exact version reproducibility
# composer.lock: frozen dependency tree from last successful install
# symfony.lock: Flex recipe versions (not used for dev-mode)
COPY --chown=www-data:www-data composer.json composer.lock symfony.lock ./

USER www-data

# Install with --no-dev to exclude PHPUnit, PHPStan, Infection, etc.
# This keeps the production image size small (~50MB vs 120MB with dev tools)
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-progress \
    --no-interaction \
    --optimize-autoloader

COPY --chown=www-data:www-data . .

# Regenerate autoloader in production mode
# --classmap-authoritative: fail fast if a class is missing (no fallback)
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# =============================================================================
# Final Stage: Slim runtime image
# =============================================================================
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor

COPY . .

RUN test -f vendor/autoload_runtime.php && \
    grep -q "project_dir" vendor/autoload_runtime.php && \
    printf '%s\n' \
        'error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT' \
        'output_buffering = off' \
        > /usr/local/etc/php/conf.d/symfony.ini

COPY docker/nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p \
        /app/var/cache \
        /app/var/log \
        /tmp/client_temp \
        /tmp/proxy_temp \
        /tmp/fastcgi_temp \
        /tmp/uwsgi_temp \
        /tmp/scgi_temp \
    && chown -R www-data:www-data /app/var /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp /tmp/uwsgi_temp /tmp/scgi_temp

EXPOSE 8080

HEALTHCHECK --interval=10s --timeout=5s --retries=3 --start-period=30s \
    CMD curl -f http://localhost:8080/ || exit 1

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENV APP_ENV=prod
ENV APP_DEBUG=0

USER www-data

ENTRYPOINT ["/entrypoint.sh"]
