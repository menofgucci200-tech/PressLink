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

# Dossier des versions (à côté de presslink-api) et adresse du site, pour l'agent de déploiement.
REMOTE_RELEASES="${REMOTE_RELEASES:-$(dirname "$REMOTE_API_DIR")/releases}"
SITE_URL="${SITE_URL:-https://presslink.org}"
KEPT_RELEASES=3
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

# Le build sans dev se fait dans le dossier de travail local : on y remet les
# outils de développement (tests, Pint…) une fois l'envoi fini, même en cas
# d'échec en cours de route.
restore_dev_dependencies() {
  log "Backend — outils de développement réinstallés en local (composer, avec dev)"
  (cd "$API_DIR" && composer install --no-interaction --quiet) \
    || warn "Réinstallation locale impossible : lancez « composer install » dans presslink-api."
}

deploy_backend() {
  log "Backend — installation des dépendances PHP (composer, sans dev)"
  trap restore_dev_dependencies EXIT
  (cd "$API_DIR" && composer install --no-dev --optimize-autoloader --no-interaction)

  log "Backend — build des assets (Vite)"
  (cd "$API_DIR" && npm ci && npm run build)

  # ── Déploiement sans coupure ────────────────────────────────────────────
  # La nouvelle version est préparée dans son propre dossier
  # (releases/<date>) pendant que l'actuelle continue de servir les pressings,
  # puis la production bascule d'un coup (un renommage de fichier). Le .env et
  # storage/ restent dans $REMOTE_API_DIR, communs à toutes les versions.
  local release archive
  release="$(date +%Y%m%d-%H%M%S)"
  archive="$(mktemp -d)/presslink-$release.zip"

  log "Backend — archive de la version $release"
  (cd "$API_DIR" && zip -qr "$archive" . \
      -x '.env' '.env.*' '.git/*' '.agents/*' '.phpunit.cache/*' 'node_modules/*' 'storage/*' \
         'public/uploads/*' 'tests/*' 'load-testing/*' 'docker/*' 'deploy/*' \
         'bootstrap/cache/config.php' 'bootstrap/cache/routes-*.php' 'bootstrap/cache/events.php' \
         '*.DS_Store')
  # Plus besoin de la version sans outils de développement en local.
  trap - EXIT
  restore_dev_dependencies

  log "Backend — agent de déploiement déposé et vérifié"
  agent_install
  local etat
  etat="$(agent_call etat "")" || { agent_remove; exit 1; }
  if [[ "$(json_get "$etat" zip)" != "1" ]]; then
    warn "Le serveur ne sait pas décompresser les archives (extension zip absente) : déploiement annulé, rien n'a changé."
    agent_remove
    exit 1
  fi
  echo "  PHP $(json_get "$etat" php) · version en ligne : $(json_get "$etat" enLigne)"

  log "Backend — envoi de l'archive ($(du -h "$archive" | cut -f1)) vers $REMOTE_RELEASES"
  lftp_run "mkdir -p $REMOTE_RELEASES; put '$archive' -o $REMOTE_RELEASES/$release.zip;"
  rm -f "$archive"

  log "Backend — nouveaux assets ajoutés au webroot ($REMOTE_WEBROOT/build), sans retirer ceux en service"
  # Les fichiers versionnés (app-XXXX.css) de la nouvelle version arrivent à
  # côté de ceux de la version en ligne : les pages déjà ouvertes continuent
  # de les trouver, et la nouvelle version a les siens avant la bascule.
  lftp_run "
    mirror --reverse --verbose --parallel=3 --ignore-time \
      --exclude .DS_Store \
      '$API_DIR/public/build' '$REMOTE_WEBROOT/build';
  "
  # Mise en cache longue des fichiers versionnés (voir presslink-api/deploy/build.htaccess).
  lftp_run "put '$API_DIR/deploy/build.htaccess' -o $REMOTE_WEBROOT/build/.htaccess;"

  log "Backend — décompression de la version sur le serveur (le site reste en ligne)"
  local depuis=0 reponse
  while :; do
    reponse="$(agent_call extraire "release=$release&depuis=$depuis")" || { agent_remove; exit 1; }
    depuis="$(json_get "$reponse" suivant)"
    echo "  $depuis / $(json_get "$reponse" total) fichiers"
    [[ "$(json_get "$reponse" fini)" == "1" ]] && break
  done

  log "Backend — bascule sur la version $release"
  reponse="$(agent_call activer "release=$release")" || { agent_remove; exit 1; }
  echo "  Précédente : $(json_get "$reponse" precedente)"

  log "Backend — vérification du site après la bascule"
  if ! site_responds; then
    warn "Le site ne répond pas correctement : retour à la version précédente."
    agent_call revenir "" >/dev/null || warn "Retour impossible : lancez ./deploy.sh rollback."
    agent_remove
    exit 1
  fi
  echo "  Site en ligne (HTTP 200)."

  log "Backend — anciennes versions supprimées (les $KEPT_RELEASES plus récentes sont gardées)"
  while :; do
    reponse="$(agent_call nettoyer "")" || break
    [[ "$(json_get "$reponse" fini)" == "1" ]] && break
  done
  agent_remove

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
  lftp_run "put '$API_DIR/deploy/images.htaccess' -o $REMOTE_WEBROOT/images/.htaccess;"

  print_followup_backend
}

# ── Agent de déploiement (presslink-api/deploy/agent.php) ──────────────────
# Sans accès shell, ce petit script PHP fait sur le serveur ce qu'une commande
# ferait (décompresser, basculer, revenir, nettoyer). Déposé dans le webroot
# sous un nom aléatoire, protégé par un jeton, supprimé à la fin (et inutilisable
# au-delà de 30 minutes).
agent_install() {
  AGENT_TOKEN="$(openssl rand -hex 24)"
  AGENT_NAME="_deploiement-$(openssl rand -hex 16).php"
  local agent_file
  agent_file="$(mktemp)"
  sed -e "s#__JETON__#$AGENT_TOKEN#" -e "s#__RELEASES__#$REMOTE_RELEASES#g" -e "s#__PARTAGE__#$REMOTE_API_DIR#g" \
    "$API_DIR/deploy/agent.php" > "$agent_file"
  lftp_run "put '$agent_file' -o $REMOTE_WEBROOT/$AGENT_NAME;"
  rm -f "$agent_file"
}

agent_remove() {
  [[ -n "${AGENT_NAME:-}" ]] && lftp_run "rm -f $REMOTE_WEBROOT/$AGENT_NAME;" >/dev/null 2>&1 || true
}

# agent_call <action> <paramètres> : réponse JSON sur stdout, échec si HTTP ≠ 200.
agent_call() {
  local corps code
  corps="$(mktemp)"
  code="$(curl -sS --max-time 120 -o "$corps" -w '%{http_code}' -X POST \
    --data "jeton=$AGENT_TOKEN&action=$1${2:+&$2}" "$SITE_URL/$AGENT_NAME")" || code=000
  if [[ "$code" != "200" ]]; then
    warn "Agent ($1) : HTTP $code — $(cat "$corps")"
    rm -f "$corps"
    return 1
  fi
  cat "$corps"
  rm -f "$corps"
}

# json_get <json> <clé> : valeur simple (true → 1).
json_get() {
  php -r '$d = json_decode($argv[1], true); $v = $d[$argv[2]] ?? ""; echo is_bool($v) ? (int) $v : $v;' "$1" "$2"
}

# Le site répond-il (page de santé et accueil) ? Trois essais, le temps que PHP voie la bascule.
site_responds() {
  local essai
  for essai in 1 2 3; do
    sleep 3
    if [[ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$SITE_URL/up")" == "200" ]] \
      && [[ "$(curl -s -o /dev/null -w '%{http_code}' --max-time 30 "$SITE_URL/")" == "200" ]]; then
      return 0
    fi
  done
  return 1
}

# ./deploy.sh rollback : revient à la version précédente, d'un coup.
rollback_backend() {
  agent_install
  log "Backend — retour à la version précédente"
  local reponse
  reponse="$(agent_call revenir "")" || { agent_remove; exit 1; }
  echo "  En ligne : $(json_get "$reponse" enLigne)"
  site_responds && echo "  Site en ligne (HTTP 200)." || warn "Le site ne répond pas correctement."
  agent_remove
}

print_followup_backend() {
  warn "Backend envoyé. Étapes manuelles restantes (pas d'exécution shell possible sur ce serveur SFTP-only) :"
  echo "  1. Migrations en attente : vérifiez s'il y a de nouveaux fichiers dans"
  echo "     presslink-api/database/migrations/ depuis le dernier déploiement et"
  echo "     appliquez-les via phpMyAdmin (ou une tâche cron LWS qui lance"
  echo "     'php artisan migrate --force' si votre offre en propose une)."
  echo "  2. La nouvelle version a ses propres caches : la tâche cron (schedule:run,"
  echo "     chaque minute) les construit dans la minute. Une modification du .env"
  echo "     est aussi reprise dans la minute. En cas de souci : ./deploy.sh rollback."
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
  echo "Usage: $0 {backend|frontend|pwa|all|rollback}" >&2
  exit 1
}

case "${1:-}" in
  backend) deploy_backend ;;
  rollback) rollback_backend ;;
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
