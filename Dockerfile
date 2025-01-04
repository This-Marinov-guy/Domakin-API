# Use the Composer image with PHP pre-installed
FROM composer:2

# Set the working directory
WORKDIR /var/www

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install

# Set proper permissions
RUN chown -R www-data:www-data /var/www

# Switch to non-root user
USER www-data

# Expose port 8000 for Laravel (or your app)
EXPOSE 8000

# Start the Laravel application
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]