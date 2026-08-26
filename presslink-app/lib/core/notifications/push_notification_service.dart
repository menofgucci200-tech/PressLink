import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';

/// Initialise Firebase, demande la permission de notification et
/// synchronise le token FCM de l'appareil avec le compte client connecté.
class PushNotificationService {
  PushNotificationService(this._ref);

  final Ref _ref;
  bool _listening = false;

  Future<void> init() async {
    await Firebase.initializeApp();

    final messaging = FirebaseMessaging.instance;
    await messaging.requestPermission(alert: true, badge: true, sound: true);

    if (!_listening) {
      messaging.onTokenRefresh.listen(_registerToken);
      _listening = true;
    }
  }

  Future<void> syncTokenIfLoggedIn() async {
    if (_ref.read(authControllerProvider).status != AuthStatus.loggedIn) return;

    try {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _registerToken(token);
    } catch (e) {
      debugPrint('PushNotificationService: impossible de récupérer le token FCM ($e)');
    }
  }

  Future<void> _registerToken(String token) async {
    if (_ref.read(authControllerProvider).status != AuthStatus.loggedIn) return;

    try {
      await _ref.read(authRepositoryProvider).updateFcmToken(token);
    } catch (e) {
      debugPrint('PushNotificationService: échec envoi token FCM ($e)');
    }
  }
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((ref) {
  return PushNotificationService(ref);
});
