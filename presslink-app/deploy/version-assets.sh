#!/usr/bin/env bash
# Place les ressources de la PWA (polices, images…) dans un dossier propre à
# chaque version : build/web/v<hash>/assets au lieu de build/web/assets.
#
# Pourquoi : Flutter ne garde dans la police d'icônes que les icônes utilisées
# par CETTE version, et les fichiers d'assets gardent toujours le même nom.
# Un téléphone ayant une version précédente en cache affichait donc des cases
# vides à la place des nouvelles icônes. Avec un dossier par version, aucune
# ressource d'une ancienne version ne peut être réutilisée.
# Usage : version-assets.sh <dossier build/web>
set -euo pipefail
cd "$1"
hash=$(md5 -q main.dart.js | cut -c1-10)
dir="v$hash"
# Dossiers de versions laissés par un build précédent (flutter build ne les
# supprime pas) : on repart propre, sinon le déplacement échoue.
rm -rf v[0-9a-f]*/
mkdir -p "$dir"
mv assets "$dir/assets"
# Préchargements et splash d'index.html
perl -pi -e "s#(href|src)=\"assets/#\$1=\"$dir/assets/#g" index.html
# Moteur Flutter : base des assets
sed -i '' "s#_flutter.loader.load({#_flutter.loader.load({ config: { assetBase: \"$dir/\" },#" flutter_bootstrap.js
grep -q "assetBase: \"$dir/\"" flutter_bootstrap.js
echo "$dir"
