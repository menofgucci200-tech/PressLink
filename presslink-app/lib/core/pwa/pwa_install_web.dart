import 'dart:js_interop';

// Pont vers window.presslinkPwa, défini dans web/index.html.
@JS('presslinkPwa.isStandalone')
external bool _isStandalone();

@JS('presslinkPwa.isIosSafari')
external bool _isIosSafari();

@JS('presslinkPwa.canInstall')
external bool _canInstall();

@JS('presslinkPwa.promptInstall')
external JSPromise<JSString> _promptInstall();

@JS('presslinkPwa.onChange')
external void _onChange(JSFunction callback);

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

class PwaInstall {
  const PwaInstall._();

  static PwaInstallMode get mode {
    try {
      if (_isStandalone()) return PwaInstallMode.none;
      if (_canInstall()) return PwaInstallMode.nativePrompt;
      if (_isIosSafari()) return PwaInstallMode.iosInstructions;
    } catch (_) {
      // window.presslinkPwa absent (page hôte différente) : rien à proposer.
    }
    return PwaInstallMode.none;
  }

  /// Ouvre l'invite d'installation native. `true` si l'utilisateur accepte.
  static Future<bool> promptInstall() async {
    try {
      final outcome = (await _promptInstall().toDart).toDart;
      return outcome == 'accepted';
    } catch (_) {
      return false;
    }
  }

  /// Appelé quand l'invite devient disponible ou que l'app vient d'être
  /// installée — pour rafraîchir l'affichage de la bannière.
  static void onChange(void Function() callback) {
    try {
      _onChange(callback.toJS);
    } catch (_) {}
  }
}
