# syntax=docker/dockerfile:1.7
# Imagen de aplicacion de Ronda.
# Multi-etapa: la imagen final no lleva composer, ni node, ni herramientas de
# compilacion, ni codigo de desarrollo.  Ver RONDA-PLAN-MAESTRO.md sec. 11.2

# ── Etapa 1: dependencias PHP ────────────────────────────────────────────────
FROM composer:2.8 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# ── Etapa 2: assets ──────────────────────────────────────────────────────────
FROM node:22-alpine AS assets

WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
RUN npm run build

# ── Etapa 3: runtime ─────────────────────────────────────────────────────────
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

RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Sin root.  Ver RONDA-PLAN-MAESTRO.md sec. 10.
USER www-data

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD php artisan octane:status || exit 1

ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=8000", "--workers=auto", "--max-requests=500"]
