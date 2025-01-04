FROM php:8.2-cli

# Install dependencies
RUN composer install

# Generate key
RUN php artisan key:generate

# Set permissions
RUN chown -R www-data:www-data /var/www
USER www-data

# Expose port 8000
EXPOSE 8000

# Start Laravel's development server
CMD ["php", "artisan", "serve"]