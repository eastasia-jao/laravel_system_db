#!/usr/bin/env sh
set -eu

# Render collects standard error, while Laravel's default stack logger writes
# to an ephemeral container file. Send runtime diagnostics to Render Logs.
export LOG_CHANNEL=stderr

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
php artisan queue:work inventory --queue=product-files --sleep=2 --tries=1 --timeout=1800 --memory=256 --verbose &
queue_worker_pid=$!
echo "Product-files import worker started (PID: $queue_worker_pid)."

stop_services() {
    kill -TERM "$queue_worker_pid" 2>/dev/null || true
    wait "$queue_worker_pid" 2>/dev/null || true
}

trap 'stop_services; exit 0' INT TERM

apache2-foreground &
apache_pid=$!
wait "$apache_pid"
apache_status=$?

stop_services
exit "$apache_status"
