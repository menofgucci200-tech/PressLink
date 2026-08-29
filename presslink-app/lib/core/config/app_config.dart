/// Configuration d'environnement de l'application.
///
/// Se surcharge entièrement au build avec `--dart-define`, ex :
///
///   flutter build apk \
///     --dart-define=APP_ENV=production \
///     --dart-define=API_BASE_URL=https://presslink.org/api/v1
///
/// Sans arguments, l'app démarre en développement local et pointe sur
/// `127.0.0.1` (Chrome/desktop). Pour un émulateur Android, passer
/// `--dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1` ; pour un
/// appareil physique, l'IP LAN de la machine de dev.
class AppConfig {
  const AppConfig._();

  static const String environment = String.fromEnvironment(
    'APP_ENV',
    defaultValue: 'development',
  );

  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000/api/v1',
  );

  static bool get isDevelopment => environment == 'development';
  static bool get isStaging => environment == 'staging';
  static bool get isProduction => environment == 'production';
}

/// Refuse de démarrer une build staging/production dont l'API n'est pas
/// en HTTPS, plutôt que de laisser le token et les données client
/// transiter en clair. À appeler une seule fois, tout au début de `main()`.
void assertSafeNetworkConfig() {
  final isDevBuild = AppConfig.isDevelopment;
  final usesHttps = AppConfig.apiBaseUrl.startsWith('https://');

  if (!isDevBuild && !usesHttps) {
    throw StateError(
      'Configuration réseau invalide : APP_ENV="${AppConfig.environment}" '
      'exige une API_BASE_URL en https:// (valeur actuelle : '
      '"${AppConfig.apiBaseUrl}"). Vérifiez les --dart-define de ce build.',
    );
  }
}
