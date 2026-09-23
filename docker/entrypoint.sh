#!/bin/sh
set -e

php-fpm &
exec nginx -g "daemon off;"
