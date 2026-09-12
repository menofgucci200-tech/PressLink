#!/usr/bin/env bash
#
# Déploiement PressLink vers LWS (presslink.org).
#
#   ./deploy.sh backend    # API/dashboard Laravel (presslink-api)
#   ./deploy.sh frontend   # app mobile Flutter (presslink-app)
#   ./deploy.sh all        # les deux
#
# IMPORTANT — ce que ce script fait et ne fait PAS :
#   Le compte LWS n'autorise que le SFTP (pas de vrai shell distant :
#   "This service allows sftp connections only."). deploy.sh ne peut donc
#   QUE transférer des fichiers, pas exécuter composer/npm/artisan sur le
#   serveur. Il compile donc tout EN LOCAL (composer install, npm run
#   build) puis envoie le résultat déjà prêt à l'emploi. À la fin, il
#   affiche les étapes qu'il reste à faire à la main (migrations de base
#   de données notamment — voir la fonction print_followup_backend).
#
# Sécurité : le mot de passe SFTP vit uniquement dans deploy.config
# (jamais committé, voir .gitignore). Ne jamais coller ce mot de passe
# ailleurs (issue GitHub, message Slack, etc.).

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_FILE="$SCRIPT_DIR/deploy.config"

if [[ ! -f "$CONFIG_FILE" ]]; then
  echo "Erreur : $CONFIG_FILE introuvable." >&2
  echo "Copiez deploy.config.example en deploy.config et remplissez vos identifiants." >&2
  exit 1
fi

# shellcheck disable=SC1090
source "$CONFIG_FILE"

for var in SSH_HOST SSH_USER SSH_PASS REMOTE_API_DIR REMOTE_APP_DIR REMOTE_WEBROOT; do
  if [[ -z "${!var:-}" ]]; then
    echo "Erreur : $var manquant dans deploy.config." >&2
    exit 1
  fi
done

if ! command -v lftp >/dev/null 2>&1; then
  echo "Erreur : lftp n'est pas installé. Sur macOS : brew install lftp" >&2
  exit 1
fi

API_DIR="$SCRIPT_DIR/presslink-api"
APP_DIR="$SCRIPT_DIR/presslink-app"

log() { echo -e "\n\033[1;34m▶ $*\033[0m"; }
warn() { echo -e "\033[1;33m⚠ $*\033[0m"; }

# Exécute un script lftp donné sur stdin, avec les identifiants de deploy.config.
lftp_run() {
  lftp -u "$SSH_USER","$SSH_PASS" "sftp://$SSH_HOST" -e "
    set sftp:auto-confirm yes;
    set net:max-retries 2;
    set net:timeout 20;
    $1
    bye
  "
}

deploy_backend() {
  log "Backend — installation des dépendances PHP (composer, sans dev)"
  (cd "$API_DIR" && composer install --no-dev --optimize-autoloader --no-interaction)

  log "Backend — build des assets (Vite)"
  (cd "$API_DIR" && npm ci && npm run build)

  log "Backend — purge de vendor/composer et public/build côté serveur avant renvoi"
  # Ces deux dossiers sont des ENSEMBLES de fichiers générés ensemble par
  # composer/vite (les fichiers d'autoload de Composer se référencent les
  # uns les autres par un hash de classe). Un "mirror" classique compare
  # les dates de fichiers pour décider quoi retransférer — mais lftp
  # réplique la date du fichier LOCAL sur le serveur au moment de l'envoi,
  # donc si le fichier local n'a pas été retouché depuis le dernier
  # déploiement (même si son contenu a changé, ex. hash Composer
  # régénéré), il est vu comme "déjà à jour" et sauté : on obtient un jeu
  # de fichiers à moitié neufs / à moitié anciens, incompatibles entre eux
  # (exactement ce qui a cassé le site la première fois). On supprime donc
  # ces deux dossiers avant de les renvoyer, pour forcer un envoi complet
  # et cohérent plutôt que de se fier à la comparaison de dates.
  lftp_run "
    rm -rf $REMOTE_API_DIR/vendor/composer;
    rm -rf $REMOTE_API_DIR/public/build;
  "

  log "Backend — envoi du code + vendor vers $REMOTE_API_DIR (SFTP, sans suppression distante)"
  # --reverse = upload (local -> distant). Pas de --delete global : on
  # n'efface jamais tout le reste du dossier distant automatiquement
  # (fichiers de logs, uploads clients, etc. ne sont de toute façon pas
  # dans ces dossiers locaux, mais mieux vaut rester prudent sur un
  # hébergement partagé sans accès shell pour vérifier les dégâts).
  lftp_run "
    mirror --reverse --verbose --parallel=3 --ignore-time \
      --exclude-glob .env \
      --exclude-glob .env.* \
      --exclude-glob .git/ \
      --exclude-glob .agents/ \
      --exclude-glob .phpunit.cache/ \
      --exclude-glob node_modules/ \
      --exclude-glob storage/ \
      --exclude-glob public/uploads/ \
      --exclude-glob tests/ \
      --exclude-glob load-testing/ \
      --exclude-glob docker/ \
      --exclude .DS_Store \
      '$API_DIR' '$REMOTE_API_DIR';
  "

  log "Backend — copie des assets buildés vers le webroot ($REMOTE_WEBROOT/build)"
  # index.php est servi depuis $REMOTE_WEBROOT, séparé de presslink-api/public
  # (pas de symlink possible sans shell) : les assets statiques doivent donc
  # être dupliqués aux deux endroits pour être servis par Apache. Même
  # logique de purge que pour vendor/composer ci-dessus : manifest.json et
  # les fichiers hashés vont ensemble, mieux vaut les renvoyer d'un bloc.
  lftp_run "rm -rf $REMOTE_WEBROOT/build;"
  lftp_run "
    mirror --reverse --verbose --parallel=3 --ignore-time \
      --exclude .DS_Store \
      '$API_DIR/public/build' '$REMOTE_WEBROOT/build';
  "

  print_followup_backend
}

print_followup_backend() {
  warn "Backend envoyé. Étapes manuelles restantes (pas d'exécution shell possible sur ce serveur SFTP-only) :"
  echo "  1. Migrations en attente : vérifiez s'il y a de nouveaux fichiers dans"
  echo "     presslink-api/database/migrations/ depuis le dernier déploiement et"
  echo "     appliquez-les via phpMyAdmin (ou une tâche cron LWS qui lance"
  echo "     'php artisan migrate --force' si votre offre en propose une)."
  echo "  2. Si un comportement semble figé après coup (config/route cachés),"
  echo "     ce projet ne cache pas config/route en prod pour l'instant — donc"
  echo "     rien à vider normalement. Sinon, contactez le support LWS pour"
  echo "     vider bootstrap/cache/*.php à la main."
  echo "  3. Vérifiez presslink.org dans le navigateur après quelques secondes"
  echo "     (le temps que le cache d'assets Apache/CDN éventuel se rafraîchisse)."
}

deploy_frontend() {
  log "Frontend — envoi du code source de l'app mobile vers $REMOTE_APP_DIR"
  # L'app Flutter n'est pas "servie" par ce serveur (elle est distribuée en
  # APK/IPA ou via les stores) : on se contente de synchroniser le code
  # source, comme copie de référence côté serveur (build/, .dart_tool/ et
  # les dépendances ne sont jamais générés ni nécessaires ici).
  lftp_run "
    mirror --reverse --verbose --parallel=3 \
      --exclude-glob .git/ \
      --exclude-glob build/ \
      --exclude-glob .dart_tool/ \
      --exclude-glob .idea/ \
      --exclude .DS_Store \
      '$APP_DIR' '$REMOTE_APP_DIR';
  "
  warn "Frontend envoyé. Rappel : ceci ne publie pas l'app sur les stores —"
  echo "  utilisez vos process habituels (flutter build appbundle / ipa) pour ça."
}

usage() {
  echo "Usage: $0 {backend|frontend|all}" >&2
  exit 1
}

case "${1:-}" in
  backend) deploy_backend ;;
  frontend) deploy_frontend ;;
  all)
    deploy_backend
    deploy_frontend
    ;;
  *) usage ;;
esac

log "Terminé."
