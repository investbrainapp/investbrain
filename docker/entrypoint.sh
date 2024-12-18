#!/bin/bash

cd /var/www/app

echo "====================== Running entrypoint script...  ====================== "
if [ ! -f ".env" ]; then
    echo " > Ope, gotta create an .env file!"

    cp .env.example .env
fi

echo "====================== Installing Composer dependencies...  ====================== "
/usr/local/bin/composer install

echo "====================== Validating environment...  ====================== "
if [ $(stat -c '%U' .) != "www-data" ]; then
    echo " > Setting correct permissions for pwd..."
    chown -R www-data:www-data .
fi

if ( ! grep -q "^APP_KEY=" ".env" || grep -q "^APP_KEY=$" ".env"); then
    echo " > Ah, APP_KEY is missing in .env file. Generating a new key!"
    
    /usr/local/bin/php artisan key:generate --force
fi

if [ ! -L "public/storage" ]; then
    echo " > Creating symbolic link for app public storage..."
    
    /usr/local/bin/php artisan storage:link
fi

echo "====================== Installing NPM dependencies and building frontend...  ====================== "
/usr/bin/npm install 
/usr/bin/npm run build 

echo "====================== Running migrations...  ====================== "
run_migrations() {
    /usr/local/bin/php artisan migrate --force
}
RETRIES=30
DELAY=5
until run_migrations; do
  RETRIES=$((RETRIES-1))
  if [ $RETRIES -le 0 ]; then
    echo "Database is not ready after multiple attempts. Exiting..."
    exit 1
  fi
  echo "Waiting for database to be ready... retrying in $DELAY seconds."
  sleep $DELAY
done

echo "====================== Spinning up Supervisor daemon...  ====================== "
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
