import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';
import 'offline_store.dart';

class ApiClient {
  ApiClient(this._tokenStorage, {OfflineStore? offlineStore})
    : offlineStore = offlineStore ?? OfflineStore(),
      dio = Dio(
        BaseOptions(
          baseUrl: AppConfig.apiBaseUrl,
          // Réseau lent qui ne coupe pas : sans limite, le chargement
          // tournerait sans fin et la copie hors connexion ne prendrait
          // jamais le relais.
          connectTimeout: requestTimeout,
          receiveTimeout: requestTimeout,
        ),
      ) {
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
        onError: (error, handler) async {
          await _endSessionIfRejected(error);
          handler.next(error);
        },
      ),
    );
  }

  static const requestTimeout = Duration(seconds: 20);

  /// Message affiché sur l'écran de connexion quand le serveur refuse la
  /// session (jeton révoqué ou expiré).
  static const sessionExpiredMessage =
      'Votre session a expiré. Reconnectez-vous.';

  final Dio dio;
  final TokenStorage _tokenStorage;
  final OfflineStore offlineStore;

  final _sessionEnded = StreamController<String>.broadcast();

  /// Session refusée par le serveur (401, ou compte désactivé) : le jeton et
  /// la copie hors connexion sont déjà effacés ; l'appli revient à l'écran
  /// de connexion avec ce message.
  Stream<String> get sessionEnded => _sessionEnded.stream;

  /// Réponse qui signifie que la session n'est plus valable : 401
  /// (« Unauthenticated. ») ou 403 du compte désactivé (le serveur a alors
  /// révoqué le jeton). Les autres 403 (commande d'un autre client…) ne
  /// déconnectent pas.
  static String? sessionRejection(DioException error) {
    final status = error.response?.statusCode;
    if (status == 401) return sessionExpiredMessage;
    if (status == 403) {
      final data = error.response?.data;
      final message = data is Map ? data['message'] : null;
      if (message is String && message.contains('désactivé')) return message;
    }
    return null;
  }

  Future<void> _endSessionIfRejected(DioException error) async {
    final message = sessionRejection(error);
    if (message == null) return;
    final sent = error.requestOptions.headers['Authorization'];
    if (sent is! String) return;
    // Réponse tardive d'une ancienne session : la session ouverte depuis
    // (autre jeton) n'est pas touchée.
    final token = await _tokenStorage.readToken();
    if (token == null || sent != 'Bearer $token') return;
    await _tokenStorage.clearToken();
    await offlineStore.clear();
    _sessionEnded.add(message);
  }

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
  Future<dynamic> getKeepingCopy(String path) => _keepingCopy(path, () async {
    final response = await dio.get(path);
    return response.data;
  });

  /// Nombre d'éléments demandés par page à [getAllPagesKeepingCopy].
  static const pageSize = 100;

  /// Comme [getKeepingCopy], pour une liste paginée par l'API (Laravel) :
  /// lit toutes les pages et renvoie `{'data': [...]}` avec tous les
  /// éléments. Une commande ancienne mais encore prête ne disparaît ainsi
  /// jamais de l'appli.
  Future<dynamic> getAllPagesKeepingCopy(String path) =>
      _keepingCopy(path, () async {
        final items = <dynamic>[];
        // Garde-fou : jamais plus de 50 pages (5 000 éléments).
        for (var page = 1; page <= 50; page++) {
          final response = await dio.get(
            path,
            queryParameters: {'page': page, 'per_page': pageSize},
          );
          final body = response.data;
          if (body is! Map) return body;
          final data = body['data'];
          if (data is! List) return body;
          items.addAll(data);
          final lastPage = (body['last_page'] as num?)?.toInt() ?? page;
          if (data.isEmpty || page >= lastPage) break;
        }
        return <String, dynamic>{'data': items};
      });

  Future<dynamic> _keepingCopy(
    String path,
    Future<dynamic> Function() load,
  ) async {
    try {
      final data = await load();
      await offlineStore.save(path, data);
      return data;
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
  /// serveur répond de nouveau. Le même signalement (même commande, même
  /// problème, même texte) n'est gardé qu'une fois : false s'il l'était déjà.
  Future<bool> queueIssue(Map<String, dynamic> issue) async {
    final pending = await offlineStore.pendingIssues();
    final alreadyQueued = pending.any(
      (p) =>
          p['order_id'] == issue['order_id'] &&
          p['category'] == issue['category'] &&
          p['description'] == issue['description'],
    );
    if (alreadyQueued) return false;
    await offlineStore.savePendingIssues([
      ...pending,
      {...issue, 'queued_at': DateTime.now().toIso8601String()},
    ]);
    return true;
  }

  /// Signalements de cette commande pas encore partis (hors connexion).
  Future<List<Map<String, dynamic>>> pendingIssuesFor(int orderId) async =>
      (await offlineStore.pendingIssues())
          .where((issue) => issue['order_id'] == orderId)
          .toList();

  /// Tente d'envoyer tout de suite les signalements en attente (avant une
  /// déconnexion) et renvoie le nombre de ceux qui n'ont pas pu partir.
  Future<int> flushPendingIssues() async {
    await _sendPendingIssues();
    return (await offlineStore.pendingIssues()).length;
  }

  Future<void>? _sending;

  Future<void> _sendPendingIssues() =>
      _sending ??= _sendPendingIssuesOnce().whenComplete(() => _sending = null);

  Future<void> _sendPendingIssuesOnce() async {
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
    // Un signalement ajouté pendant l'envoi n'est pas perdu.
    final now = await offlineStore.pendingIssues();
    await offlineStore.savePendingIssues([
      ...left,
      ...now.where((issue) => !pending.any((sent) => _sameIssue(sent, issue))),
    ]);
  }

  static bool _sameIssue(Map<String, dynamic> a, Map<String, dynamic> b) =>
      a['order_id'] == b['order_id'] &&
      a['category'] == b['category'] &&
      a['description'] == b['description'] &&
      a['queued_at'] == b['queued_at'];
}
