# PressLink en Docker

Stack : `app` (PHP-FPM 8.4) + `nginx` + `mysql` + `queue-worker` (notifications).

## Démarrage

1. Copier `.env.example` en `.env` et ajouter les variables spécifiques à Docker :

   ```
   DB_HOST=mysql
   DB_USERNAME=presslink
   DB_PASSWORD=un-mot-de-passe-fort
   DB_ROOT_PASSWORD=un-autre-mot-de-passe-fort
   ```

2. Construire et démarrer :

   ```
   docker compose build
   docker compose up -d
   ```

3. Première initialisation (migrations + lien de stockage) :

   ```
   docker compose exec app php artisan migrate --force
   docker compose exec app php artisan storage:link
   ```

4. L'app est servie sur http://localhost:8000.

## Notes

- **Jamais** `docker compose exec app php artisan migrate:fresh` sur un environnement contenant de vraies données — règle déjà en place pour ce projet, elle s'applique aussi ici.
- Le worker de queue (`queue-worker`) doit tourner en permanence : sans lui, les notifications push ne partent jamais (silencieusement).
- La clé de service Firebase (`storage/app/firebase/service-account.json`) n'est pas copiée dans l'image (`.dockerignore`) — elle doit être montée en volume ou injectée au déploiement, jamais buildée dans l'image.
- Si le conteneur `app` est recréé (rebuild, `docker compose up -d app`) sans redémarrer `nginx`, ce dernier garde en cache l'ancienne IP de `app` et répond `502` : faire `docker compose restart nginx` après tout redéploiement de `app`.
- Stack testée de bout en bout (build, migrations, connexion MySQL, worker de queue, page `/login` en 200) avec Colima + Docker CLI en local le 2026-08-28.
