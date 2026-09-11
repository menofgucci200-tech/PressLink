import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../widgets/app_page_route.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../features/notifications/presentation/notifications_controller.dart';
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
      debugPrint(
        'PushNotificationService: ONESIGNAL_APP_ID absent — notifications désactivées.',
      );

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

        // Notification reçue pendant que l'app est déjà ouverte au premier
        // plan (aucun tap requis) : la liste des commandes et le badge de
        // notifications doivent se rafraîchir tout de suite, pas seulement
        // au retour d'un écran ou au prochain relancement de l'app.
        OneSignal.Notifications.addForegroundWillDisplayListener(
          (_) => _refreshAfterNotification(),
        );

        _listening = true;
      }
    } catch (e) {
      debugPrint(
        'PushNotificationService: initialisation OneSignal impossible ($e)',
      );
    }
  }

  void _openOrderFromNotification(OSNotificationClickEvent event) {
    final rawOrderId = event.notification.additionalData?['order_id'];
    final orderId = rawOrderId is String
        ? int.tryParse(rawOrderId)
        : rawOrderId is num
        ? rawOrderId.toInt()
        : null;
    _refreshAfterNotification();

    if (orderId == null) return;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      rootNavigatorKey.currentState
          ?.push(
            AppPageRoute(builder: (_) => OrderDetailScreen(orderId: orderId)),
          )
          .then((_) => _refreshAfterNotification());
    });
  }

  /// Une notification (reçue ou tapée) signale toujours qu'une commande a
  /// changé côté serveur : la liste des commandes et le badge de
  /// notifications ne doivent jamais rester affichés avec des données
  /// périmées en attendant une action manuelle de l'utilisateur.
  void _refreshAfterNotification() {
    _ref.invalidate(ordersProvider);
    _ref.invalidate(notificationsProvider);
    _ref.invalidate(unreadNotificationsCountProvider);
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
      debugPrint(
        'PushNotificationService: échec envoi du player ID OneSignal ($e)',
      );
    }
  }
}

final pushNotificationServiceProvider = Provider<PushNotificationService>((
  ref,
) {
  return PushNotificationService(ref);
});
