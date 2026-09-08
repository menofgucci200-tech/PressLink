#!/usr/bin/env bash
#
# Déploiement PressLink — pousse le code vers GitHub puis synchronise le
# serveur de production (hébergement mutualisé LWS, sans Node.js dispo :
# les assets front sont donc compilés ici en local puis envoyés par rsync,
# jamais buildés côté serveur).
#
# Usage :
#   ./deploy.sh              # backend + frontend (par défaut)
#   ./deploy.sh backend      # API Laravel uniquement (git pull, migrations, cache)
#   ./deploy.sh frontend     # assets compilés (CSS/JS Vite) uniquement
#   ./deploy.sh all          # équivalent à la commande sans argument
#
# Ne couvre PAS l'app mobile Flutter (presslink-app) : un APK/IPA se
# construit et se distribue autrement (voir presslink-app/android/README.md
# et presslink-app/ios/README.md), pas par un simple push serveur.

set -euo pipefail

# --- Configuration --------------------------------------------------------
SSH_HOST="webdb2203"
SSH_USER="press2855966"
REMOTE_PATH="~/PressLink/presslink-api"
LOCAL_API_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/presslink-api" && pwd)"
# ---------------------------------------------------------------------------

MODE="${1:-all}"

log() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }
die() { printf '\033[1;31m✖ %s\033[0m\n' "$1" >&2; exit 1; }

case "$MODE" in
    backend|frontend|all) ;;
    *) die "Usage : ./deploy.sh [backend|frontend|all]" ;;
esac

# --- 1. Pousser le code sur GitHub -----------------------------------------
log "Push du code vers GitHub"
git -C "$(dirname "$LOCAL_API_DIR")" push origin main

# --- 2. Backend : synchroniser le serveur avec le dépôt --------------------
if [[ "$MODE" == "backend" || "$MODE" == "all" ]]; then
    log "Backend : git pull + migrations + cache sur $SSH_HOST"
    ssh "${SSH_USER}@${SSH_HOST}" "cd ${REMOTE_PATH} && \
        git pull origin main && \
        composer install --no-dev --optimize-autoloader --no-interaction && \
        php artisan migrate --force && \
        php artisan optimize:clear && \
        php artisan optimize"
fi

# --- 3. Frontend : build local + envoi des assets compilés -----------------
if [[ "$MODE" == "frontend" || "$MODE" == "all" ]]; then
    log "Frontend : build des assets (npm run build)"
    (cd "$LOCAL_API_DIR" && npm run build)

    log "Frontend : envoi de public/build vers $SSH_HOST"
    rsync -az --delete "$LOCAL_API_DIR/public/build/" \
        "${SSH_USER}@${SSH_HOST}:${REMOTE_PATH}/public/build/"

    log "Frontend : vidage du cache de vues Laravel (Blade compilé)"
    ssh "${SSH_USER}@${SSH_HOST}" "cd ${REMOTE_PATH} && php artisan view:clear"
fi

log "Déploiement terminé (${MODE})."
