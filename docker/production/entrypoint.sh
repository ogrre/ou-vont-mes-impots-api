#!/bin/sh

set -eu

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    if ! php artisan db:seed --force; then
        echo "Reference seeding failed; aborting API startup." >&2
        exit 1
    fi
fi

if [ "${RUN_DATA_IMPORTS:-false}" = "true" ]; then
    if ! php artisan dataset:import-known "${DATA_IMPORT_PATH:-data}"; then
        echo "Critical data import failed; aborting API startup. Existing observations are preserved." >&2
        exit 1
    fi
fi

if [ "${RUN_RAP_IMPORTS:-false}" = "true" ]; then
    if ! php artisan dataset:import-rap 2024 --parse-only; then
        echo "RAP 2024 import completed with parser warnings; the API will start with all successfully imported programmes." >&2
    fi
fi

exec "$@"
