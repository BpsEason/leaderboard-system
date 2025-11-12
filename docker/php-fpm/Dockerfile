# docker/php-fpm/Dockerfile
FROM php:8.3-fpm-alpine

# Install PHP extensions
RUN apk add --no-cache \
    mysql-client \
    git \
    unzip \
    # Add other necessary packages for PHP extensions
    # For imagick, you'll need imagemagick-dev and imagemagick
    imagemagick-dev \
    imagemagick \
    # For gd, you'll need libjpeg-turbo-dev, libpng-dev, freetype-dev
    libjpeg-turbo-dev \
    libpng-dev \
    freetype-dev && \
    docker-php-ext-install pdo_mysql bcmath exif pcntl zip && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    pecl install redis && \
    docker-php-ext-enable redis

# Set working directory
WORKDIR /var/www/html

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# You might want to copy your composer.json/lock and run composer install here
# COPY src/composer.json src/composer.lock ./
# RUN composer install --no-dev --optimize-autoloader

EXPOSE 9000
CMD ["php-fpm"]