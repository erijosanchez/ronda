#!/bin/sh
set -e

# Espera a que las dependencias esten listas antes de arrancar Octane.
if [ -n "${DB_HOST:-}" ]; then
    echo "[entrypoint] esperando a postgres en ${DB_HOST}:${DB_PORT:-5432}..."
    until php -r "exit(@fsockopen(getenv('DB_HOST'), (int) (getenv('DB_PORT') ?: 5432)) ? 0 : 1);"; do
        sleep 1
    done
fi

# El manifiesto de paquetes no se pudo generar en el build (no habia artisan
# en la etapa de composer), asi que se genera aqui la primera vez. Despues ya
# existe y no se repite en cada arranque.
if [ ! -f bootstrap/cache/packages.php ]; then
    php artisan package:discover --ansi
fi

# En produccion las caches se construyen aqui, no en el build: dependen del .env.
if [ "${APP_ENV:-production}" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache
fi

exec "$@"
