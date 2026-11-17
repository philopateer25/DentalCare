#!/bin/sh
set -e

# Wait for MySQL to be ready (optional but good practice)
echo "Waiting for database connection..."
# We try to run a simple artisan command that connects to the database to ensure it's up.
# A small sleep loop is usually safer here.
until php artisan db:monitor > /dev/null 2>&1; do
  echo "Database is unavailable - sleeping"
  sleep 2
done

echo "Database is up. Running migrations..."
php artisan migrate --force

echo "Compiling and optimizing caches for high performance..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan filament:cache-components || true

echo "Starting PHP-FPM..."
exec php-fpm
