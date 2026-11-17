#!/bin/sh
set -e

mkdir -p storage/app/public storage/app/imports storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

if [ "${MIGRATE_ON_START:-false}" = "true" ]; then
    php artisan migrate --force
    php artisan db:seed --force
fi

php artisan storage:link --force
php artisan optimize

exec "$@"
