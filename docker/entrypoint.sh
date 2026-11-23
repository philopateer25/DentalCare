#!/bin/sh
set -e

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

echo "Database is up. Running migrations..."
php artisan migrate --force

echo "Compiling and optimizing caches for high performance..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true

echo "Starting PHP-FPM..."
exec php-fpm
