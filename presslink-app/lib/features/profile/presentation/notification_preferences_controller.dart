import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../../core/notifications/push_notification_service.dart';

/// Interrupteur « Notifications » du profil — Profil §Personnalisation
/// (26/08). Il agit vraiment : coupé, OneSignal n'envoie plus rien à ce
/// téléphone (optOut) ; rallumé, les envois reprennent (optIn, avec la
/// demande d'autorisation si elle n'a jamais été donnée). Le choix est gardé
/// dans le téléphone et réappliqué au démarrage (PushNotificationService).
///
/// L'état affiché est la réalité : allumé seulement si le client le veut
/// ET que le téléphone autorise les notifications.
class NotificationPreferencesController extends StateNotifier<bool> {
  NotificationPreferencesController(this._push) : super(false) {
    _restore();
  }

  final PushSwitch _push;

  /// Message à afficher quand l'activation échoue.
  static const blockedMessage =
      'Les notifications sont bloquées sur ce téléphone. Autorisez-les '
      'dans les réglages du navigateur ou du téléphone, puis réessayez.';

  Future<void> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    final wanted =
        prefs.getBool(PushNotificationService.enabledPrefKey) ?? true;
    if (mounted) state = wanted && _push.permissionGranted;
  }

  /// Renvoie un message d'erreur, ou null si le réglage est appliqué.
  Future<String?> setEnabled(bool enabled) async {
    final previous = state;
    state = enabled;
    final applied = await _push.setEnabled(enabled);
    if (!mounted) return null;
    if (applied) return null;
    state = previous && _push.permissionGranted;
    return enabled
        ? blockedMessage
        : 'Impossible de couper les notifications. Réessayez.';
  }
}

final notificationPreferencesControllerProvider =
    StateNotifierProvider<NotificationPreferencesController, bool>((ref) {
      return NotificationPreferencesController(
        ref.watch(pushNotificationServiceProvider),
      );
    });
