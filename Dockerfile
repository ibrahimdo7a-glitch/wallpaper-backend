FROM php:8.4-fpm-alpine

# Install system dependencies + build tools
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    postgresql-dev \
    oniguruma-dev \
    libxml2-dev \
    icu-dev \
    autoconf \
    g++ \
    make \
    zip \
    unzip

# Install Redis extension FIRST (before php-ext-install purges autoconf)
RUN pecl install redis && docker-php-ext-enable redis

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        xml \
        opcache \
        intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy composer file only (no composer.lock)
COPY composer.json ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-dev --no-security-blocking --ignore-platform-reqs

# Copy application
COPY . .

# Finish composer install
RUN composer dump-autoload --optimize --no-scripts

# Copy Filament compiled assets to public/ so php artisan serve can serve them as static files
# (Filament asset routes require package:discover at runtime; static copy is more reliable)
RUN mkdir -p \
    public/css/filament/forms \
    public/css/filament/support \
    public/css/filament/filament \
    public/js/filament/filament \
    public/js/filament/notifications \
    public/js/filament/support \
    && cp vendor/filament/forms/dist/index.css public/css/filament/forms/forms.css \
    && cp vendor/filament/support/dist/index.css public/css/filament/support/support.css \
    && cp vendor/filament/filament/dist/theme.css public/css/filament/filament/app.css \
    && cp vendor/filament/notifications/dist/index.js public/js/filament/notifications/notifications.js \
    && cp vendor/filament/support/dist/index.js public/js/filament/support/support.js \
    && cp vendor/filament/filament/dist/echo.js public/js/filament/filament/echo.js \
    && cp vendor/filament/filament/dist/index.js public/js/filament/filament/app.js

# Set permissions
RUN mkdir -p /var/www/html/storage/logs \
    /var/www/html/storage/framework/cache \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 8080

CMD php artisan migrate --force && php artisan package:discover --ansi && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}
