#!/usr/bin/env sh
set -eu

# Render collects standard error, while Laravel's default stack logger writes
# to an ephemeral container file. Send runtime diagnostics to Render Logs.
export LOG_CHANNEL=stderr

# Render mounts persistent disks before starting the container. Make the
# configured upload directory writable by Apache's www-data user.
if [ -n "${PUBLIC_STORAGE_PATH:-}" ]; then
    mkdir -p "$PUBLIC_STORAGE_PATH"
    chown -R www-data:www-data "$PUBLIC_STORAGE_PATH"
fi

# Point Laravel's public storage URL at the same disk used for uploads.
php artisan storage:link --force --no-interaction

# Render assigns PORT at runtime. Apache's default configuration listens on 80.
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:10000>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Test deployments use this startup migration because Render Free does not
# provide one-off shell jobs. Laravel skips migrations that already ran.
php artisan migrate --force --no-interaction
php artisan app:bootstrap-initial-admin

# Free Render services cannot run a separate Background Worker. For test
# deployments, keep the product-file queue consumer in this same container.
# It is intentionally a single worker to limit memory use on the free plan.
echo "Starting product-files import worker..."
apache2-foreground &
apache_pid=$!
echo "Apache started (PID: $apache_pid)."

stop_services() {
    kill -TERM "$apache_pid" 2>/dev/null || true
    wait "$apache_pid" 2>/dev/null || true
}

trap 'stop_services; exit 0' INT TERM

# Keep the queue worker as the container's primary process. Render now tracks
# it reliably while Apache continues serving the web application above.
exec php artisan queue:work inventory --queue=product-files --sleep=2 --tries=3 --timeout=1800 --memory=256 --verbose
