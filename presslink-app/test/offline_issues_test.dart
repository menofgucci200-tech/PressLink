import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/features/orders/domain/order_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _MemoryToken extends TokenStorage {
  _MemoryToken() : super(const FlutterSecureStorage());

  @override
  Future<String?> readToken() async => 'jeton';
}

/// Serveur simulé : hors connexion tant que [online] est faux.
class _FakeServer implements HttpClientAdapter {
  bool online = false;
  final List<RequestOptions> issuesSent = [];

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    if (!online) {
      throw DioException.connectionError(
        requestOptions: options,
        reason: 'hors connexion',
      );
    }
    if (options.path.endsWith('/issues')) issuesSent.add(options);
    return ResponseBody.fromString(
      jsonEncode({'data': {}}),
      201,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  setUp(() => SharedPreferences.setMockInitialValues({}));

  test('un signalement hors connexion est visible, gardé une seule fois, '
      'puis envoyé avant la déconnexion', () async {
    final server = _FakeServer();
    final client = ApiClient(_MemoryToken())..dio.httpClientAdapter = server;
    final orders = OrderRepository(client);

    Future<IssueReport> report() => orders.reportIssue(
      orderId: 7,
      category: IssueCategory.missingItem,
      description: 'Une chemise blanche',
    );

    expect(await report(), IssueReport.queued);
    expect(await report(), IssueReport.alreadyQueued);

    final pending = await orders.pendingIssues(7);
    expect(pending, hasLength(1));
    expect(pending.single.category, IssueCategory.missingItem);
    expect(await orders.pendingIssues(8), isEmpty);

    // Toujours hors connexion : la déconnexion préviendra qu'il reste 1 envoi.
    expect(await client.flushPendingIssues(), 1);

    server.online = true;
    expect(await client.flushPendingIssues(), 0);
    expect(server.issuesSent, hasLength(1));
    expect(await orders.pendingIssues(7), isEmpty);
  });
}
