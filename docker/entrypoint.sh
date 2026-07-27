#!/bin/sh
#
# Corre en cada arranque del contenedor (no solo el primero). config/jwt y config/oauth
# deben estar montados como volumen persistente en EasyPanel: --skip-if-exists hace que
# generar las llaves sea seguro en cada boot, pero solo pasa de verdad la primera vez.
set -e

php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
php bin/console league:oauth2-server:generate-keypair --skip-if-exists --no-interaction

php bin/console doctrine:database:create --if-not-exists --no-interaction

# Deploy desde cero (DB recién creada, ninguna migración ejecutada todavía): la migración
# más vieja de este proyecto asume un schema base que nunca quedó como migración (se creó
# a mano en su momento). Para una DB nueva generamos el schema actual directo desde las
# entidades y marcamos las migraciones existentes como ya aplicadas, en vez de intentar
# correrlas desde cero. En un update (la DB ya tiene migraciones aplicadas) esto no entra
# y se sigue el camino normal de doctrine:migrations:migrate de abajo.
EXECUTED_MIGRATIONS="$(php bin/console doctrine:migrations:status --no-interaction 2>/dev/null | grep -E '\| *Executed *\|' | grep -oE '[0-9]+')"
if [ "$EXECUTED_MIGRATIONS" = "0" ]; then
    echo "==> DB vacía: creando schema base desde las entidades y marcando migraciones existentes como aplicadas"
    php bin/console doctrine:schema:create --no-interaction
    php bin/console doctrine:migrations:sync-metadata-storage --no-interaction
    php bin/console doctrine:migrations:version --add --all --no-interaction
fi

php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:seed-roles

php bin/console cache:clear

exec "$@"
