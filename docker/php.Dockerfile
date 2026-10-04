# syntax=docker/dockerfile:1.10

FROM php:8.4-fpm

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install \
    intl \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies first for better layer caching
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=nova_username,env=NOVA_USERNAME \
    --mount=type=secret,id=nova_license_key,env=NOVA_LICENSE_KEY \
    export COMPOSER_AUTH="$(php -r 'echo json_encode(["http-basic" => ["nova.laravel.com" => ["username" => getenv("NOVA_USERNAME"), "password" => getenv("NOVA_LICENSE_KEY")]]]);')" \
    && composer install --no-interaction --prefer-dist --no-autoloader --no-scripts

# Copy application source
COPY . .

RUN composer dump-autoload --no-interaction --optimize

RUN chown -R www-data:www-data /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
