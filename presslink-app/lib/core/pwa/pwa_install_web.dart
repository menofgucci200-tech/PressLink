import 'dart:js_interop';

import 'pwa_install_stub.dart' show PwaBrowser, PwaInstallMode;

export 'pwa_install_stub.dart' show PwaBrowser, PwaInstallMode;

// Pont vers window.presslinkPwa, défini dans web/index.html.
@JS('presslinkPwa.isStandalone')
external bool _isStandalone();

@JS('presslinkPwa.isIos')
external bool _isIos();

@JS('presslinkPwa.isAndroid')
external bool _isAndroid();

@JS('presslinkPwa.browser')
external JSString _browser();

@JS('presslinkPwa.canInstall')
external bool _canInstall();

@JS('presslinkPwa.isInstalled')
external bool _isInstalled();

@JS('presslinkPwa.checkInstalled')
external JSPromise<JSBoolean> _checkInstalled();

@JS('presslinkPwa.openInChrome')
external void _openInChrome();

@JS('presslinkPwa.promptInstall')
external JSPromise<JSString> _promptInstall();

@JS('presslinkPwa.onChange')
external void _onChange(JSFunction callback);

class PwaInstall {
  const PwaInstall._();

  static PwaInstallMode get mode {
    try {
      if (_isStandalone()) return PwaInstallMode.none;
      if (_canInstall()) return PwaInstallMode.nativePrompt;
      if (_isAndroid() && _isInstalled()) {
        return PwaInstallMode.alreadyInstalled;
      }
      if (_isIos()) return PwaInstallMode.iosInstructions;
      if (_isAndroid()) return PwaInstallMode.androidInstructions;
    } catch (_) {
      // window.presslinkPwa absent (page hôte différente) : rien à proposer.
    }
    return PwaInstallMode.none;
  }

  static PwaBrowser get browser {
    try {
      return PwaBrowser.values.firstWhere(
        (b) => b.name == _browser().toDart,
        orElse: () => PwaBrowser.other,
      );
    } catch (_) {
      return PwaBrowser.other;
    }
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

  /// Vérifie (Android/Chrome) si l'appli est déjà installée ; [onChange] est
  /// appelé quand la réponse arrive.
  static void checkInstalled() {
    try {
      _checkInstalled();
    } catch (_) {}
  }

  static void openInChrome() {
    try {
      _openInChrome();
    } catch (_) {}
  }

  /// Appelé quand l'invite devient disponible ou que l'app vient d'être
  /// installée — pour rafraîchir l'affichage de la bannière.
  static void onChange(void Function() callback) {
    try {
      _onChange(callback.toJS);
    } catch (_) {}
  }
}
