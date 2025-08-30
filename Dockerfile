# Use official PHP image with Composer
FROM php:8.2-cli

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy app files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose Cloud Run's default port
EXPOSE 8080

# Start Laravel using Cloud Run's PORT env var
CMD php artisan serve --host=0.0.0.0 --port=$PORT
