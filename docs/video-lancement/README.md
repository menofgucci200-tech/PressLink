# Vidéo de lancement PressLink (gratuit jusqu'au 15 novembre)

`presslink-lancement.mp4` : 44 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (statut WhatsApp, réseaux sociaux). Vignette : `presslink-lancement.jpg`.

Elle reprend le style de la page d'accueil et des affiches du lancement. Les écrans du tableau de bord et de l'appli viennent des captures de `docs/video-presentation/src/assets`. Les deux écrans de connexion par numéro (« Votre numéro de téléphone », « Bienvenue sur PressLink ») sont recréés en HTML d'après l'appli. Le QR code ouvre WhatsApp au +225 07 18 00 22 93 avec le message « Bonjour, je veux inscrire mon pressing sur PressLink. ».

| Temps | Scène | Voix off |
|---|---|---|
| 0 – 3,6 s | « Lancement officiel », logo, confettis | C'est officiel : PressLink est lancé ! |
| 3,6 – 8,6 s | GRATUIT, jusqu'au 15 novembre | Pour le lancement, c'est gratuit pour tous les pressings, jusqu'au 15 novembre. |
| 8,6 – 14 s | Ordinateur : tableau de bord, caisse clôturée sans écart | Commandes, paiements, reçus et caisse du jour : tout votre pressing, au même endroit. |
| 14 – 19,8 s | Téléphone : nouvelle commande en 4 étapes | Un dépôt s'enregistre en quelques secondes : le client, les articles, le paiement. C'est fait. |
| 19,8 – 25,8 s | Appli client : le numéro suffit, les commandes attendent déjà | Vos clients n'ont besoin que de leur numéro : leurs commandes les attendent déjà dans l'application. |
| 25,8 – 30,2 s | Notification « Votre commande est prête ! » | Et dès que c'est prêt, ils sont prévenus. Fini les appels pour rien. |
| 30,2 – 35,6 s | Rejoindre en 3 étapes | Pour nous rejoindre, écrivez-nous sur WhatsApp : on installe votre espace avec vous. |
| 35,6 – 44 s | Gratuit jusqu'au 15 novembre, WhatsApp, e-mail, QR code | PressLink. Gratuit jusqu'au 15 novembre. Écrivez-nous dès aujourd'hui ! |

Sous-titres incrustés (lecture sans le son). Voix de synthèse Kokoro (`ff_siwis`) et musique kalimba afro-pop : mêmes outils que `docs/video-presentation` (le groove démarre ici à 3,6 s, sur « GRATUIT »).

## Régénérer

```bash
python src/voix.py <dossier_kokoro> <dossier_voix>
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-lancement.mp4
# Aperçu d'images précises : node src/render.cjs apercu <repliques.json> <dossier> 5.5 22.9 42
```
