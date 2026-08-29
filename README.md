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

L'app pointe par défaut sur `http://127.0.0.1:8000/api/v1` (voir `lib/core/config/app_config.dart`). Configuration par environnement via `--dart-define` :

| Environnement | Commande |
|---|---|
| Dev (Chrome/desktop) | `flutter run` |
| Dev (émulateur Android) | `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` |
| Dev (appareil physique) | `flutter run --dart-define=API_BASE_URL=http://<ip-lan>:8000/api/v1` |
| Staging | `flutter build apk --dart-define=APP_ENV=staging --dart-define=API_BASE_URL=https://api-staging.presslink.org/api/v1` |
| Production | `flutter build apk --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://api.presslink.org/api/v1` |

En staging/production, l'app refuse de démarrer si `API_BASE_URL` n'est pas en `https://` (voir `assertSafeNetworkConfig()`), pour éviter de livrer par erreur une build qui parle en clair.

#### Flavors dev / staging / prod (Android & iOS)

Trois flavors natifs, App ID/Bundle ID distincts, permettent d'installer dev/staging/prod en même temps sur un même appareil — voir [`android/README.md`](presslink-app/android/README.md) et [`ios/README.md`](presslink-app/ios/README.md) pour les commandes de build complètes et les prérequis Firebase par flavor.
