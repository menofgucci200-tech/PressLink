# Vidéo de présentation PressLink (côté pressing et côté client)

`presslink-presentation.mp4` : 55 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (réseaux sociaux, statut WhatsApp). Vignette : `presslink-presentation.jpg`.

Les écrans sont de vraies captures de l'application (tableau de bord web et application client), faites sur une copie de la base de démo : noms ivoiriens fictifs (« Awa Konan », « Pressing du Plateau »), catalogue et encaissements du jour ajoutés pour l'occasion.

| Temps | Scène | Voix off |
|---|---|---|
| 0 – 7,3 s | Carnet raturé, ticket, messages « c'est prêt ? », puis le logo | Carnets, tickets perdus, clients qui appellent pour savoir si c'est prêt… Avec PressLink, tout devient simple. |
| 7,3 – 15,8 s | Côté pressing : nouvelle commande en 4 étapes (client, articles, paiement, confirmation) | Côté pressing, on enregistre un dépôt en quelques secondes : le client, les articles, et le paiement. Payé, acompte, ou au retrait. |
| 15,8 – 19,2 s | Le reçu sort de l'imprimante | Le reçu est prêt aussitôt, à imprimer ou à envoyer. |
| 19,2 – 23,9 s | « Marquer prête » : le client est prévenu | Un clic, la commande est prête… et le client est prévenu automatiquement. |
| 23,9 – 31,8 s | Ordinateur : tableau de bord, clôture de caisse sans écart, vue d'ensemble du groupe | Tableau de bord, caisse du jour, clôture sans écart. Et si vous avez plusieurs pressings, tout est réuni au même endroit. |
| 31,8 – 35,8 s | Côté client : rejoindre son pressing avec un code | Côté client, on rejoint son pressing avec un simple code. |
| 35,8 – 40,2 s | Suivi Reçue → En traitement → Prête, reste à payer | On suit ses vêtements en temps réel, et on sait ce qu'il reste à payer. |
| 40,2 – 45,6 s | Notification « Votre commande est prête ! » | Dès que c'est prêt, une notification arrive. Fini les déplacements pour rien. |
| 45,6 – 55 s | 100 % gratuit pendant la phase de test, WhatsApp, e-mail, presslink.org | PressLink. Gratuit pendant la phase de test. Écrivez-nous sur WhatsApp. |

Sous-titres incrustés (lecture sans le son). Voix de synthèse Kokoro (`ff_siwis`), musique kalimba afro-pop composée note à note : mêmes outils que `docs/video-promo` (voir son README pour les prérequis). Pour une voix humaine, enregistrer chaque réplique en respectant son début dans `src/timeline.json`, puis relancer `mixer.py` et `render.cjs`.

## Régénérer

```bash
python src/voix.py <dossier_kokoro> <dossier_voix>
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-presentation.mp4
# Aperçu d'images précises : node src/render.cjs apercu <repliques.json> <dossier> 9.5 20.5 27.2
```
