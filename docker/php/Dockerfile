FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    git zip unzip libpng-dev libonig-dev libzip-dev libicu-dev \
&& docker-php-ext-install pdo_mysql mbstring zip intl gd

# composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
