# Vidéo « Parcours pressing »

Explique à un pressing comment PressLink fonctionne, du site jusqu'à la remise des vêtements, en 7 étapes :
découvrir le site, tester, créer son pressing, préparer son espace, enregistrer une commande, la suivre jusqu'au
retrait, piloter (tableau de bord, commandes, clients, caisse). Tout est filmé dans la vraie application.

Résultat : `PressLink_Parcours_Pressing.mp4` à la racine du projet (1920 × 1080, 30 i/s, environ 3 min 36).

## Fichiers

- `script.json` : le texte de la voix off (27 répliques). Chaque phrase décrit une fonction qui existe déjà.
- `tourner.mjs` : le tournage, en vrai navigateur, sur une copie locale de la base. La caméra est `../outils/camera-pro.mjs` : curseur filmé, repères de cadrage, pauses hors champ.
- `montage.html` : l'habillage, avec la fenêtre de navigateur, le zoom, les cartes de chapitre, les sous-titres, le téléphone du client et la carte de fin (QR vers presslink.org).
- `fabriquer.cjs` : le montage. Il accélère les passages sans voix, comme les saisies, et cale la voix sur les repères du tournage.
- `son.py` : la musique, les bruitages et le mixage (-14 LUFS), à partir de `../../motion-vitrine/commun/audio.py`.

## Refaire la vidéo

1. **Copier la base.** Lancer l'API sur une copie de la base, par exemple `presslink_capture` sur le port 8001. Ne jamais tourner sur la vraie base : le tournage crée un pressing de test, un pressing « La Perle », des clients et des commandes. Les limites d'inscription (3 par heure) sont gardées en cache dans la base : il faut restaurer la copie avant chaque prise.
2. **Générer la voix (Kokoro).**
   ```
   python ../outils/voix.py <kokoro> . <travail>/voix
   ```
3. **Tourner.** Ajouter `apercu` à la fin de la commande pour obtenir des captures de contrôle.
   ```
   PW=<node_modules avec playwright-core> node tourner.mjs http://127.0.0.1:8001 <travail>/voix/repliques.json <travail>/rec
   ```
4. **Monter et mixer.**
   ```
   NODE_PATH=… node fabriquer.cjs plan <travail>
   python3 son.py <travail>/plan.json <travail>/voix <travail>/mix.wav
   NODE_PATH=… node fabriquer.cjs video <travail> <travail>/mix.wav PressLink_Parcours_Pressing.mp4
   ```

## Points d'attention

- **Horaires.** Ne pas enregistrer les horaires d'ouverture pendant le tournage : si l'on filme le soir, le comptoir affiche « Pressing fermé ».
- **Notification du client.** Le téléphone reprend mot pour mot le titre et le texte de `OrderReadyNotification`, avec le vrai numéro de commande.
- **Reçu.** On montre les actions du reçu (imprimer, PDF, partager), jamais un reçu papier remis au client.
