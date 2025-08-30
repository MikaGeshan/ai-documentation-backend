# Use official PHP image
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    git \
    && docker-php-ext-install pdo pdo_mysql zip mbstring bcmath exif gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Fix permissions for Laravel storage
RUN chown -R www-data:www-data storage bootstrap/cache

# Expose Railway/Cloud Run default port
EXPOSE 8080

# Run migrations and start Laravel
CMD php artisan migrate --force && php artisan serve --host=0.0.0.0 --port=8080
