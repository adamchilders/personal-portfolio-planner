# Use PHP 8.4 FPM as base image
FROM php:8.4-fpm

# Set working directory
WORKDIR /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libssl-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libicu-dev \
    redis-tools \
    && rm -rf /var/lib/apt/lists/*

# Configure and install GD extension first
RUN docker-php-ext-configure gd --with-freetype --with-jpeg

# Install PHP extensions one by one to avoid conflicts
RUN docker-php-ext-install -j$(nproc) pdo_mysql
RUN docker-php-ext-install -j$(nproc) mbstring
RUN docker-php-ext-install -j$(nproc) exif
RUN docker-php-ext-install -j$(nproc) pcntl
RUN docker-php-ext-install -j$(nproc) bcmath
RUN docker-php-ext-install -j$(nproc) gd
RUN docker-php-ext-install -j$(nproc) zip
RUN docker-php-ext-install -j$(nproc) intl
RUN docker-php-ext-install -j$(nproc) opcache

# Install Redis extension via PECL
RUN pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

# Remove default PHP-FPM pool configuration and replace with our custom one
RUN rm -f /usr/local/etc/php-fpm.d/www.conf.default
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Create application user
RUN groupadd -g 1000 www \
    && useradd -u 1000 -ms /bin/bash -g www www

# Copy application code
COPY . /var/www/html

# Make scripts executable
RUN chmod +x /var/www/html/bin/startup.sh \
    && chmod +x /var/www/html/bin/install.php \
    && chmod +x /var/www/html/bin/migrate.php

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Create required directories and set permissions
RUN mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/storage/cache \
    && mkdir -p /var/www/html/storage/sessions \
    && mkdir -p /var/www/html/bootstrap/cache \
    && chown -R www:www /var/www/html \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/bootstrap/cache

# Switch to non-root user for security
USER www

# Expose port 9000 for PHP-FPM
EXPOSE 9000

# Use startup script as entrypoint for initialization
ENTRYPOINT ["/var/www/html/bin/startup.sh"]

# Default command
CMD ["php-fpm"]
