#!/bin/sh
# Commande de release Fly.io, exécutée avant chaque mise en service d'une nouvelle version :
# migrations de la base, puis initialisation du restaurant (sans effet s'il existe déjà).
set -eu
cd /var/www/app
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration --env=prod
php bin/console app:init-restaurant --env=prod
