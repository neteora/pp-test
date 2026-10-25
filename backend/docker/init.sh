#!/bin/sh
# Passo esplicito, da eseguire UNA volta (e a ogni deploy con nuove migrazioni):
#   docker compose run --rm app sh docker/init.sh
set -e
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console messenger:setup-transports --no-interaction
