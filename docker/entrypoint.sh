#!/bin/sh
set -e

php-fpm &
PHP_PID=$!

trap 'kill "$PHP_PID" 2>/dev/null || true' EXIT INT TERM

exec nginx -g "daemon off;"
