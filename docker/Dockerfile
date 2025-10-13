# Stage 1: Build stage
FROM php:8.3-fpm AS builder

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_NAME=Investbrain
ENV VITE_APP_NAME=Investbrain

# Set the working directory
WORKDIR /var/app

# Install required packages
RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        libpq-dev \
        binutils libc6-dev \
        unzip curl git \
        nodejs npm \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip

# Copy application files
COPY . .

# Install Composer and Node.js Install PHP dependencies and build front end assets
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-scripts --optimize-autoloader \
    && npm install && npm run build \
    && rm -rf node_modules

# Stage 2: Production stage
FROM php:8.5.0RC2-fpm-alpine

# Set the working directory
WORKDIR /var/app

ARG VERSION=dev
ENV VERSION=$VERSION

# Copy necessary files from the builder stage
COPY --from=builder /var/app /var/app
COPY --from=builder /usr/local/etc/php/conf.d /usr/local/etc/php/conf.d
COPY --from=builder /usr/local/bin/composer /usr/local/bin/composer

# Install required Alpine packages
RUN apk add --no-cache \
        nginx \
        supervisor \
        libpng-dev \
        libzip-dev \
        icu-dev \
        postgresql-dev \
        freetype-dev \
        libjpeg-turbo-dev \
        bash \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd pgsql zip pdo_mysql pdo_pgsql mysqli intl 

# Remove default nginx config
RUN rm -rf /var/www/html \
    && ln -s /var/app /var/www/app

# Create required directories for supervisord
RUN mkdir -p /var/log/supervisor /var/run/supervisor

# Copy over configs
COPY ./docker/nginx.conf /etc/nginx/http.d/default.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Set permissions and link storage
RUN php artisan storage:link \
    && chown -R www-data:www-data . \
    && chmod +x ./docker/entrypoint.sh

# Serve on port 80
EXPOSE 80

# Set up healthcheck
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost/up || exit 1

# Run everything else
ENTRYPOINT ["/bin/sh", "./docker/entrypoint.sh"]
