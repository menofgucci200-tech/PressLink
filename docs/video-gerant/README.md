# Vidéo « Gérant de pressing : du cahier au digital »

`presslink-gerant.mp4` : 55 s, 1080 × 1920 (9:16), 30 i/s, H.264 + AAC, son normalisé à -14 LUFS (statut WhatsApp, réseaux sociaux). Vignette : `presslink-gerant.jpg`.

Vidéo destinée aux gérants de pressing. Elle part du problème (la gestion au cahier), présente PressLink comme logiciel de gestion de pressing, montre ce qu'il règle sur les vrais écrans actuels (branche `main`, base de démonstration), dit un mot de l'appli client, puis invite à essayer (gratuit tout le mois de novembre, QR code de l'espace pressing : https://presslink.org/login).

| Scène | Voix off |
|---|---|
| Accroche | Vous êtes gérant de pressing ? Et vous notez encore les commandes dans un cahier ? |
| Le problème (4 illustrations) | Un cahier qui se perd, ou qui se mouille. Des tickets griffonnés, que personne n'arrive à relire. Des clients qui appellent toute la journée : c'est prêt ? Et le soir, une caisse qui ne tombe jamais juste. |
| La solution | Grâce au digital, nous avons une solution pour vous. PressLink : le logiciel de gestion de pressing. |
| L'outil (problème barré / solution) | Chaque dépôt s'enregistre en quelques secondes, et se retrouve en un clic. Le reçu est clair, imprimé ou envoyé au client. D'un coup d'œil, vous voyez les commandes en cours, prêtes, ou en retard. Et le soir, la caisse se clôture sans écart. |
| L'appli client (court) | Vos clients, eux, suivent leur commande sur l'application, et sont prévenus quand c'est prêt. |
| Fin | PressLink, c'est gratuit tout le mois de novembre. Rangez le cahier : passez au digital, dès aujourd'hui ! |

## Synchronisation et prononciation

- Chaque phrase est générée séparément (`src/voix.py`) puis rognée de ses silences : sa durée exacte est mesurée.
- `src/planifier.py` construit `src/timeline.json` à partir de ces durées (respirations fixes entre les phrases).
- `src/video.html` accroche chaque animation au début de la phrase qu'elle illustre (`V.d1`, `V.e1`…) : si la voix change, l'image suit.
- Débit ralenti (0,88) pour une articulation nette ; prononciation imposée pour PressLink, pressing, WhatsApp, Wave et digital ; phrases réécrites pour éviter les liaisons fautives (« dépôt‿est », « écrits‿à »). La transcription phonétique de chaque phrase est affichée par `voix.py` pour relecture.

Voix de synthèse Kokoro (`ff_siwis`). Pour une voix humaine : enregistrer chaque phrase dans un fichier `<clé>.wav` (a1, a2, b1…), mettre leurs durées dans `repliques.json`, puis relancer `planifier.py`, `musique.py`, `mixer.py` et `render.cjs` : tout se recale.

## Régénérer

```bash
python src/voix.py <dossier_kokoro> <dossier_voix>
python src/planifier.py <dossier_voix>/repliques.json src/timeline.json
python src/musique.py src/timeline.json <dossier_voix>/repliques.json musique.wav
python src/mixer.py src/timeline.json <dossier_voix> musique.wav mix.wav
node src/render.cjs video <dossier_voix>/repliques.json mix.wav presslink-gerant.mp4
```
