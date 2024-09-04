#!/bin/bash

echo "Running entrypoint script..."

if [ ! -f /var/www/app/.env ]; then
    echo "Ope, gotta create an .env file!"

    cp /var/www/app/.env.example /var/www/app/.env
fi

echo "Waiting a second for the database to become available..."
sleep 2

echo "Running migrations..."
/usr/local/bin/php /var/www/app/artisan migrate

echo "Spinning up Supervisor daemon..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
