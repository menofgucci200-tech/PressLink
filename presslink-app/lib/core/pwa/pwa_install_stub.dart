/// Comment proposer l'installation de la PWA à l'utilisateur.
enum PwaInstallMode {
  /// Rien à proposer : app native (APK/IPA), PWA déjà installée, ou
  /// navigateur sans installation possible.
  none,

  /// Android/Chrome : l'invite d'installation native est disponible.
  nativePrompt,

  /// iOS/Safari : aucune invite n'existe, il faut expliquer le geste
  /// « Partager > Sur l'écran d'accueil ».
  iosInstructions,
}

/// Version hors navigateur (Android/iOS natifs, tests) : jamais d'invite.
class PwaInstall {
  const PwaInstall._();

  static PwaInstallMode get mode => PwaInstallMode.none;

  static Future<bool> promptInstall() async => false;

  static void onChange(void Function() callback) {}
}
