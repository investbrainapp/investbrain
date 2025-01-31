#!/bin/bash

cd /var/app

# Starting Investbrain
echo "CiAgKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioKICAqICBJSUkgICBOICAgTiAgViAgIFYgIEVFRUVFICBTU1NTICBUVFRUVCAgQkJCQkIgICBSUlJSICAgIEFBQUFBICBJSUkgICBOICAgTiAgKgogICogICBJICAgIE5OICBOICBWICAgViAgRSAgICAgIFMgICAgICAgVCAgICBCICAgIEIgIFIgICBSICAgQSAgIEEgICBJICAgIE5OICBOICAqCiAgKiAgIEkgICAgTiBOIE4gIFYgICBWICBFRUVFICAgU1NTUyAgICBUICAgIEJCQkJCICAgUlJSUiAgICBBQUFBQSAgIEkgICAgTiBOIE4gICoKICAqICAgSSAgICBOICBOTiAgViAgIFYgIEUgICAgICAgICAgUyAgIFQgICAgQiAgICBCICBSICBSICAgIEEgICBBICAgSSAgICBOICBOTiAgKgogICogIElJSSAgIE4gICBOICAgVlZWICAgRUVFRUUgIFNTU1MgICAgVCAgICBCQkJCQiAgIFIgICBSICAgQSAgIEEgIElJSSAgIE4gICBOICAqCiAgKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKioKICA=" | base64 -d

echo -e "\n====================== Validating environment...  ====================== "

# Ensure app storage directory is scaffolded
mkdir -p storage/{{framework/cache,framework/sessions,framework/views},app,logs}

# Ensure storage directory is permissioned for www-data
chmod -R 775 storage
chown -R www-data:www-data storage

echo -e "\n > Storage directory scaffolding is OK... "

# Ensure app key is generated
if [[ -z "$APP_KEY" ]]; then
    echo -e "\n > Oops! The required APP_KEY configuration is missing in your environment! "

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
else
    echo -e "\n > APP_KEY is OK... "
fi

echo -e "\n====================== Running migrations...  ====================== "

# Wait 60 seconds for database to be ready
RETRIES=12 
DELAY=5
run_migrations() {
    sleep $DELAY
    # php artisan migrate --force
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

echo -e "\n====================== Spinning up Supervisor daemon...  ====================== \n"

exec supervisord -c /etc/supervisor/conf.d/supervisord.conf

