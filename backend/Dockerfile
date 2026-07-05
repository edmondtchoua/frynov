# ─────────────────────────────────────────────
#  Backend Laravel — Multi-stage Dockerfile
# ─────────────────────────────────────────────

# ── Stage 1 : Dépendances PHP ────────────────
FROM php:8.3-fpm-alpine AS base

# Extensions système requises
RUN apk add --no-cache \
    linux-headers \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    icu-dev \
    curl \
    git \
    unzip

# Extensions PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    gd \
    bcmath \
    intl \
    opcache \
    pcntl

# Redis extension via PECL
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www


# ── Stage 2 : Développement ──────────────────
FROM base AS development

# Xdebug pour le coverage
RUN pecl install xdebug && docker-php-ext-enable xdebug

COPY infra/php/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini
COPY infra/php/php-dev.ini /usr/local/etc/php/conf.d/custom.ini

# Installer les dépendances Composer (avec dev)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader

# Copier le code source
COPY . .
RUN composer dump-autoload --optimize

CMD ["php-fpm"]


# ── Stage 3 : Build production ───────────────
FROM base AS builder

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative


# ── Stage 4 : Production ─────────────────────
FROM base AS production

COPY infra/php/php-prod.ini /usr/local/etc/php/conf.d/custom.ini
COPY infra/php/opcache.ini  /usr/local/etc/php/conf.d/opcache.ini

# Copier uniquement le nécessaire depuis le builder
COPY --from=builder /var/www /var/www

# Permissions correctes
RUN chown -R www-data:www-data /var/www \
 && chmod -R 755 /var/www/storage \
 && chmod -R 755 /var/www/bootstrap/cache

USER www-data

CMD ["php-fpm"]
