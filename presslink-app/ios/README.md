# Flavors iOS — dev / staging / prod

Trois schemes Xcode (`dev`, `staging`, `prod`), avec un Bundle ID distinct
chacun, pour pouvoir installer dev/staging/prod en même temps sur le même
appareil. Le scheme `Runner` d'origine reste inchangé (fallback pour
`flutter run` sans `--flavor`).

| Flavor | Bundle ID | Nom affiché |
|---|---|---|
| `dev` | `com.presslink.presslinkApp.dev` | PressLink Dev |
| `staging` | `com.presslink.presslinkApp.staging` | PressLink Staging |
| `prod` | `com.presslink.presslinkApp` (inchangé) | PressLink |

## Construire une build

```bash
flutter build ios --flavor prod --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://presslink.org/api/v1
flutter build ios --flavor staging --dart-define=APP_ENV=staging --dart-define=API_BASE_URL=https://api-staging.presslink.org/api/v1
flutter build ios --flavor dev --dart-define=APP_ENV=development --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
flutter run --flavor dev --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Les trois flavors compilent dès aujourd'hui (vérifié par de vrais builds
simulateur le 2026-08-28) — **mais Firebase n'est configuré pour aucun
d'entre eux**, voir ci-dessous.

## ⚠️ Deux prérequis Firebase, avant le pilote

### 1. Configurer Firebase pour iOS (actuellement absent, même pour `prod`)

Contrairement à Android, il n'existe **aucun** `GoogleService-Info.plist` ni
`firebase_options.dart` dans ce projet : `Firebase.initializeApp()` est
appelé sans configuration. Ça compile quand même (le SDK iOS ne vérifie rien
à la compilation), mais **FCM ne fonctionnera pas au runtime sur iOS tant
que ce n'est pas fait** — c'est indépendant des flavors, un blocage
pilote à part entière.

Marche à suivre recommandée (une fois, avec le Firebase CLI) :

```bash
dart pub global activate flutterfire_cli
flutterfire configure
```

Ça génère `lib/firebase_options.dart` et demande de sélectionner les apps
iOS enregistrées dans Firebase Console pour chaque Bundle ID.

### 2. Enregistrer `dev` et `staging` comme apps Firebase distinctes

Comme pour Android : `prod` peut réutiliser l'app Firebase existante (même
Bundle ID). `dev`/`staging` ont besoin de leur propre app iOS dans Firebase
Console (Bundle ID `com.presslink.presslinkApp.dev` / `.staging`), puis de
leur propre `GoogleService-Info.plist` — à placer respectivement dans
`ios/Flutter/dev/GoogleService-Info.plist` et `ios/Flutter/staging/GoogleService-Info.plist`
(ou via `flutterfire configure` qui gère ça automatiquement si on le relance
par flavor).

## Note technique — build iOS jamais exécuté avant cette session

En marge des flavors, deux problèmes pré-existants ont été corrigés pour
qu'un build iOS soit possible du tout :
- `IPHONEOS_DEPLOYMENT_TARGET` était à 13.0 ; Firebase (SPM) exige 15.0 minimum.
- Aucun `Podfile` n'existe — ce projet utilise Swift Package Manager pour
  ses dépendances natives (pas CocoaPods), ce qui est normal pour un projet
  Flutter récent, mais explique l'absence du fichier si vous vous attendiez
  à en voir un.
