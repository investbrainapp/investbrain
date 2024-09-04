#!/bin/bash

echo "Running entrypoint script..."

if [ ! -f /var/www/app/.env ]; then
    echo "Ope, gotta create an .env file!"

    cp /var/www/app/.env.example /var/www/app/.env
fi

echo "Installing Composer dependencies..."
/usr/local/bin/composer install

echo "Install NPM dependencies and build frontend..."
/usr/bin/npm install 
/usr/bin/npm run build 

echo "Running migrations..."
/usr/local/bin/php /var/www/app/artisan migrate

echo "Spinning up Supervisor daemon..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
