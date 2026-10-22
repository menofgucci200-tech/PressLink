import 'dart:async';

import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../widgets/app_page_route.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/orders/presentation/order_detail_screen.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../features/notifications/presentation/notifications_controller.dart';
import '../../main.dart';
import '../config/app_config.dart';
import 'web_push.dart';

/// Interrupteur « Notifications » du profil : ce qu'il faut du service push
/// (interface séparée pour pouvoir le tester sans OneSignal).
abstract interface class PushSwitch {
  /// Notifications push possibles ici (clé OneSignal, navigateur compatible).
  bool get isAvailable;

  /// Le téléphone (ou le navigateur) autorise les notifications de l'appli.
  bool get permissionGranted;

  /// Active ou coupe réellement les envois vers ce téléphone (OneSignal
  /// optIn / optOut). Renvoie `true` si le réglage demandé est appliqué.
  Future<bool> setEnabled(bool enabled);
}

/// Initialise OneSignal, demande la permission de notification et
/// synchronise le "player ID" de l'appareil avec le compte client connecté.
///
/// Gère aussi le deep linking : taper une notification push (bandeau,
/// verrouillage, ou relance de l'app depuis l'état "tuée") ouvre
/// directement le détail de la commande concernée — User Flows §6.
class PushNotificationService implements PushSwitch {
  PushNotificationService(this._ref);

  final Ref _ref;
  bool _listening = false;

  /// Dernier identifiant push connu de ce téléphone (envoyé au serveur à la
  /// déconnexion pour qu'il le détache du compte).
  String? _playerId;

  /// Choix de l'interrupteur « Notifications » du profil, gardé dans le
  /// téléphone et réappliqué à chaque démarrage.
  static const enabledPrefKey = 'presslink_notifications_enabled';

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

    // PWA : jamais attendu. Si le SDK OneSignal ne répond pas (bloqueur de
    // publicité, réseau filtré), l'app doit démarrer quand même.
    if (kIsWeb) {
      unawaited(_initWeb());
      return;
    }

    try {
      OneSignal.initialize(AppConfig.oneSignalAppId);
      if (await _disabledByCustomer()) {
        // Coupées depuis le profil : on ne redemande rien.
        OneSignal.User.pushSubscription.optOut();
      } else {
        await OneSignal.Notifications.requestPermission(true);
      }

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

  /// PWA : SDK web OneSignal (le SDK Flutter ne gère que Android/iOS). La
  /// demande d'autorisation n'est pas faite ici : Safari iOS l'exige depuis
  /// un geste de l'utilisateur (voir [requestWebPermission]).
  Future<void> _initWeb() async {
    if (!WebPush.isSupported) return;

    try {
      await WebPush.init(
        AppConfig.oneSignalAppId,
      ).timeout(const Duration(seconds: 10));
      if (await _disabledByCustomer()) await WebPush.setOptedIn(false);

      if (!_listening) {
        WebPush.onSubscriptionChange(_registerPlayerId);
        WebPush.onClick(_openOrder);
        WebPush.onForeground(_refreshAfterNotification);
        _listening = true;
      }
    } catch (e) {
      debugPrint('PushNotificationService: initialisation web impossible ($e)');
    }
  }

  /// `true` si la PWA peut recevoir des notifications mais que le client ne
  /// les a pas encore autorisées (ni refusées).
  bool get webPermissionPending =>
      kIsWeb &&
      AppConfig.oneSignalAppId.isNotEmpty &&
      WebPush.isSupported &&
      WebPush.permission == 'default';

  /// À appeler depuis un tap (bouton « Activer les notifications »).
  Future<bool> requestWebPermission() async {
    if (!kIsWeb || AppConfig.oneSignalAppId.isEmpty) return false;

    final granted = await WebPush.requestPermission() == 'granted';
    if (granted) {
      await _saveEnabled(true);
      await syncTokenIfLoggedIn();
    }

    return granted;
  }

  Future<bool> _disabledByCustomer() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      return prefs.getBool(enabledPrefKey) == false;
    } catch (_) {
      return false;
    }
  }

  Future<void> _saveEnabled(bool enabled) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(enabledPrefKey, enabled);
  }

  @override
  bool get isAvailable =>
      AppConfig.oneSignalAppId.isNotEmpty && (!kIsWeb || WebPush.isSupported);

  @override
  bool get permissionGranted {
    if (!isAvailable) return false;
    if (kIsWeb) return WebPush.permission == 'granted';
    try {
      return OneSignal.Notifications.permission;
    } catch (_) {
      return false;
    }
  }

  @override
  Future<bool> setEnabled(bool enabled) async {
    if (!isAvailable) return false;
    try {
      if (kIsWeb) {
        // optIn demande l'autorisation du navigateur si besoin (le tap sur
        // l'interrupteur est le geste exigé par Safari iOS).
        final permission = await WebPush.setOptedIn(enabled);
        if (enabled && permission != 'granted') return false;
      } else if (enabled) {
        await OneSignal.User.pushSubscription.optIn();
        if (!OneSignal.Notifications.permission) return false;
      } else {
        await OneSignal.User.pushSubscription.optOut();
      }
    } catch (e) {
      debugPrint('PushNotificationService: réglage impossible ($e)');
      return false;
    }
    await _saveEnabled(enabled);
    if (enabled) await syncTokenIfLoggedIn();
    return true;
  }

  /// Identifiant push de ce téléphone, pour la déconnexion. N'attend jamais
  /// longtemps le SDK : la déconnexion ne doit pas traîner.
  Future<String?> currentPlayerId() async {
    if (_playerId != null || AppConfig.oneSignalAppId.isEmpty) {
      return _playerId;
    }
    try {
      return kIsWeb
          ? await WebPush.subscriptionId().timeout(const Duration(seconds: 3))
          : OneSignal.User.pushSubscription.id;
    } catch (_) {
      return null;
    }
  }

  /// Déconnexion : ce téléphone n'est plus rattaché au client chez
  /// OneSignal (le serveur, lui, a déjà oublié son identifiant).
  Future<void> unlinkDevice() async {
    if (AppConfig.oneSignalAppId.isEmpty) return;
    try {
      if (kIsWeb) {
        await WebPush.logout();
      } else {
        await OneSignal.logout();
      }
    } catch (e) {
      debugPrint('PushNotificationService: déconnexion OneSignal ($e)');
    }
  }

  void _openOrderFromNotification(OSNotificationClickEvent event) {
    final rawOrderId = event.notification.additionalData?['order_id'];
    _openOrder(
      rawOrderId is String
          ? int.tryParse(rawOrderId)
          : rawOrderId is num
          ? rawOrderId.toInt()
          : null,
    );
  }

  void _openOrder(int? orderId) {
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
    if (AppConfig.oneSignalAppId.isEmpty) return;

    final id = kIsWeb
        ? await WebPush.subscriptionId()
        : OneSignal.User.pushSubscription.id;
    if (id != null) await _registerPlayerId(id);
  }

  Future<void> _registerPlayerId(String playerId) async {
    _playerId = playerId;
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
