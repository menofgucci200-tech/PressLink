#!/usr/bin/env bash
# Fabrique les vidéos de parcours à partir des tournages déjà faits.
#   ./fabriquer.sh <dossier_travail> <dossier_kokoro> <python>
# <dossier_travail>/<parcours>/{voix,tournage} doivent exister (voir README).
set -euo pipefail
W="$1"; K="$2"; PY="$3"; ICI="$(cd "$(dirname "$0")" && pwd)"
cd "$ICI"
# Inscription (un seul appareil)
"$PY" outils/voix.py "$K" inscription "$W/inscription/voix"
node outils/composer.mjs inscription "$W/inscription/tournage" "$W/inscription/voix" plan
"$PY" outils/son.py "$W/inscription/tournage/plan.json" "$W/inscription/voix" "$W/inscription/mix.wav"
node outils/composer.mjs inscription "$W/inscription/tournage" "$W/inscription/voix" video "$W/inscription/mix.wav" "$W/presslink-inscription.mp4"
# Suivi d'une commande (ordinateur + téléphone)
node outils/composer2.mjs suivi "$W/suivi/tournage" "$W/suivi/voix" plan
"$PY" outils/son.py "$W/suivi/tournage/plan.json" "$W/suivi/voix" "$W/suivi/mix.wav"
node outils/composer2.mjs suivi "$W/suivi/tournage" "$W/suivi/voix" video "$W/suivi/mix.wav" "$W/presslink-suivi-commande.mp4"
# Présentation (ordinateur + téléphone)
node outils/composer2.mjs presentation "$W/presentation/tournage" "$W/presentation/voix" plan
"$PY" outils/son.py "$W/presentation/tournage/plan.json" "$W/presentation/voix" "$W/presentation/mix.wav"
node outils/composer2.mjs presentation "$W/presentation/tournage" "$W/presentation/voix" video "$W/presentation/mix.wav" "$W/presslink-presentation.mp4"
