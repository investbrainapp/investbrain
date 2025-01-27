FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_NAME=Investbrain
ENV VITE_APP_NAME=Investbrain
ENV APP_DEBUG=true
ENV SELF_HOSTED=true

# Set the working directory
COPY . /var/www/app
WORKDIR /var/www/app

# Install required packages
RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y \
        nginx \
        libfreetype-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        zlib1g-dev \
        libzip-dev \
        libicu-dev \
        libpq-dev \
        supervisor \
        unzip git \
        nodejs npm \
# Install PHP extensions
    && curl -sSL https://packages.sury.org/php/README.txt | bash -x && apt-get update \
    && apt-get install -y \
        php8.3-gd \
        php8.3-pgsql \
        php8.3-zip \
        php8.3-mysql \
        php8.3-intl \
# Clean up APT
    && apt-get -y autoremove \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* 

# Set permissions
RUN chown -R www-data:www-data . \ 
    && chmod -R 775 ./storage \
    && chmod +x ./docker/entrypoint.sh \
    && usermod -s /bin/bash www-data

# Install Composer and Node.js Install PHP dependencies and build front end assets
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-scripts --optimize-autoloader \
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

