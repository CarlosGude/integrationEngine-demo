#!/bin/sh
set -e

# /app/var is an anonymous volume, so it can arrive root-owned from an earlier
# container even when the image gets this right. php-fpm runs as www-data and
# needs to write var/cache and var/log, so make sure of it on every start.
mkdir -p /app/var/cache /app/var/log
chown -R www-data:www-data /app/var

# Start PHP-FPM in the background
php-fpm &
PHP_PID=$!

# Start Nginx in the foreground
exec nginx -g "daemon off;"

# If this script ends, kill PHP-FPM
kill $PHP_PID 2>/dev/null || true
