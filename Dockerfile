# syntax=docker/dockerfile:1.7

FROM node:20-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources ./resources
COPY scripts ./scripts
COPY public ./public
RUN npm run build

FROM php:8.3-cli-bookworm AS dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends git libicu-dev libonig-dev libpq-dev unzip \
    && docker-php-ext-install -j"$(nproc)" intl mbstring pdo_pgsql pgsql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --classmap-authoritative

FROM php:8.3-apache-bookworm AS runtime

ENV CI_ENVIRONMENT=production \
    PORT=10000

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        libicu-dev \
        libonig-dev \
        libpq-dev \
        postgresql-client \
    && docker-php-ext-install -j"$(nproc)" intl mbstring opcache pdo_pgsql pgsql \
    && a2enmod expires headers rewrite setenvif \
    && a2dissite 000-default \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=dependencies --chown=www-data:www-data /app ./
COPY --from=assets --chown=www-data:www-data /app/public/assets/build ./public/assets/build
COPY docker/apache-vhost.conf /etc/apache2/sites-available/inv-mat.conf
COPY docker/ports.conf /etc/apache2/ports.conf
COPY docker/php-production.ini /usr/local/etc/php/conf.d/zz-inv-mat-production.ini

RUN a2ensite inv-mat \
    && mkdir -p writable/cache writable/debugbar writable/logs writable/session writable/uploads writable/private-uploads \
    && chown -R www-data:www-data writable \
    && chmod -R 750 writable \
    && chmod 750 ops/backup.sh ops/restore.sh

EXPOSE 10000

CMD ["apache2-foreground"]
