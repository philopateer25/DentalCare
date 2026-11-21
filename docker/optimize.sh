#!/bin/sh
# Rebuild Laravel's caches inside the clinic_app container and reload PHP.
#
# Run after changing .env, config, routes or PHP code:
#   docker exec clinic_app sh docker/optimize.sh && docker restart clinic_nginx
#
# Config/route/event caches are written to storage/framework/cache (a Docker volume, see
# APP_*_CACHE in docker-compose.yml), never to bootstrap/cache in the shared project folder,
# so host-side `php artisan test` runs are not affected. `artisan optimize` is avoided on
# purpose: it also writes bootstrap/cache/blade-icons.php with container paths.
set -e
cd /var/www/html
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache
php artisan filament:cache-components
rm -f bootstrap/cache/blade-icons.php
# opcache.validate_timestamps=0 (docker/php/opcache.ini): reload PHP-FPM so code changes apply.
kill -USR2 1
echo "Caches rebuilt and PHP reloaded."
