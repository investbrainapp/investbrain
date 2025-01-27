FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_NAME=Investbrain
ENV VITE_APP_NAME=Investbrain
ENV APP_DEBUG=true
ENV SELF_HOSTED=true

# Set the working directory
COPY . /var/www/app
WORKDIR /var/www/app

# Install common php extension dependencies
RUN apt-get update && apt-get install -y \
        nginx \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        libpq-dev \
        supervisor \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd pgsql bcmath zip pdo_mysql mysqli intl \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set permissions
RUN chown -R www-data:www-data . \ 
    && chmod -R 775 ./storage \
    && chmod +x ./docker/entrypoint.sh \
    && usermod -s /bin/bash www-data

# Install Composer and Node.js
RUN apt-get update && apt-get install -y \
        curl \
        unzip \
        git \
        nodejs \
        npm \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies and build front end assets
RUN composer install --no-scripts --optimize-autoloader \
    && npm install && npm run build

# Remove default nginx config
RUN rm /etc/nginx/sites-enabled/default

# Copy over configs
COPY ./docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Serve on port 80
EXPOSE 80

# Set up healthcheck
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost || exit 1

# Run everything else
ENTRYPOINT ["/bin/bash", "./docker/entrypoint.sh"]

