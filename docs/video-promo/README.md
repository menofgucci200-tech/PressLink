# Vidéo promo PressLink — format mobile

`presslink-promo-mobile.mp4` : 37 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (réseaux sociaux). Vignette : `presslink-promo-mobile.jpg`.

## Concept : « le fil PressLink »

Un fil bleu continu traverse la vidéo : il transperce le ticket papier de l'attente, relie le pressing à ses clients (avec le « P » au milieu), puis dessine un cintre qui devient le logo. Fond à motifs géométriques inspirés du wax, en tons PressLink. Signature sonore : trois notes de kalimba montantes, sur un groove afro-pop doux composé pour la vidéo.

| Temps | Scène | Voix off |
|---|---|---|
| 0 – 4,6 s | Ticket papier sur un cintre, « … et on attend » | Au pressing, on dépose ses vêtements… et on attend, sans savoir. |
| 4,6 – 8,9 s | Le fil relie « Votre pressing » et « Vos clients » | Avec PressLink, le pressing et ses clients restent liés. |
| 8,9 – 13,4 s | Vrais écrans de l'application, étapes Reçue → Prête | Suivez vos vêtements en temps réel, depuis votre téléphone. |
| 13,4 – 18 s | Notification « Votre commande est prête ! » | Dès qu'ils sont prêts, vous êtes prévenu. Fini les déplacements pour rien. |
| 18 – 25,5 s | Tableau de bord pressing (ordinateur + mobile) | Et pour les pressings : commandes, clients, équipe… tout est simple, et vos clients sont informés automatiquement. |
| 25,5 – 29,5 s | Le cintre devient le logo | PressLink. Le lien entre votre pressing et vos clients. |
| 29,5 – 37 s | QR code, presslink.org/app, WhatsApp pressings | Gratuit pour les clients. Rendez-vous sur presslink.org. |

Sous-titres incrustés (lecture sans le son). Les captures viennent de la vraie application, avec les données de l'environnement de démo (« Pressing Élégance », cliente « Aïcha Koné », tous fictifs).

## Voix off

Voix de synthèse neuronale Kokoro-82M, voix française `ff_siwis`, ralentie à 0,9 pour un débit posé, avec un traitement chaleur/présence. Aucune voix à l'accent africain n'était disponible dans l'environnement de production. Pour une voix humaine ivoirienne, faire enregistrer le texte du tableau ci-dessus en respectant le début de chaque réplique (`src/timeline.json`), puis relancer `mixer.py` et `render.cjs` avec ces fichiers à la place de ceux générés.

## Régénérer

Prérequis : Python 3 (numpy, scipy), un venv avec `kokoro-onnx` et `soundfile`, Node + Playwright/Chromium, ffmpeg, polices Inter / Inter Display.

```bash
# 1. Modèle Kokoro (npm) : kokoro-q8-shards (poids) + kokoro-js (voix)
#    → concaténer les parts en kokoro-q8.onnx et regrouper la voix ff_siwis dans voices.bin (npz)
python src/voix.py <dossier_kokoro> <dossier_voix>          # répliques + repliques.json (durées)
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-promo-mobile.mp4
# Aperçu d'images précises : node src/render.cjs apercu <repliques.json> <dossier> 4.5 9 14
```

Le montage (`src/video.html`) est piloté par une seule fonction `render(t)` : chaque image est calculée puis capturée, ce qui rend le rendu exact et reproductible.
