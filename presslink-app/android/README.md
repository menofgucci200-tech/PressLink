# Flavors Android — dev / staging / prod

Trois flavors Gradle, avec un App ID distinct chacun, pour pouvoir installer
dev/staging/prod en même temps sur le même téléphone :

| Flavor | App ID | Nom affiché |
|---|---|---|
| `dev` | `com.presslink.presslink_app.dev` | PressLink Dev |
| `staging` | `com.presslink.presslink_app.staging` | PressLink Staging |
| `prod` | `com.presslink.presslink_app` (inchangé) | PressLink |

## Construire une build

```bash
flutter build apk --flavor prod --dart-define=APP_ENV=production --dart-define=API_BASE_URL=https://presslink.org/api/v1
flutter build apk --flavor staging --dart-define=APP_ENV=staging --dart-define=API_BASE_URL=https://api-staging.presslink.org/api/v1
flutter build apk --flavor dev --dart-define=APP_ENV=development --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
flutter run --flavor dev --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## ⚠️ `dev` et `staging` ont besoin d'une app Firebase dédiée

`prod` fonctionne immédiatement (même App ID qu'avant, même `google-services.json`).

`dev` et `staging` échoueront à la compilation tant que leurs App ID ne sont
pas enregistrés dans Firebase Console (erreur attendue et volontaire :
`No matching client found for package name '...'`, plutôt qu'un plantage
silencieux à l'exécution).

Pour débloquer chaque flavor :

1. Firebase Console → le projet PressLink → **Ajouter une application** → Android.
2. Nom du package : `com.presslink.presslink_app.dev` (ou `.staging`).
3. Télécharger le `google-services.json` généré.
4. Le placer dans `android/app/src/dev/google-services.json` (ou `src/staging/`)
   — **pas** dans `android/app/` (qui sert de config par défaut/`prod`).
5. Relancer le build du flavor concerné.
