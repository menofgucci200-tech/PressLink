# Vidéos du site : présentation et parcours

Trois vidéos 1920 × 1080 publiées dans `presslink-api/public/video/` et affichées sur l'accueil et « Comment ça marche » :

| Vidéo | Fichier | Durée |
|---|---|---|
| Présentation (site, appli client, comptoir, caisse, équipe, journal, support) | `presslink-presentation.mp4` | 1 min 22 |
| Inscription, ajout du pressing et connexion | `presslink-inscription.mp4` | 1 min 07 |
| Suivi d'une commande, côté pressing et côté client | `presslink-suivi-commande.mp4` | 1 min 22 |

Les écrans sont de **vrais enregistrements** de l'application et du tableau de bord (pas de maquettes), faits sur une copie de la base de démo. À refaire après chaque évolution visible de l'appli.

## Fonctionnement

1. **Voix off** : `outils/voix.py` (Kokoro, voix `ff_siwis`) lit le `script.json` de chaque vidéo.
2. **Tournage** : `<parcours>/tourner.mjs` pilote la vraie appli (PWA) et/ou le tableau de bord avec Playwright et filme l'écran image par image (`outils/camera.mjs`). Chaque étape dure au moins le temps de sa voix.
3. **Préparation** : `outils/preparer.py` remet les images à cadence fixe (`app.mp4`) et relève la couleur de la barre d'état.
4. **Montage** : `outils/composer.mjs` (un téléphone) ou `outils/composer2.mjs` (ordinateur + téléphone) : carte d'ouverture, appareil, étapes et puces (`montage.json`), légendes, carte de fin avec le code QR.
5. **Son** : `outils/son.py` (kalimba douce sous la voix, clics, notification) puis mixage à -14 LUFS.

## Refaire les vidéos

Prérequis : Node + Playwright, ffmpeg, Python 3.12 avec `kokoro-onnx`, `phonemizer-fork`, `espeakng-loader`, `soundfile`, `scipy`, `pillow` ; modèle Kokoro (`kokoro-q8.onnx`, `voices.bin`) ; données espeak copiées dans `~/.cache/pl-espeak/espeak-ng-data` (la bibliothèque n'accepte pas les chemins longs).

```bash
# 1. Copie de la base de démo et serveurs de tournage (.claude/launch.json : api-capture, pwa-capture)
mysqldump -uroot presslink | mysql -uroot presslink_capture
#    PWA construite contre l'API de tournage :
#    flutter build web --release --base-href /app/ --dart-define=APP_ENV=development --dart-define=API_BASE_URL=http://127.0.0.1:8001/api/v1
# 2. Voix puis tournages (W = dossier de travail)
python outils/voix.py <kokoro> inscription $W/inscription/voix
node inscription/tourner.mjs http://127.0.0.1:5963/app/ $W/inscription/voix/repliques.json $W/inscription/tournage
node suivi/tourner.mjs http://127.0.0.1:8001 http://127.0.0.1:5963/app/ $W/suivi/voix/repliques.json $W/suivi/tournage
mysql -uroot presslink_capture < presentation/remettre-a-zero.sql
node presentation/tourner.mjs http://127.0.0.1:8001 http://127.0.0.1:5963/app/ $W/presentation/voix/repliques.json $W/presentation/tournage
# 3. Préparation (largeur 1440 pour l'ordinateur, 780 pour le téléphone), montage et son
python outils/preparer.py $W/inscription/tournage
./fabriquer.sh $W <kokoro> <python>
```

Avant de retourner le suivi, supprimer les commandes d'Awa Koné dans `presslink_capture` ; avant l'inscription, supprimer la cliente `+2250758211436`.
