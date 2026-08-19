# ---------------------------------------------------------
# STAGE 1: Frontend Build (Node.js)
# ---------------------------------------------------------
FROM node:20-alpine as frontend

WORKDIR /app
COPY package*.json ./
RUN npm ci

COPY . .
RUN npm run build


# ---------------------------------------------------------
# STAGE 2: PHP Application
# ---------------------------------------------------------
FROM php:8.3-fpm-alpine

# Set working directory
WORKDIR /var/www

# Install system dependencies
RUN apk add --no-cache \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    icu-dev \
    oniguruma-dev \
    bash

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl zip dom

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy existing application directory contents
COPY . /var/www

# Copy built frontend assets from stage 1
COPY --from=frontend /app/public/build /var/www/public/build

# Install PHP dependencies (production)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set proper permissions for Laravel
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Create storage link (will only work if public/storage doesn't exist, we force it)
RUN rm -rf /var/www/public/storage && php artisan storage:link

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Start PHP-FPM server
CMD ["php-fpm"]
