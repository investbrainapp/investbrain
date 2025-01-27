#!/bin/bash

cd /var/www/app

echo -e "\n====================== Validating environment...  ====================== "
if [[ -z "$APP_KEY" ]]; then
    echo "\n > Oops! The required APP_KEY configuration is missing in your environment!  \n\n"
    echo "\n > Generating a key (see below) but this will NOT be persisted between container restarts.  \n\n"
    echo "\n > You should set this APP_KEY in your .env file!  \n\n"

    draw_box() {
      local text="$1"
      local length=${#text}
      local border=$(printf '%*s' "$((length + 4))" | tr ' ' '*')

      echo "$border"
      echo "* $text *"
      echo "$border"
    }

    export APP_KEY=base64:$(openssl rand -base64 32)
    draw_box $APP_KEY
fi

for dir in storage/framework/cache storage/framework/sessions storage/framework/views; do
    if [ ! -d "$dir" ]; then
        echo "\n > $dir is missing. Creating scaffold for storage directory... \n\n"
        mkdir -p storage/framework/{cache,sessions,views}
        chmod -R 775 storage
        chown -R www-data:www-data storage
    fi
done

if [ ! -L "public/storage" ]; then
    echo "\n > Creating symbolic link for app public storage... \n\n"
    
    /usr/local/bin/php /var/www/app/artisan storage:link
fi

echo -e "\n====================== Running migrations...  ====================== "
run_migrations() {
    /usr/local/bin/php /var/www/app/artisan migrate --force
}
RETRIES=10
DELAY=5
until run_migrations; do
  RETRIES=$((RETRIES-1))
  if [ $RETRIES -le 0 ]; then
    echo "\n > Database is not ready after $RETRIES attempts. Exiting... \n\n"
    exit 1
  fi
  echo "\n > Waiting for database to be ready... retrying in $DELAY seconds. \n\n"
  sleep $DELAY
done

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== "
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf