#!/usr/bin/env bash
#
# Bootstrap / actualización del proyecto tras clonar (o en cada pull posterior).
#
# Uso:
#   ./setup.sh [create|update] [--branch=<rama>]
#
#   create   Primera vez: además de lo de "update", genera las llaves JWT/OAuth2
#            (gitignored, no vienen en el clone; quedan en config/jwt/ y config/oauth/,
#            tal como ya apunta el .env — el script no toca esas rutas), crea la base
#            de datos si no existe, y pide crear el usuario super_admin inicial.
#            Si las llaves ya existen (--skip-if-exists) no se regeneran ni se
#            sobrescriben — correr "create" de nuevo por error es seguro.
#   update   (default) Trae los últimos cambios de la rama y aplica lo pendiente
#            (migraciones, roles/permisos). Es lo que corres día a día.
#
#   --branch=<rama>     Rama a usar (default: main)
#   --web-user=<user>   Usuario/grupo con el que corre PHP-FPM/Nginx (default: www-data) —
#                        se usa solo para dejar var/ (cache, log, sesiones) escribible por ese
#                        usuario sin abrir permisos de más al resto del árbol.
#
# Ejemplos:
#   ./setup.sh                        # update sobre main
#   ./setup.sh create                 # primera vez, sobre main
#   ./setup.sh update --branch=dev    # update sobre la rama dev
#   ./setup.sh create dev             # primera vez, sobre la rama dev
#   ./setup.sh create --web-user=nginx

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")"

MODE="update"
BRANCH="main"
WEB_USER="www-data"

for arg in "$@"; do
    case "$arg" in
        --mode=*) MODE="${arg#--mode=}" ;;
        --branch=*) BRANCH="${arg#--branch=}" ;;
        --web-user=*) WEB_USER="${arg#--web-user=}" ;;
        create|update) MODE="$arg" ;;
        -h|--help)
            sed -n '2,26p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) BRANCH="$arg" ;;
    esac
done

if [[ "$MODE" != "create" && "$MODE" != "update" ]]; then
    echo "Modo inválido: '$MODE' (usa 'create' o 'update')" >&2
    exit 1
fi

step() { echo; echo "==> $1"; }

step "Modo: $MODE — Rama: $BRANCH"
if [[ -n "$(git status --porcelain)" ]]; then
    echo "Aviso: hay cambios locales sin commitear. git no los va a descartar," \
         "pero revisa 'git status' si algo falla más abajo."
fi

git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"

step "Dependencias PHP (composer install)"
composer install --no-interaction

step "Dependencias frontend (npm install)"
npm install

if [[ "$MODE" == "create" ]]; then
    step "Generando llaves JWT (lexik/jwt-authentication-bundle)"
    php bin/console lexik:jwt:generate-keypair --skip-if-exists

    step "Generando llaves OAuth2 (league/oauth2-server-bundle)"
    php bin/console league:oauth2-server:generate-keypair --skip-if-exists

    step "Creando base de datos si no existe"
    php bin/console doctrine:database:create --if-not-exists
fi

step "Aplicando migraciones pendientes"
php bin/console doctrine:migrations:migrate --no-interaction

step "Actualizando roles y permisos (app:seed-roles)"
php bin/console app:seed-roles

if [[ "$MODE" == "create" ]]; then
    step "Creando usuario super_admin inicial (app:create-user)"
    php bin/console app:create-user \
        || echo "Aviso: no se creó el usuario (¿ya existe ese email?) — podés crearlo a mano con 'php bin/console app:create-user'."
fi

step "Permisos de var/ para que $WEB_USER (usuario de PHP-FPM/Nginx) pueda escribir cache/log/sesiones"
mkdir -p var/cache var/log var/sessions
PERMS_OK=0
if command -v setfacl >/dev/null 2>&1; then
    # Método recomendado por Symfony: ACL, no toca el dueño real del archivo — funciona tanto si
    # quien despliega es root como si es un usuario sin permiso para chown. Puede fallar igual si
    # el filesystem no soporta ACLs (común en algunos overlays de contenedores), por eso el
    # fallback de abajo a chown/chmod en vez de darlo por hecho.
    if setfacl -R -m u:"$WEB_USER":rwX -m u:"$(whoami)":rwX var 2>/dev/null \
        && setfacl -dR -m u:"$WEB_USER":rwX -m u:"$(whoami)":rwX var 2>/dev/null; then
        PERMS_OK=1
    fi
fi
if [ "$PERMS_OK" = "0" ]; then
    if [ "$(id -u)" = "0" ]; then
        chown -R "$WEB_USER":"$WEB_USER" var
        chmod -R 775 var
        PERMS_OK=1
    fi
fi
if [ "$PERMS_OK" = "0" ]; then
    echo "Aviso: no pude ajustar los permisos de var/ automáticamente (sin soporte de ACL en" \
         "este filesystem y el script no corre como root). Como root, corré:" \
         "chown -R $WEB_USER:$WEB_USER var && chmod -R 775 var"
fi

step "Limpiando caché"
php bin/console cache:clear

