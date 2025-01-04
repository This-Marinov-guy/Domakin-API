# Use PHP CLI with Alpine for smaller image size
FROM php:8.2-cli-alpine

# Install necessary system dependencies and PHP extensions (e.g., pdo, pdo_pgsql for PostgreSQL)
RUN apk --no-cache add \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libpq-dev \
    bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_pgsql

# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install PHP dependencies using Composer
RUN composer install --no-dev --optimize-autoloader

# Set proper permissions for the application
RUN chown -R www-data:www-data /var/www

# Switch to non-root user (security best practice)
USER www-data

# Expose port 8000 for Laravel development server
EXPOSE 8000

# Start Laravel's development server
CMD ["php", "artisan", "serve"]