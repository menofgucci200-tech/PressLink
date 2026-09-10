import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../main.dart';
import '../config/app_config.dart';

/// Initialise OneSignal, demande la permission de notification et
/// synchronise le "player ID" de l'appareil avec le compte client connecté.
///
/// Gère aussi le deep linking : taper une notification push (bandeau,
/// verrouillage, ou relance de l'app depuis l'état "tuée") ouvre
/// directement le détail de la commande concernée — User Flows §6.
class PushNotificationService {
  PushNotificationService(this._ref);

  final Ref _ref;
  bool _listening = false;

  /// `main()` attend cet appel avant `runApp()` : une erreur OneSignal non
  /// interceptée ici (ex. App ID manquant) empêcherait l'app entière de
  /// démarrer, pas seulement les notifications. Les push ne doivent jamais
  /// pouvoir bloquer le reste de l'application.
  Future<void> init() async {
    if (AppConfig.oneSignalAppId.isEmpty) {
      debugPrint('PushNotificationService: ONESIGNAL_APP_ID absent — notifications désactivées.');

      return;
    }

    try {
      OneSignal.initialize(AppConfig.oneSignalAppId);
      await OneSignal.Notifications.requestPermission(true);

      if (!_listening) {
        // Le player ID n'est disponible qu'une fois l'appareil enregistré
        // auprès de OneSignal (peut arriver après ce premier appel).
        OneSignal.User.pushSubscription.addObserver((state) {
          final id = OneSignal.User.pushSubscription.id;
          if (id != null) _registerPlayerId(id);
        });

        final currentId = OneSignal.User.pushSubscription.id;
        if (currentId != null) await _registerPlayerId(currentId);

        // Tap sur la notification (app en arrière-plan, au premier plan, ou
        // relancée depuis l'état "tuée" — OneSignal unifie les trois cas
        // contrairement à Firebase Messaging qui distinguait onMessageOpenedApp
        // et getInitialMessage()).
        OneSignal.Notifications.addClickListener(_openOrderFromNotification);

        _listening = true;
      }
    } catch (e) {
      debugPrint('PushNotificationService: initialisation OneSignal impossible ($e)');
    }
  }

  void _openOrderFromNotification(OSNotificationClickEvent event) {
    final rawOrderId = event.notification.additionalData?['order_id'];
    final orderId = rawOrderId is String
        ? int.tryParse(rawOrderId)
        : rawOrderId is num
            ? rawOrderId.toInt()
            : null;
    if (orderId == null) return;

    // La commande a forcément changé côté serveur pour qu'une notification
    // soit envoyée : invalider tout de suite évite que l'écran d'accueil
    // (déjà monté sous l'écran de détail qu'on va pousser, donc jamais
    // recréé) ne continue d'afficher son ancien statut en cache une fois
    // qu'on revient dessus.
    _ref.invalidate(ordersProvider);

    WidgetsBinding.instance.addPostFrameCallback((_) {
      rootNavigatorKey.currentState
          ?.push(MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: orderId)))
          .then((_) => _ref.invalidate(ordersProvider));
    });
  }

  Future<void> syncTokenIfLoggedIn() async {
    if (_ref.read(authControllerProvider).status != AuthStatus.loggedIn) return;

    final id = OneSignal.User.pushSubscription.id;
    if (id != null) await _registerPlayerId(id);
  }

  Future<void> _registerPlayerId(String playerId) async {
    if (_ref.read(authControllerProvider).status != AuthStatus.loggedIn) return;

    try {
      await _ref.read(authRepositoryProvider).updateOnesignalPlayerId(playerId);
    } catch (e) {
      debugPrint('PushNotificationService: échec envoi du player ID OneSignal ($e)');
    }
  }
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((ref) {
  return PushNotificationService(ref);
});
