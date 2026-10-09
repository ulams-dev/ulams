#!/bin/bash

# disable paritcular supervisor job by deleting jobs files 

rm inited

mkdir -p /etc/supervisor/custom.d 
mkdir -p /etc/supervisor/conf.d 

if [ -n "$MULTI_DOMAINS" ]
then
  ./init_multidomains.sh
  exit 0;
fi

if [ "$DISABLE_PHP_FPM" == 'true' ]
then
    rm -f /etc/supervisor/conf.d/php-fpm.conf
    echo php-fpm.conf disabled
else 
    echo php-fpm.conf enabled
    cp docker/conf/supervisor/services/php-fpm.conf /etc/supervisor/conf.d/php-fpm.conf
fi

if [ "$DISABLE_HORIZON" == 'true' ]
then
    rm -f /etc/supervisor/custom.d/horizon.conf
    echo horizon.conf disabled
else 
    cp docker/conf/supervisor/services/horizon.conf /etc/supervisor/custom.d/horizon.conf
    echo horizon.conf enabled
fi


# queue workers for provisioned tenants (the platform queue is served by Horizon);
# queue.sh re-reads the tenant list on every pass
if [ "$DISABLE_QUEUE" == 'true' ]
then
    rm -f /etc/supervisor/custom.d/multidomain_queue.conf
    echo multidomain_queue.conf disabled
else
    cp docker/conf/supervisor/services/multidomain_queue.conf /etc/supervisor/custom.d/multidomain_queue.conf
    echo multidomain_queue.conf enabled
fi

if [ "$DISABLE_SCHEDULER" == 'true' ]
then
    rm -f /etc/supervisor/custom.d/scheduler.conf
    echo scheduler.conf disabled
else 
    cp docker/conf/supervisor/services/scheduler.conf /etc/supervisor/custom.d/scheduler.conf
    echo scheduler.conf enabled
fi

# set env from `LARAVEL_` prefixed env vars
# this also setup MULTI_DOMAINS eg 
# when MULTI_DOMAINS: "api-sprawnymarketing.ulams.app,api-gest.ulams.app" 
# then API_SPRAWNYMARKETING_ULAMS_COM_APP_NAME: '"Sprawny Marketing"'

php docker/envs/envs.php

# if binded by k8s or docker those folders might need to be recreated
mkdir storage
mkdir storage/framework
mkdir storage/framework/sessions
mkdir storage/framework/views
mkdir storage/framework/cache
mkdir storage/app
mkdir storage/logs

chmod -R 0775 storage

# run all laravel related tasks 
# klucze mozna trzymac jako zmienne srodowiskowe wiec .... 
# https://github.com/gecche/laravel-multidomain/issues/51
# create keys from env base64 variables 
if [ -n "$JWT_PUBLIC_KEY_BASE64" ]; then
    echo "Storing public RSA key for JWT generation - storage/oauth-public.key"
    echo ${JWT_PUBLIC_KEY_BASE64} | base64 -d > storage/oauth-public.key
fi

if [ -n "$JWT_PRIVATE_KEY_BASE64" ]; then
    echo "Storing private RSA key for JWT generation - storage/oauth-private.key"
    echo ${JWT_PRIVATE_KEY_BASE64} | base64 -d > storage/oauth-private.key
fi




# PHP profile (development, DEMO_PERF=1 demo, production image), see php-profile.sh
./php-profile.sh prepare

if [ "$DISABLE_DB_MIGRATE" == 'true' ]
then
    echo "Disable db migrate"
else 
    php artisan migrate --force
fi

# rebuild .env.<host> files, domain registrations and Passport keys of provisioned tenants
# (ulams:tenant:create) from the tenants table; they are not part of the image
if [ "$DISABLE_TENANT_SYNC" == 'true' ]
then
    echo "Disable tenant sync"
elif [ "$DISABLE_DB_MIGRATE" == 'true' ]
then
    php artisan ulams:tenant:sync-env
else
    php artisan ulams:tenant:sync-env --migrate
fi

# APP_KEY is generated only when empty (it encrypts the tenant secrets: ADR 0007); Passport keys
# only when storage/oauth-private.key is missing. See init-keys.sh.
./init-keys.sh

if [ "$DISABLE_DB_SEED" == 'true' ]
then
    echo "Disable db:seed"
else 
    php artisan db:seed --class=PermissionsSeeder --force --no-interaction
fi

# Passport 13 rejects key files whose mode is not 400/440/600/640/660 (the chmod -R above
# made them 0775). The public key stays group-readable for the H5P service.
find storage -maxdepth 2 -name oauth-private.key -exec chmod 600 {} +
find storage -maxdepth 2 -name oauth-public.key -exec chmod 640 {} +

# config/route/event caches per domain (demo profile, production image) or none (development)
./php-profile.sh cache

touch inited

# TODO: Fixme
# This is required so far as docker compose run this script as root 
chown -R www-data:www-data /var/www/html/storage

/usr/bin/supervisord -c /etc/supervisord.conf


