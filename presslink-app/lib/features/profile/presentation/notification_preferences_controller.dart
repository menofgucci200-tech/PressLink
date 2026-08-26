import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Préférence locale d'activation des notifications push — Profil
/// §Personnalisation (26/08). Le filtrage serveur réel viendra avec
/// l'intégration FCM complète (cf. FcmChannel côté backend).
class NotificationPreferencesController extends StateNotifier<bool> {
  NotificationPreferencesController() : super(true) {
    _restore();
  }

  static const _prefsKey = 'presslink_notifications_enabled';

  Future<void> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    state = prefs.getBool(_prefsKey) ?? true;
  }

  Future<void> setEnabled(bool enabled) async {
    state = enabled;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_prefsKey, enabled);
  }
}

final notificationPreferencesControllerProvider =
    StateNotifierProvider<NotificationPreferencesController, bool>((ref) {
  return NotificationPreferencesController();
});
