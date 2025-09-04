# Stage 1: Build the PHP application with all dependencies
FROM php:8.2-fpm-alpine AS php_build

# Install dependencies for building PHP extensions
RUN apk add --no-cache \
    git \
    unzip \
    libzip-dev \
    mariadb-connector-c-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    zip

# Copy composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory and copy composer files for caching
WORKDIR /var/www/html
COPY composer.json composer.lock ./

# Install Composer dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy application source code
COPY . /var/www/html/public

# Stage 2: Create a lightweight final image with Nginx
FROM nginx:stable-alpine

# Install PHP-FPM in the final image
RUN apk add --no-cache \
    php82 \
    php82-fpm \
    php82-cli \
    php82-pdo \
    php82-pdo_mysql \
    supervisor \
 && ln -s /usr/bin/php82 /usr/bin/php   # <-- make php command available









COPY --from=php_build /var/www/html /var/www/html

# Copy the Nginx configuration
COPY nginx.conf /etc/nginx/conf.d/default.conf
COPY supervisord.conf /etc/supervisord.conf
# Set the working directory
WORKDIR /var/www/html

EXPOSE 8000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
