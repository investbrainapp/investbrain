FROM php:8.3-fpm

ENV DEBIAN_FRONTEND=noninteractive
ENV APP_NAME=Investbrain
ENV VITE_APP_NAME=Investbrain
ENV APP_DEBUG=true
ENV SELF_HOSTED=true

# Set the working directory
COPY . /var/app
WORKDIR /var/app

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
        binutils libc6-dev \
        supervisor \
        unzip curl git \
        nodejs npm \
# Clean up APT
    && apt-get -y autoremove \
        && apt-get clean \
        && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
# Install PHP extensions
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd pgsql zip pdo_mysql mysqli intl

# Remove default nginx config
RUN rm /etc/nginx/sites-enabled/default \
    && rm -rf /var/www/html \
    && ln -s /var/app /var/www/app

# Set permissions and ensure www-data has a shell available
RUN chown -R www-data:www-data . \ 
    && chmod -R 775 ./storage \
    && chmod +x ./docker/entrypoint.sh \
    && usermod -s /bin/bash www-data 

# Install Composer and Node.js Install PHP dependencies and build front end assets
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && composer install --no-scripts --optimize-autoloader \
    && npm install && npm run build

# Copy over configs
COPY ./docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Serve on port 80
EXPOSE 80

# Set up healthcheck
HEALTHCHECK --interval=30s --timeout=10s --retries=3 CMD curl -f http://localhost/up || exit 1

# Run everything else
ENTRYPOINT ["/bin/bash", "./docker/entrypoint.sh"]

