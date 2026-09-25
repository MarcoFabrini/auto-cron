FROM node:20-alpine AS frontend-builder

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json* ./
RUN npm ci --ignore-scripts
COPY frontend/ .
RUN npm run build

FROM dunglas/frankenphp:1.12-php8.5-alpine AS base

RUN apk add --no-cache \
    icu-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    bash \
    su-exec

RUN docker-php-ext-install \
    pdo_mysql \
    intl \
    zip \
    bcmath

RUN apk add --no-cache mariadb-client

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN mkdir -p /var/storage/uploads /var/backups \
    && chown -R www-data:www-data /var/storage /var/backups

WORKDIR /app

FROM base AS dev

RUN apk add --no-cache $PHPIZE_DEPS linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del $PHPIZE_DEPS

COPY docker/app/php.ini       /usr/local/etc/php/conf.d/autocron.ini
COPY docker/app/xdebug.ini    /usr/local/etc/php/conf.d/xdebug.ini
COPY docker/app/Caddyfile.dev /etc/frankenphp/Caddyfile

FROM base AS prod

COPY docker/app/php.ini      /usr/local/etc/php/conf.d/autocron.ini
COPY docker/app/opcache.ini  /usr/local/etc/php/conf.d/opcache.ini
COPY docker/app/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-interaction \
    --optimize-autoloader

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

ENTRYPOINT ["entrypoint.sh"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
