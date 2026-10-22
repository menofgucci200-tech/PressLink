import 'dart:async';
import 'dart:js_interop';

// Pont vers window.presslinkPush, défini dans web/index.html.
@JS('presslinkPush.init')
external JSPromise<JSAny?> _init(JSString appId);

@JS('presslinkPush.isSupported')
external bool _isSupported();

@JS('presslinkPush.permission')
external JSString _permission();

@JS('presslinkPush.requestPermission')
external JSPromise<JSString> _requestPermission();

@JS('presslinkPush.subscriptionId')
external JSPromise<JSString> _subscriptionId();

@JS('presslinkPush.onSubscriptionChange')
external void _onSubscriptionChange(JSFunction callback);

@JS('presslinkPush.onClick')
external void _onClick(JSFunction callback);

@JS('presslinkPush.onForeground')
external void _onForeground(JSFunction callback);

@JS('presslinkPush.setOptedIn')
external JSPromise<JSString> _setOptedIn(JSBoolean enabled);

@JS('presslinkPush.logout')
external JSPromise<JSAny?> _logout();

/// Notifications push de la PWA (SDK web OneSignal). Toute erreur JS est
/// absorbée : un bloqueur de pub ou un navigateur sans push ne doit jamais
/// empêcher l'app de fonctionner.
class WebPush {
  const WebPush._();

  static bool get isSupported {
    try {
      return _isSupported();
    } catch (_) {
      return false;
    }
  }

  /// `default`, `granted`, `denied` ou `unsupported`.
  static String get permission {
    try {
      return _permission().toDart;
    } catch (_) {
      return 'unsupported';
    }
  }

  static Future<void> init(String appId) async {
    await _init(appId.toJS).toDart;
  }

  /// Doit être appelé depuis un geste de l'utilisateur (exigé par Safari iOS).
  static Future<String> requestPermission() async {
    try {
      return (await _requestPermission().toDart.timeout(
        const Duration(seconds: 60),
      )).toDart;
    } catch (_) {
      return permission;
    }
  }

  static Future<String?> subscriptionId() async {
    try {
      final id = (await _subscriptionId().toDart.timeout(
        const Duration(seconds: 10),
      )).toDart;
      return id.isEmpty ? null : id;
    } catch (_) {
      return null;
    }
  }

  static void onSubscriptionChange(void Function(String id) callback) {
    _onSubscriptionChange(
      ((JSString id) {
        if (id.toDart.isNotEmpty) callback(id.toDart);
      }).toJS,
    );
  }

  static void onClick(void Function(int? orderId) callback) {
    _onClick(((JSString id) => callback(int.tryParse(id.toDart))).toJS);
  }

  static void onForeground(void Function() callback) {
    _onForeground(callback.toJS);
  }

  /// Interrupteur « Notifications » : OneSignal arrête (ou reprend) les
  /// envois vers ce navigateur. Renvoie l'autorisation du navigateur après
  /// coup (`granted`, `denied`…).
  static Future<String> setOptedIn(bool enabled) async {
    try {
      return (await _setOptedIn(
        enabled.toJS,
      ).toDart.timeout(const Duration(seconds: 60))).toDart;
    } catch (_) {
      return permission;
    }
  }

  /// Déconnexion : ce navigateur n'est plus rattaché au client chez OneSignal.
  static Future<void> logout() async {
    try {
      await _logout().toDart.timeout(const Duration(seconds: 5));
    } catch (_) {
      // SDK bloqué ou absent : le serveur a déjà détaché l'identifiant.
    }
  }
}
