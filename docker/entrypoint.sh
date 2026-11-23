#!/bin/sh
set -e

# SQLite: make sure the database file exists before anything connects to it
# (a fresh clinic install starts with an empty data volume).
if [ "${DB_CONNECTION:-}" = "sqlite" ] && [ -n "${DB_DATABASE:-}" ] && [ ! -f "$DB_DATABASE" ]; then
  if [ "$#" -eq 0 ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    touch "$DB_DATABASE"
  fi
fi

# Wait until the database answers.
echo "Waiting for database connection..."
until php artisan db:monitor > /dev/null 2>&1; do
  echo "Database is unavailable - sleeping"
  sleep 2
done

# Worker / scheduler containers pass their own command (docker-compose `command:`),
# e.g. `php artisan schedule:work`. Run it instead of the web server. The app
# container owns migrations and caches, so they are not repeated here (two
# containers migrating at once can collide).
if [ "$#" -gt 0 ]; then
  echo "Starting: $*"
  exec "$@"
fi

# An update (new image pulled by Watchtower) may bring database changes. Take a
# database backup first, so a bad update can always be rolled back.
if [ "${DB_CONNECTION:-}" = "sqlite" ] && [ -s "${DB_DATABASE:-/nonexistent}" ] \
  && php artisan migrate:status 2>/dev/null | grep -q "Pending"; then
  echo "Database changes in this version - backing up first..."
  php artisan backup:run --only-db || echo "WARNING: pre-update backup failed; continuing."
fi

echo "Database is up. Running migrations..."
php artisan migrate --force

echo "Compiling and optimizing caches for high performance..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true

# Everything above ran as root; php-fpm workers, the queue and the scheduler
# run as www-data and must be able to write logs, uploads, backups and the DB.
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
if [ -n "${DB_DATABASE:-}" ] && [ -f "$DB_DATABASE" ]; then
  chown -R www-data:www-data "$(dirname "$DB_DATABASE")" 2>/dev/null || true
fi

echo "Starting PHP-FPM..."
exec php-fpm
