import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Préférence d'apparence — Profil §Personnalisation (26/08).
/// Persistée localement, indépendante du compte (survit à la déconnexion).
class ThemeModeController extends StateNotifier<ThemeMode> {
  ThemeModeController() : super(ThemeMode.light) {
    _restore();
  }

  static const _prefsKey = 'presslink_theme_mode';

  Future<void> _restore() async {
    final prefs = await SharedPreferences.getInstance();
    final stored = prefs.getString(_prefsKey);
    state = switch (stored) {
      'dark' => ThemeMode.dark,
      'system' => ThemeMode.system,
      // Par défaut : clair, tant qu'aucun choix explicite n'a été fait
      // (design PressLink pensé thème clair en priorité).
      _ => ThemeMode.light,
    };
  }

  Future<void> setThemeMode(ThemeMode mode) async {
    state = mode;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, mode.name);
  }
}

final themeModeControllerProvider = StateNotifierProvider<ThemeModeController, ThemeMode>((ref) {
  return ThemeModeController();
});
