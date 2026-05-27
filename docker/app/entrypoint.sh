#!/bin/sh
set -e

composer install --no-interaction --optimize-autoloader
npm install
npm run build

php artisan migrate
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

exec supervisord -c docker/app/supervisord.conf
