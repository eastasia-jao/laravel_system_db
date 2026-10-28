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

# Rebuild Blade templates from the deployed source instead of reusing stale compiled views.
php artisan view:clear --no-interaction

# Render assigns PORT at runtime. Apache's default configuration listens on 80.
sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:10000>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Render can occasionally take a few seconds to restore internal database DNS
# or PostgreSQL availability. Retry database-dependent startup commands before
# giving up so a brief platform interruption does not immediately cause a 502.
retry_database_command() {
    command_label="$1"
    shift
    attempt=1
    max_attempts=12
    retry_delay=5

    until "$@"; do
        if [ "$attempt" -ge "$max_attempts" ]; then
            echo "${command_label} failed after ${max_attempts} attempts." >&2
            return 1
        fi

        echo "${command_label} failed (attempt ${attempt}/${max_attempts}); retrying in ${retry_delay}s..." >&2
        attempt=$((attempt + 1))
        sleep "$retry_delay"
    done
}

# Test deployments use this startup migration because Render Free does not
# provide one-off shell jobs. Laravel skips migrations that already ran.
retry_database_command "Database migration" php artisan migrate --force --no-interaction
retry_database_command "Initial admin bootstrap" php artisan app:bootstrap-initial-admin

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
