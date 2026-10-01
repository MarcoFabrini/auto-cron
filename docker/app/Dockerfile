FROM node:22-alpine AS frontend-builder

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci --ignore-scripts
COPY frontend/ .
RUN npm run build

# ---- base: solo ciò che serve a far GIRARE l'app (immagine di produzione) ----
FROM dunglas/frankenphp:1.12-php8.5-alpine AS base

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    su-exec

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    bcmath

# mariadb-dump per app:backup:create
RUN apk add --no-cache mariadb-client

RUN mkdir -p /var/storage/uploads /var/backups \
    && chown -R www-data:www-data /var/storage /var/backups \
    && chmod 750 /var/backups

WORKDIR /app

# ---- tools: strumenti di sviluppo/build (non finiscono nell'immagine di produzione) ----
FROM base AS tools

RUN apk add --no-cache \
    zip \
    unzip \
    git \
    bash

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

FROM tools AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

COPY docker/app/php.ini       /usr/local/etc/php/conf.d/autocron.ini
COPY docker/app/xdebug.ini    /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/app/Caddyfile.dev /etc/frankenphp/Caddyfile

# ---- vendor: dipendenze di produzione con autoloader a classmap autoritativa ----
FROM tools AS vendor

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --no-interaction

# La classmap deve conoscere anche le classi dell'app (src/): per questo si genera dopo averle copiate.
COPY src/ src/
RUN composer dump-autoload \
    --no-dev \
    --classmap-authoritative \
    --no-interaction

# ---- prod ----
FROM base AS prod

# Senza compose (docker run) l'immagine non deve partire in modalità dev
ENV APP_ENV=prod

COPY docker/app/php.ini      /usr/local/etc/php/conf.d/autocron.ini
COPY docker/app/opcache.ini  /usr/local/etc/php/conf.d/opcache.ini
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# composer.json serve a runtime (Symfony ricava da lì la root del progetto), composer no.
COPY composer.json composer.lock ./
COPY --from=vendor /app/vendor/ vendor/

COPY bin/ bin/
COPY config/ config/
COPY migrations/ migrations/
COPY public/ public/
COPY src/ src/
COPY templates/ templates/
COPY symfony.lock .env ./
COPY --from=frontend-builder /app/dist /app/public/frontend
COPY docker/app/Caddyfile /etc/frankenphp/Caddyfile

RUN mkdir -p var/cache var/log config/jwt \
    && chown -R www-data:www-data var config/jwt /data /config

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1/api/health || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
