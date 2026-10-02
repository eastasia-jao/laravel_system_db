#!/usr/bin/env sh
set -eu

# Render assigns PORT at runtime. Apache's default configuration listens on 80.
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:10000>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Test deployments use this startup migration because Render Free does not
# provide one-off shell jobs. Laravel skips migrations that already ran.
php artisan migrate --force --no-interaction

exec apache2-foreground
