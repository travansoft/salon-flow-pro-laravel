FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

FROM base AS dev

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-interaction

CMD ["php-fpm"]

FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]
FROM php:8.3-fpm AS base

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libpq-dev \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo_pgsql pgsql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 9000

FROM base AS dev

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-interaction --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --no-interaction

CMD ["php-fpm"]

FROM base AS production

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["php-fpm"]

# ---- NEW: nginx target ----
FROM nginx:1.27-alpine AS nginx

RUN rm -f /etc/nginx/conf.d/default.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=production /var/www/html/public /var/www/html/public

EXPOSE 80
