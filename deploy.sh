#!/usr/bin/env bash
#
# Déploiement PressLink vers LWS (presslink.org).
#
#   ./deploy.sh backend    # API/dashboard Laravel (presslink-api)
#   ./deploy.sh frontend   # code source de l'app mobile Flutter (presslink-app)
#   ./deploy.sh pwa        # PWA : build Flutter web publié sur presslink.org/app/
#   ./deploy.sh all        # les trois
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

# Optionnels : valeurs par défaut si absents de deploy.config.
REMOTE_PWA_DIR="${REMOTE_PWA_DIR:-$REMOTE_WEBROOT/app}"
PWA_URL="${PWA_URL:-https://presslink.org/app/}"
PWA_API_BASE_URL="${PWA_API_BASE_URL:-https://presslink.org/api/v1}"

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

  log "Backend — fichiers statiques à la racine du webroot (SEO : robots, favicon, images de partage, QR)"
  # Le webroot ($REMOTE_WEBROOT) est une copie séparée de presslink-api/public :
  # ces fichiers n'y arrivent que si on les envoie. favicon.ico y était vide
  # (0 octet) et robots.txt était celui par défaut.
  local static_file
  # manifest-espace.json, espace-sw.js, hors-connexion.html : espace pressing
  # installable sur PC (le service worker doit être servi à la racine).
  for static_file in robots.txt favicon.ico apple-touch-icon.png favicon-48.png favicon-96.png favicon-192.png og-image.png qr-presslink-app.png manifest-espace.json espace-sw.js hors-connexion.html; do
    lftp_run "put '$API_DIR/public/$static_file' -o $REMOTE_WEBROOT/$static_file;"
  done

  # Fichiers de vérification Google Search Console (googleXXXX.html) : doivent
  # être servis à la racine du domaine pour que la vérification réussisse.
  local verification_file
  for verification_file in "$API_DIR"/public/google*.html; do
    [[ -e "$verification_file" ]] || continue
    lftp_run "put '$verification_file' -o $REMOTE_WEBROOT/$(basename "$verification_file");"
  done

  # Images (photos de téléphones, icônes WhatsApp/appel) et vidéo du parcours
  # du site public.
  local static_dir
  for static_dir in images video; do
    lftp_run "
      mirror --reverse --verbose --parallel=3 \
        --exclude .DS_Store \
        '$API_DIR/public/$static_dir' '$REMOTE_WEBROOT/$static_dir';
    "
  done

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

deploy_pwa() {
  if ! command -v flutter >/dev/null 2>&1; then
    echo "Erreur : flutter n'est pas installé ou pas dans le PATH." >&2
    exit 1
  fi

  # Garde-fou : ce dossier est purgé en partie à chaque déploiement ; il ne
  # doit jamais pointer sur le webroot lui-même (qui sert Laravel).
  if [[ "$REMOTE_PWA_DIR" == "$REMOTE_WEBROOT" || "$REMOTE_PWA_DIR" == "/" ]]; then
    echo "Erreur : REMOTE_PWA_DIR ($REMOTE_PWA_DIR) ne doit pas être le webroot." >&2
    exit 1
  fi

  log "PWA — build Flutter web (release, servie sous /app/)"
  # App ID OneSignal : public par nature (visible dans le code de la page).
  ONESIGNAL_APP_ID="${ONESIGNAL_APP_ID:-b08351a3-2b31-49cd-a6a9-61d45a295e86}"
  (cd "$APP_DIR" && flutter build web --release --base-href /app/ \
    --dart-define=APP_ENV=production \
    --dart-define=API_BASE_URL="$PWA_API_BASE_URL" \
    --dart-define=ONESIGNAL_APP_ID="$ONESIGNAL_APP_ID")

  log "PWA — ressources placées dans un dossier propre à cette version"
  # Voir deploy/version-assets.sh : sans cela, un téléphone ayant l'ancienne
  # police d'icônes en cache affichait des cases vides. Les dossiers des
  # versions précédentes restent sur le serveur, pour les pages encore ouvertes.
  "$APP_DIR/deploy/version-assets.sh" "$APP_DIR/build/web"

  log "PWA — purge des dossiers générés côté serveur avant renvoi"
  # Même raison que vendor/composer : main.dart.js, flutter_service_worker.js
  # (qui référence le hash de chaque fichier) et assets/ sont générés
  # ENSEMBLE. Un mirror basé sur les dates pourrait en laisser une moitié
  # périmée, d'où une PWA cassée. On purge donc avant de renvoyer.
  lftp_run "
    mkdir -p $REMOTE_PWA_DIR;
    rm -rf $REMOTE_PWA_DIR/assets;
    rm -rf $REMOTE_PWA_DIR/icons;
    rm -rf $REMOTE_PWA_DIR/splash;
    rm -rf $REMOTE_PWA_DIR/canvaskit;
  "

  log "PWA — envoi vers $REMOTE_PWA_DIR"
  # canvaskit/ (~40 Mo) est exclu : par défaut, Flutter charge le moteur de
  # rendu depuis le CDN de Google (gstatic.com), pas depuis notre serveur.
  lftp_run "
    mirror --reverse --verbose --parallel=3 --ignore-time \
      --exclude-glob canvaskit/ \
      --exclude .DS_Store \
      '$APP_DIR/build/web' '$REMOTE_PWA_DIR';
    put '$APP_DIR/deploy/pwa.htaccess' -o $REMOTE_PWA_DIR/.htaccess;
  "

  # --ignore-time ne compare que les tailles : la page d'entrée et le chargeur
  # ne changent souvent que par le nom du dossier de version (même longueur),
  # et n'étaient alors pas renvoyés (ancienne page + nouveau main.dart.js).
  # Ces petits fichiers sont donc toujours renvoyés.
  lftp_run "
    put '$APP_DIR/build/web/index.html' -o $REMOTE_PWA_DIR/index.html;
    put '$APP_DIR/build/web/flutter_bootstrap.js' -o $REMOTE_PWA_DIR/flutter_bootstrap.js;
    put '$APP_DIR/build/web/flutter_service_worker.js' -o $REMOTE_PWA_DIR/flutter_service_worker.js;
    put '$APP_DIR/build/web/hors-connexion-sw.js' -o $REMOTE_PWA_DIR/hors-connexion-sw.js;
    put '$APP_DIR/build/web/version.json' -o $REMOTE_PWA_DIR/version.json;
    put '$APP_DIR/build/web/manifest.json' -o $REMOTE_PWA_DIR/manifest.json;
  "

  log "PWA — préchauffage de la compression (Brotli/gzip) de main.dart.js"
  # Observé au premier déploiement : la toute première requête d'un navigateur
  # sur main.dart.js (3 Mo) a échoué une fois (net::ERR_HTTP2_PROTOCOL_ERROR)
  # alors que les suivantes passaient — très probablement la compression à la
  # volée du serveur sur un gros fichier jamais encore servi. On la déclenche
  # ici, une fois par encodage, pour que ce soit le script qui l'absorbe plutôt
  # que le premier visiteur.
  local enc
  for enc in br gzip; do
    curl -s -H "Accept-Encoding: $enc" -o /dev/null "${PWA_URL}main.dart.js" \
      || warn "  préchauffage $enc : échec (non bloquant)"
  done

  log "PWA — vérification de $PWA_URL"
  local url code
  for url in "$PWA_URL" "${PWA_URL}manifest.json" "${PWA_URL}main.dart.js"; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$url" || true)"
    if [[ "$code" == "200" ]]; then
      echo "  OK  $code  $url"
    else
      warn "  KO  $code  $url"
    fi
  done
  echo
  echo "  Pour installer : ouvrez $PWA_URL sur le téléphone."
  echo "  Android/Chrome : bouton « Installer » de la bannière. iPhone/Safari :"
  echo "  Partager > Sur l'écran d'accueil."
}

usage() {
  echo "Usage: $0 {backend|frontend|pwa|all}" >&2
  exit 1
}

case "${1:-}" in
  backend) deploy_backend ;;
  frontend) deploy_frontend ;;
  pwa) deploy_pwa ;;
  all)
    deploy_backend
    deploy_frontend
    deploy_pwa
    ;;
  *) usage ;;
esac

log "Terminé."
