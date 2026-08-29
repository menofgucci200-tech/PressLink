import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../main.dart';

/// Initialise Firebase, demande la permission de notification et
/// synchronise le token FCM de l'appareil avec le compte client connecté.
///
/// Gère aussi le deep linking : taper une notification push (bandeau,
/// verrouillage, ou relance de l'app depuis l'état "tuée") ouvre
/// directement le détail de la commande concernée — User Flows §6.
class PushNotificationService {
  PushNotificationService(this._ref);

  final Ref _ref;
  bool _listening = false;

  /// `main()` attend cet appel avant `runApp()` : une erreur Firebase non
  /// interceptée ici (ex. config manquante) empêcherait l'app entière de
  /// démarrer, pas seulement les notifications. Les push ne doivent jamais
  /// pouvoir bloquer le reste de l'application.
  Future<void> init() async {
    try {
      await Firebase.initializeApp();

      final messaging = FirebaseMessaging.instance;
      await messaging.requestPermission(alert: true, badge: true, sound: true);

      if (!_listening) {
        messaging.onTokenRefresh.listen(_registerToken);

        // App en arrière-plan, relancée au premier plan par un tap sur la notif.
        FirebaseMessaging.onMessageOpenedApp.listen(_openOrderFromMessage);

        // App totalement tuée, relancée depuis le tap sur la notif : le
        // message d'origine n'est disponible qu'une fois, ici, au démarrage.
        final initialMessage = await messaging.getInitialMessage();
        if (initialMessage != null) {
          _openOrderFromMessage(initialMessage);
        }

        _listening = true;
      }
    } catch (e) {
      debugPrint('PushNotificationService: initialisation Firebase impossible ($e)');
    }
  }

  void _openOrderFromMessage(RemoteMessage message) {
    final rawOrderId = message.data['order_id'];
    final orderId = rawOrderId is String ? int.tryParse(rawOrderId) : null;
    if (orderId == null) return;

    // getInitialMessage() peut arriver avant que le premier écran ne soit
    // construit ; on attend la frame suivante pour que le Navigator existe.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      rootNavigatorKey.currentState?.push(
        MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: orderId)),
      );
    });
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
