/// Comment proposer l'installation de la PWA à l'utilisateur.
enum PwaInstallMode {
  /// Rien à proposer : app native (APK/IPA), PWA déjà installée et ouverte,
  /// ou ordinateur.
  none,

  /// Android : l'invite d'installation du navigateur est disponible, un
  /// toucher sur « Installer » suffit.
  nativePrompt,

  /// Android : l'appli est déjà installée sur ce téléphone (le navigateur
  /// ne propose donc plus de l'installer) — il suffit de l'ouvrir.
  alreadyInstalled,

  /// iPhone/iPad : aucune invite n'existe, on montre les gestes
  /// « Partager > Sur l'écran d'accueil ».
  iosInstructions,

  /// Android sans invite disponible (navigateur intégré, Samsung Internet,
  /// invite refusée récemment…) : on montre les gestes du navigateur.
  androidInstructions,
}

/// Navigateur, pour montrer le bon geste.
enum PwaBrowser { chrome, samsung, inapp, safari, other }

/// Version hors navigateur (Android/iOS natifs, tests) : jamais d'invite.
class PwaInstall {
  const PwaInstall._();

  static PwaInstallMode get mode => PwaInstallMode.none;

  static PwaBrowser get browser => PwaBrowser.other;

  static Future<bool> promptInstall() async => false;

  static void checkInstalled() {}

  static void openInChrome() {}

  static void onChange(void Function() callback) {}
}
