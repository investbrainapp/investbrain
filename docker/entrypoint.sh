#!/bin/bash

cd /var/app

# Starting Investbrain
echo "CuKWhOKWliAgICAgICAg4paXIOKWjCAgICAg4paYICAK4paQIOKWm+KWjOKWjOKWjOKWiOKWjOKWm+KWmOKWnOKWmOKWm+KWjOKWm+KWmOKWgOKWjOKWjOKWm+KWjArilp/ilpbilozilozilprilpjilpnilpbiloTilozilpDilpbilpnilozilowg4paI4paM4paM4paM4paMCg==" | base64 -d
printf "%15s$VERSION\n"

echo -e "\n====================== Validating environment...  ====================== "

# Ensure app storage directory is scaffolded
mkdir -p storage/framework/cache \
          storage/framework/sessions \
          storage/framework/views \
          storage/app \
          storage/logs

timestamp=$(date -u "+[%Y-%m-%d %H:%M:%S]")
echo "$timestamp Investbrain starting..." >> storage/logs/laravel.log

echo -e "\n > Storage directory scaffolding is OK... "

# Ensure storage directory is permissioned for www-data
chmod -R 775 storage
chown -R www-data:www-data storage

echo -e "\n > Permissions are OK... "

# Ensure app key exists / generate if required
KEY_FILE="storage/app/.key"
if [ -z "$APP_KEY" ] && [ ! -s "$KEY_FILE" ]; then

    draw_box() {
      local text="$1"
      local length=${#text}
      local border=$(printf '%*s' "$((length + 4))" | tr ' ' '*')

      echo -e "\n\n$border"
      echo "* $text *"
      echo "$border"
    }

    export APP_KEY="$(php artisan key:generate --show)"

    echo -e "\n > Oops! The required APP_KEY configuration is missing! Generated app key and saved in $KEY_FILE"

    echo "$APP_KEY" > "$KEY_FILE"

    draw_box $APP_KEY
else
    echo -e "\n > APP_KEY is OK... "
fi

echo -e "\n====================== Running migrations...  ====================== "

# Wait 60 seconds for database to be ready
RETRIES=12 
DELAY=5
run_migrations() {
    sleep $DELAY
    output=$(php artisan migrate --force 2>/dev/null)
    if [[ $? -eq 0 ]]; then
        echo "$output"
        return 0
    else
        return 1
    fi
}
until run_migrations; do
  RETRIES=$((RETRIES-1))
  if [[ $RETRIES -le 0 ]]; then
    echo -e "\n > Database is not ready after one minute. Exiting... \n"
    exit 1
  fi
  echo -e "\n > Waiting for database to be ready... retrying in $DELAY seconds. \n"
done

echo -e "\n====================== Cleaning up...  ====================== \n"

# Clear caches
echo $(php artisan cache:clear)
echo $(php artisan view:clear)
echo $(php artisan route:clear)
echo $(php artisan event:clear)

# Re-create caches
echo $(php artisan route:cache)
echo $(php artisan event:cache)

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== \n"

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
