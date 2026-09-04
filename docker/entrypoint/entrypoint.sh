#!/bin/sh
set -e

# Espera a que las dependencias esten listas antes de arrancar Octane.
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] esperando a postgres en ${DB_HOST}:${DB_PORT:-5432}..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int) (getenv('DB_PORT') ?: 5432)) ? 0 : 1);"; do
        sleep 1
    done
fi

# En produccion las caches se construyen aqui, no en el build: dependen del .env.
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
