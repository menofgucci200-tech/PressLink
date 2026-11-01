# Vidéo « C'est quoi PressLink ? » (présentation du produit)

`presslink-cest-quoi.mp4` : 52 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (statut WhatsApp, réseaux sociaux). Vignette : `presslink-cest-quoi.jpg`.

Les écrans sont de vraies captures des versions actuelles (branche `main`) : l'espace pressing (Pressing du Plateau) et l'appli client Flutter compilée pour le web, sur une base de démonstration (cliente fictive « Awa Konan »). Le QR code de fin ouvre l'espace pressing (https://presslink.org/login).

| Temps | Scène | Voix off |
|---|---|---|
| 0 – 3 s | « C'est quoi, PressLink ? » | C'est quoi, PressLink ? |
| 3 – 12,2 s | Logo, définition ; tableau de bord et appli client | PressLink, c'est le logiciel de gestion de pressing pensé pour la Côte d'Ivoire : un espace pour le pressing, et une application pour ses clients. |
| 12,2 – 18,4 s | Nouvelle commande (une seule page), reçu | Au comptoir, chaque dépôt s'enregistre en quelques secondes, avec un reçu à imprimer ou à envoyer. |
| 18,4 – 23,2 s | Liste des commandes, statuts | Le tableau de bord montre où en est chaque commande : reçue, en traitement, prête. |
| 23,2 – 28,6 s | Caisse du jour, clôture | La caisse du jour réunit espèces, Wave et Mobile Money, et se clôture sans écart. |
| 28,6 – 33,6 s | Clients, services et tarifs, équipe, vue d'ensemble de plusieurs pressings | Vos clients, vos tarifs, votre équipe… et même plusieurs pressings, au même endroit. |
| 33,6 – 40,2 s | Appli client : numéro, accueil, détail de la commande, notification | Vos clients, eux, se connectent avec leur numéro : ils suivent leurs vêtements, et sont prévenus dès que c'est prêt. |
| 40,2 – 52 s | Gratuit tout le mois de novembre, contacts, QR de l'espace pressing | PressLink, c'est gratuit tout le mois de novembre. Rendez-vous dans votre espace pressing ! |

Sous-titres incrustés (lecture sans le son). Voix de synthèse Kokoro (`ff_siwis`) et musique kalimba afro-pop : mêmes outils que `docs/video-presentation` (le groove démarre à 3 s).

## Régénérer

```bash
python src/voix.py <dossier_kokoro> <dossier_voix>
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-cest-quoi.mp4
# Aperçu d'images précises : node src/render.cjs apercu <repliques.json> <dossier> 11 22.5 39.5 46
```
