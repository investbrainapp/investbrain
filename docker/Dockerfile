FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive

# Set the working directory
COPY . /var/www/app
WORKDIR /var/www/app

# Set permissions
RUN chown -R www-data:www-data . \ 
    && chmod -R 775 ./storage \
    && chmod +x ./docker/entrypoint.sh \
# Install common php extension dependencies
    && apt-get update && apt-get install -y \
        nginx \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        supervisor \
        && docker-php-ext-configure gd --with-freetype --with-jpeg \
        && docker-php-ext-install -j$(nproc) \
                gd \
                zip \
                pdo_mysql \
                mysqli \
                intl \
# remove default nginx config
    && rm /etc/nginx/sites-enabled/default

# Install Composer and Node.js
RUN apt-get update && apt-get install -y \
        curl \
        unzip \
        git \
        nodejs \
        npm \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
RUN composer install --no-scripts --optimize-autoloader

# Install Node dependencies and build assets
RUN npm install && npm run build

# Copy the custom Nginx configuration
COPY ./docker/nginx.conf /etc/nginx/conf.d/default.conf

# Copy over supervisor configuration
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Serve on port 80
EXPOSE 80

# Set up healthcheck
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost || exit 1

USER www-data

# Run everything else
ENTRYPOINT ["/bin/bash", "./docker/entrypoint.sh"]
CMD ["./docker/entrypoint.sh"]
