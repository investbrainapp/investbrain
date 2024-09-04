#!/bin/bash

echo "===============   Running entrypoint script...    =============== "

if [ ! -f "/var/www/app/.env" ]; then
    echo " > Ope, gotta create an .env file!"

    cp /var/www/app/.env.example /var/www/app/.env
fi

if ( ! grep -q "^APP_KEY=" "/var/www/app/.env" || grep -q "^APP_KEY=$" "/var/www/app/.env"); then
    echo " > Ah, APP_KEY is missing in .env file. Generating a new key!"
    
    /usr/local/bin/php /var/www/app/artisan key:generate
fi

echo "===============   Installing Composer dependencies...    =============== "
/usr/local/bin/composer install

echo "===============   Install NPM dependencies and build frontend...    =============== "
/usr/bin/npm install 
/usr/bin/npm run build 

echo "===============   Running migrations...    =============== "
/usr/local/bin/php /var/www/app/artisan migrate --force

echo "===============   Spinning up Supervisor daemon...    =============== "
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
