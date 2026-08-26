# PressLink

Le lien entre votre pressing et vos clients.

PressLink est une plateforme SaaS qui permet aux pressings de digitaliser la gestion de leurs commandes et à leurs clients de suivre leurs vêtements en temps réel.

## Structure du dépôt

- **`presslink-api/`** — Backend Laravel (API REST + dashboard pressing en Livewire) + MySQL.
- **`presslink-app/`** — Application client Flutter (Android/iOS).
- **`DEVELOPMENT_PLAN.md`** — Plan de développement par phases.

## Démarrage rapide

### Backend (`presslink-api/`)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

### App client (`presslink-app/`)

```bash
flutter pub get
flutter run
```

L'app pointe par défaut sur `http://127.0.0.1:8000` (voir `lib/core/network/api_client.dart`, surchargeable via `--dart-define=API_HOST=<ip>`).
