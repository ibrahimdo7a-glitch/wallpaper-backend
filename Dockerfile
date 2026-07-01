FROM php:8.4-fpm-alpine

# Install system dependencies + build tools
RUN apk add --no-cache \
    git \
    curl \
    nginx \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    libavif-dev \
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
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
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
    /var/www/html/storage/app/public/livewire-tmp \
    /var/www/html/storage/app/private/livewire-tmp \
    /var/www/html/storage/app/livewire-tmp \
    /var/www/html/bootstrap/cache \
    && chmod -R 777 /var/www/html/storage /var/www/html/bootstrap/cache

# Increase PHP upload limits (large APKs up to ~500 MB)
RUN echo "upload_max_filesize=550M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=560M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time=600" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_input_time=600" >> /usr/local/etc/php/conf.d/uploads.ini

# Nginx config: serves static files + proxies PHP to php-fpm (parallel workers).
# Replaces the single-threaded `php artisan serve`.
COPY docker/nginx.conf /etc/nginx/http.d/app.conf.template
RUN rm -f /etc/nginx/http.d/default.conf

EXPOSE 8080

CMD mkdir -p storage/app/public/livewire-tmp storage/app/private/livewire-tmp storage/app/livewire-tmp /run/nginx \
    && chmod -R 777 storage \
    && php artisan config:clear || true \
    && php artisan cache:clear || true \
    && php artisan migrate --force \
    && php artisan package:discover --ansi \
    && php artisan filament:assets \
    && php artisan db:seed --class=DefaultCategoriesSeeder --force \
    && php artisan storage:link --force \
    && sed "s/__PORT__/${PORT:-8080}/g" /etc/nginx/http.d/app.conf.template > /etc/nginx/http.d/app.conf \
    && php-fpm -D \
    && ( while true; do php artisan queue:work --queue=image-processing,watermark,default --sleep=3 --tries=3 --max-time=3600 --memory=256; sleep 2; done & ) \
    && ( while true; do php artisan schedule:work; sleep 5; done & ) \
    && nginx -g 'daemon off;'
