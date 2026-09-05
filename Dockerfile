# syntax=docker/dockerfile:1.7
# Imagen de aplicacion de Ronda.
# Multi-etapa: la imagen final no lleva composer, ni node, ni herramientas de
# compilacion, ni codigo de desarrollo.  Ver RONDA-PLAN-MAESTRO.md sec. 11.2

# --- Etapa 1: dependencias PHP ----------------------------------------------
FROM composer:2.8 AS vendor

WORKDIR /app

# Las dependencias primero: esta capa solo se invalida si cambia composer.lock.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# El classmap necesita el codigo. Se genera aqui, donde composer existe: la
# imagen final no lleva composer (peso muerto y superficie de ataque).
COPY app app
COPY src src
COPY bootstrap bootstrap
COPY config config
COPY database database
COPY routes routes
# --no-scripts: post-autoload-dump invoca `artisan package:discover`, y aqui
# no hay artisan. El descubrimiento de paquetes se hace al arrancar.
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev --no-scripts

# --- Etapa 2: assets --------------------------------------------------------
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# --- Etapa 3: runtime -------------------------------------------------------
FROM dunglas/frankenphp:1-php8.4-alpine AS runtime

LABEL org.opencontainers.image.title="Ronda" \
      org.opencontainers.image.description="Control operativo de sucursales" \
      org.opencontainers.image.vendor="Ronda"

# Extensiones declaradas en composer.json config.platform.
RUN install-php-extensions \
        pdo_pgsql \
        pgsql \
        redis \
        intl \
        gd \
        zip \
        bcmath \
        exif \
        sockets \
        pcntl \
        opcache \
        sodium

# Chromium NO va aqui a proposito.
# Arrastra 149 paquetes (GTK, LLVM) y multiplica el tamano de la imagen que
# corre en cada nodo de aplicacion. La generacion de PDF se resuelve en un
# servicio aparte, en cola y con concurrencia limitada.
# Ver RONDA-PLAN-MAESTRO.md sec. 18, riesgo 9.

WORKDIR /app

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-ronda.ini
COPY docker/entrypoint/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Codigo de la aplicacion (el .dockerignore excluye lo que no debe entrar).
COPY --chown=www-data:www-data . /app
COPY --from=vendor --chown=www-data:www-data /app/vendor /app/vendor
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build

# busybox ash no expande llaves: se escriben las rutas completas.
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Sin root.  Ver RONDA-PLAN-MAESTRO.md sec. 10.
USER www-data

EXPOSE 8000

# Comprobacion por HTTP, no por CLI: `php artisan octane:status` arranca el
# framework entero y tarda ~17 s sobre el bind mount de Docker Desktop en
# Windows, asi que jamas cabia dentro del timeout. Ademas medía poder lanzar
# un proceso nuevo, no poder servir peticiones, que es lo que importa.
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -fsS -o /dev/null http://127.0.0.1:8000/up || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
