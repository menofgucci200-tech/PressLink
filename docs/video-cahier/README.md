# Vidéo « Rangez le cahier, passez à PressLink » (gratuit tout le mois de novembre)

`presslink-rangez-le-cahier.mp4` : 47 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (statut WhatsApp, réseaux sociaux). Vignette : `presslink-rangez-le-cahier.jpg`.

Même canevas que l'affiche « Rangez le cahier ». Les écrans sont de vraies captures de l'espace pressing actuel (branche `main`, création de commande sur une seule page), faites sur une base de démonstration : Pressing du Plateau, cliente fictive « Awa Konan ». Le QR code de fin ouvre l'espace pressing (https://presslink.org/login), où un pressing sans compte trouve « Inscrire mon pressing gratuitement ».

| Temps | Scène | Voix off |
|---|---|---|
| 0 – 6,5 s | Le cahier griffonné, lignes barrées, messages « C'est prêt ? » | Le cahier qui se perd, les tickets illisibles, les clients qui appellent pour savoir si c'est prêt… |
| 6,5 – 10,4 s | « Rangez le ~~cahier~~. Passez à PressLink. » · Gratuit tout le mois de novembre | Il est temps de ranger le cahier. Passez à PressLink ! |
| 10,4 – 14,3 s | Nouvelle commande : recherche « Awa », la cliente est choisie | Une nouvelle commande ? On tape le nom ou le numéro du client. |
| 14,3 – 20,4 s | Nettoyage, 3 chemises, 2 pantalons, 1 grand boubou brodé, total 12 000 F | On touche les pièces : trois chemises, deux pantalons, un grand boubou. Le total se calcule tout seul. |
| 20,4 – 26,6 s | Acompte 5 000 F par Wave, commande validée, reçu PL-219234 | Acompte par Wave ou Mobile Money : on valide, et le reçu est prêt à imprimer ou à envoyer. |
| 26,6 – 33,8 s | Fiche de la commande : en traitement, puis prête ; notification au client | Ensuite, chaque commande se suit en un clic : en traitement, puis prête. Et le client est prévenu automatiquement. |
| 33,8 – 47 s | Gratuit tout le mois de novembre, WhatsApp, e-mail, QR de l'espace pressing | PressLink, c'est gratuit tout le mois de novembre. Rendez-vous dans votre espace pressing ! |

Sous-titres incrustés (lecture sans le son). Voix de synthèse Kokoro (`ff_siwis`) et musique kalimba afro-pop : mêmes outils que `docs/video-presentation` (le groove démarre à 6,5 s, sur « Passez à PressLink »).

## Refaire les captures

Les captures de `src/assets` (`d00` à `d12` pour la commande, `s01` à `s03` pour le suivi, `recu.jpg`, `telephone-commande.jpg`) se refont en lançant l'espace pressing sur une base de démonstration, puis en pilotant la page « Nouvelle commande » avec Playwright (ordinateur 1440 × 900 @2x, téléphone 430 × 932 @3x, `LANG=fr_FR.UTF-8` pour les dates).

## Régénérer

```bash
python src/voix.py <dossier_kokoro> <dossier_voix>
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-rangez-le-cahier.mp4
# Aperçu d'images précises : node src/render.cjs apercu <repliques.json> <dossier> 5 16.4 30.5 42
```
