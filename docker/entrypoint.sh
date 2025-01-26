#!/bin/bash

cd /var/www/app

echo -e "\n====================== Validating environment...  ====================== "
run_as_www_user() {
    su - www-data -c "/usr/local/bin/php /var/www/app/artisan $1"
}

if [ ! -f ".env" ]; then
    echo " > Ope, you forgot to create an .env file! Create the required .env file and restart the container!"

    exit 1;
fi

if ( ! grep -q "^APP_KEY=" ".env" || grep -q "^APP_KEY=$" ".env"); then
    echo " > The required APP_KEY configuration is missing in your .env file. Copy and paste this key into your .env file. Then restart the container!"

    draw_box() {
      local text="$1"
      local length=${#text}
      local border=$(printf '%*s' "$((length + 4))" | tr ' ' '*')

      echo "*$border*"
      echo "* $text *"
      echo "*$border*"
    }

    draw_box "base64:$(openssl rand -base64 32)"

    exit 1;
fi

if [ ! -L "public/storage" ]; then
    echo " > Creating symbolic link for app public storage..."
    
    run_as_www_user "storage:link"
fi

echo -e "\n====================== Running migrations...  ====================== "
run_migrations() {
    run_as_www_user "migrate --force"
}
RETRIES=30
DELAY=5
until run_migrations; do
  RETRIES=$((RETRIES-1))
  if [ $RETRIES -le 0 ]; then
    echo " > Database is not ready after multiple attempts. Exiting..."
    exit 1
  fi
  echo " > Waiting for database to be ready... retrying in $DELAY seconds."
  sleep $DELAY
done

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== "
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf