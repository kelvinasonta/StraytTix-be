# ==============================================================================
# Strayt Tix - Production Multi-Stage Dockerfile
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Build Dependencies via Composer
# ------------------------------------------------------------------------------
FROM composer:2 AS composer-stage

WORKDIR /app

# Copy dependency manifests first for maximum layer caching
COPY composer.json composer.lock ./

# Install production dependencies without dev tools, optimizing the classmap
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --ignore-platform-req=ext-redis

# Copy the rest of the application files (excluding those in .dockerignore)
COPY . .

# Run dump-autoload to ensure full PSR-4 classmap discovery across all modules
RUN composer dump-autoload --no-dev --optimize

# ------------------------------------------------------------------------------
# Stage 2: Lean Production Runtime (PHP-FPM)
# ------------------------------------------------------------------------------
FROM php:8.4-fpm-alpine

WORKDIR /var/www/html

# Install essential runtime libraries (Alpine)
RUN apk add --no-cache \
    bash \
    curl \
    freetype \
    icu-libs \
    libjpeg-turbo \
    libpng \
    libwebp \
    libzip \
    oniguruma \
    tzdata \
    # Temporary build dependencies for PHP extensions
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        linux-headers \
    # Configure and install core extensions
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip \
    # Install optional Redis extension (available if enabled via environment variable)
    && pecl install redis \
    && docker-php-ext-enable redis \
    # Cleanup build dependencies to minimize image footprint
    && apk del .build-deps \
    && rm -rf /tmp/pear

# Copy production PHP and PHP-FPM configuration
COPY docker/php/production.ini /usr/local/etc/php/conf.d/production.ini
COPY docker/php/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# Copy application code and vendor dependencies from builder stage
COPY --from=composer-stage --chown=www-data:www-data /app /var/www/html

# Ensure runtime directories exist with hardened permissions
RUN mkdir -p /var/www/html/storage/framework/cache/data \
             /var/www/html/storage/framework/sessions \
             /var/www/html/storage/framework/views \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Copy entrypoint script
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

CMD ["php-fpm"]
