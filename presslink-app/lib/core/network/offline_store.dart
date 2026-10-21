import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Copie locale, dans le téléphone, des dernières données reçues du serveur
/// (commandes, pressings, notifications, profil) et des signalements pas
/// encore envoyés : l'appli reste consultable hors connexion.
///
/// Effacée à la connexion et à la déconnexion : un téléphone partagé ne
/// montre jamais les commandes d'un autre client.
class OfflineStore {
  static const _prefix = 'hors_connexion:';
  static const _pendingIssuesKey = '${_prefix}signalements';

  Future<void> save(String key, Object? data) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(
      '$_prefix$key',
      jsonEncode({'at': DateTime.now().toIso8601String(), 'data': data}),
    );
  }

  Future<({Object? data, DateTime savedAt})?> read(String key) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString('$_prefix$key');
    if (raw == null) return null;
    try {
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      return (
        data: decoded['data'],
        savedAt: DateTime.parse(decoded['at'] as String),
      );
    } on FormatException {
      return null;
    }
  }

  Future<List<Map<String, dynamic>>> pendingIssues() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_pendingIssuesKey);
    if (raw == null) return [];
    return (jsonDecode(raw) as List).cast<Map<String, dynamic>>();
  }

  Future<void> savePendingIssues(List<Map<String, dynamic>> issues) async {
    final prefs = await SharedPreferences.getInstance();
    if (issues.isEmpty) {
      await prefs.remove(_pendingIssuesKey);
    } else {
      await prefs.setString(_pendingIssuesKey, jsonEncode(issues));
    }
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    for (final key in prefs.getKeys().where((k) => k.startsWith(_prefix))) {
      await prefs.remove(key);
    }
  }
}
