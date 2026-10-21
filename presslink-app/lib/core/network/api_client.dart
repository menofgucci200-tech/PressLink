import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';
import 'offline_store.dart';

class ApiClient {
  ApiClient(this._tokenStorage, {OfflineStore? offlineStore})
    : offlineStore = offlineStore ?? OfflineStore(),
      dio = Dio(BaseOptions(baseUrl: AppConfig.apiBaseUrl)) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.readToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          options.headers['Accept'] = 'application/json';
          handler.next(options);
        },
        onResponse: (response, handler) {
          // Le serveur répond : on est de nouveau en ligne.
          offlineDataFrom.value = null;
          _sendPendingIssues();
          handler.next(response);
        },
      ),
    );
  }

  final Dio dio;
  final TokenStorage _tokenStorage;
  final OfflineStore offlineStore;

  /// Date de la copie locale affichée quand le serveur est injoignable ;
  /// null en ligne. Écoutée par le bandeau « Hors connexion ».
  final ValueNotifier<DateTime?> offlineDataFrom = ValueNotifier(null);

  /// Pas de réponse du serveur (pas de réseau, coupure, délai dépassé) —
  /// par opposition à une erreur renvoyée par l'API.
  static bool isConnectionError(Object error) =>
      error is DioException &&
      error.response == null &&
      error.type != DioExceptionType.cancel &&
      error.type != DioExceptionType.badCertificate;

  /// GET qui garde une copie de la réponse ; hors connexion, renvoie la
  /// dernière copie (et le signale via [offlineDataFrom]).
  Future<dynamic> getKeepingCopy(String path) async {
    try {
      final response = await dio.get(path);
      await offlineStore.save(path, response.data);
      return response.data;
    } catch (error) {
      if (!isConnectionError(error)) rethrow;
      final copy = await offlineStore.read(path);
      if (copy == null) rethrow;
      final shown = offlineDataFrom.value;
      if (shown == null || copy.savedAt.isBefore(shown)) {
        offlineDataFrom.value = copy.savedAt;
      }
      return copy.data;
    }
  }

  /// Signalement fait hors connexion : gardé, puis envoyé dès que le
  /// serveur répond de nouveau.
  Future<void> queueIssue(Map<String, dynamic> issue) async {
    final pending = await offlineStore.pendingIssues();
    await offlineStore.savePendingIssues([...pending, issue]);
  }

  bool _sending = false;

  Future<void> _sendPendingIssues() async {
    if (_sending) return;
    _sending = true;
    try {
      final pending = await offlineStore.pendingIssues();
      if (pending.isEmpty) return;
      final left = <Map<String, dynamic>>[];
      for (final issue in pending) {
        try {
          await dio.post(
            '/orders/${issue['order_id']}/issues',
            data: {
              'category': issue['category'],
              'description': issue['description'],
            },
          );
        } catch (error) {
          // Encore hors connexion : on réessaiera. Refus de l'API
          // (commande annulée…) : on abandonne ce signalement.
          if (isConnectionError(error)) left.add(issue);
        }
      }
      await offlineStore.savePendingIssues(left);
    } finally {
      _sending = false;
    }
  }
}
