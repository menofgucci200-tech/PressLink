import 'dart:js_interop';

@JS('removeSplashFromWeb')
external void _removeSplashFromWeb();

/// Retire l'écran de démarrage HTML (web/index.html) une fois que Flutter a
/// réellement dessiné le même logo : aucun « trou » blanc entre les deux.
void removeWebSplash() {
  try {
    _removeSplashFromWeb();
  } catch (_) {
    // Page hôte sans écran de démarrage : rien à faire.
  }
}
