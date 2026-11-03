#!/bin/sh
# Démarrage de la machine Fly.io : cache Symfony, PHP-FPM en arrière-plan, Nginx au premier plan.
set -eu
cd /var/www/app
mkdir -p var/cache/prod var/log public/uploads
chown -R www-data:www-data var public/uploads
su -s /bin/sh www-data -c 'php bin/console cache:clear --env=prod --no-debug'
php-fpm -D
exec nginx -g 'daemon off;'
