#!/bin/bash

cd /var/www/app

echo -e "\n====================== Validating environment...  ====================== "
if [[ -z "$APP_KEY" ]]; then
    echo " > Oops! The required APP_KEY configuration is missing in your environment! "
    echo " > Generating a key (see below) but this will NOT be persisted between container restarts. "
    echo " > You should set this APP_KEY in your .env file! "

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
        echo " > $dir is missing. Creating scaffold for storage directory..."
        mkdir -p storage/framework/{cache,sessions,views}
        chmod -R 775 storage
        chown -R www-data:www-data storage
    fi
done

if [ ! -L "public/storage" ]; then
    echo " > Creating symbolic link for app public storage..."
    
    /usr/local/bin/php /var/www/app/artisan storage:link
fi

echo -e "\n====================== Running migrations...  ====================== "
run_migrations() {
    /usr/local/bin/php /var/www/app/artisan migrate --force
}
RETRIES=10
DELAY=5
until run_migrations; do
  EXIT_STATUS=$?
  
  if [ $EXIT_STATUS -ne 0 ]; then

    RETRIES=$((RETRIES-1))
    if [ $RETRIES -le 0 ]; then
      echo " > Database is not ready after $RETRIES attempts. Exiting..."
      exit 1
    fi
    echo " > Waiting for database to be ready... retrying in $DELAY seconds."
    sleep $DELAY
  else
    # If migration was successful, break out of the loop
    echo " > Migration succeeded."
    break
  fi
done

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== "
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf