#!/bin/sh

# Start PHP-FPM in the background
php-fpm &
PHP_PID=$!

# Start Nginx in the foreground
exec nginx -g "daemon off;"

# If this script ends, kill PHP-FPM
kill $PHP_PID 2>/dev/null || true
