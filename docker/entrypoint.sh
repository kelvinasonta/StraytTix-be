#!/bin/sh
set -e

# Ensure storage & bootstrap/cache exist before setting permissions
mkdir -p /var/www/html/storage /var/www/html/bootstrap/cache

# Set permissions for storage & bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache || true
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache || true

# Execute passed command (default: php-fpm)
exec "$@"
