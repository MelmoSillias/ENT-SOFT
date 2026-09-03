# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

# Platform extensions required by composer.json + Impression (Dompdf / PhpSpreadsheet / PhpWord).
RUN apk add --no-cache \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        zip \
    && apk del $PHPIZE_DEPS \
    && rm -rf /var/cache/apk/*

WORKDIR /app

COPY api/composer.json api/composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY api/ .
RUN composer dump-autoload --optimize --classmap-authoritative

# Symfony requires a .env file at boot; entrypoint regenerates it from container env.
COPY api/.env.docker.dist .env

FROM php:8.3-fpm-alpine AS runtime

RUN apk add --no-cache \
        bash \
        freetype \
        icu-dev \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libwebp \
        libzip \
        nginx \
        oniguruma \
        sqlite-libs \
        wget \
        freetype-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        sqlite-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install \
        bcmath \
        gd \
        intl \
        mbstring \
        opcache \
        pdo_sqlite \
        zip \
    && apk del \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        sqlite-dev \
    && rm -rf /var/cache/apk/*

COPY docker/api/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/api/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/api/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor /app /var/www/html

RUN mkdir -p var/cache var/log var/share var/database public/uploads/logos \
    && chown -R www-data:www-data var public/uploads \
    && chmod -R ug+rwX var

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
