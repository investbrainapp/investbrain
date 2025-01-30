#!/bin/bash

cd /var/app

echo -e "\n====================== Validating environment...  ====================== "
for dir in storage/framework/cache storage/framework/sessions storage/framework/views; do
    if [ ! -d "$dir" ]; then
        echo -e "\n > $dir is missing. Creating scaffold for storage directory... "
        mkdir -p storage/framework/{cache,sessions,views}
        chmod -R 775 storage
        chown -R www-data:www-data storage
    fi
done

if [ ! -L "public/storage" ]; then
    echo -e "\n > Creating symbolic link for app public storage... "
    
    php artisan storage:link
fi

if [[ -z "$APP_KEY" ]]; then
    echo -e "\n > Oops! The required APP_KEY configuration is missing in your environment! "
    echo -e "\n > You should set this APP_KEY in your .env file! "

    draw_box() {
      local text="$1"
      local length=${#text}
      local border=$(printf '%*s' "$((length + 4))" | tr ' ' '*')

      echo -e "\n\n$border"
      echo "* $text *"
      echo "$border"
    }

    export APP_KEY=$(php artisan key:generate --show)
    draw_box $APP_KEY
fi

echo -e "\n====================== Running migrations...  ====================== "
run_migrations() {
    php artisan migrate --force
}
RETRIES=12 # wait 60 seconds for database to be ready
DELAY=5
until run_migrations; do
  RETRIES=$((RETRIES-1))
  if [ $RETRIES -le 0 ]; then
    echo -e "\n > Database is not ready after $RETRIES attempts. Exiting... "
    exit 1
  fi
  echo -e "\n > Waiting for database to be ready... retrying in $DELAY seconds. "
  sleep $DELAY
done

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== \n"
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf