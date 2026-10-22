import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _NoToken extends TokenStorage {
  _NoToken() : super(const FlutterSecureStorage());

  @override
  Future<String?> readToken() async => null;
}

/// Serveur simulé : répond, ou fait comme si le réseau était coupé.
class _FakeServer implements HttpClientAdapter {
  bool online = true;
  final List<String> posts = [];

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
    if (options.method == 'POST') posts.add(options.path);
    return ResponseBody.fromString(
      jsonEncode({
        'data': [
          {'id': 1},
        ],
      }),
      200,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  late ApiClient client;
  late _FakeServer server;

  setUp(() {
    SharedPreferences.setMockInitialValues({});
    server = _FakeServer();
    client = ApiClient(_NoToken())..dio.httpClientAdapter = server;
  });

  test('hors connexion, la dernière copie est affichée et signalée', () async {
    await client.getKeepingCopy('/orders');
    expect(client.offlineDataFrom.value, isNull);

    server.online = false;
    final copy = await client.getKeepingCopy('/orders') as Map;

    expect(copy['data'], [
      {'id': 1},
    ]);
    expect(client.offlineDataFrom.value, isNotNull);

    server.online = true;
    await client.getKeepingCopy('/orders');
    expect(client.offlineDataFrom.value, isNull);
  });

  test('sans copie, l\'erreur de connexion remonte', () async {
    server.online = false;
    expect(
      () => client.getKeepingCopy('/orders/7'),
      throwsA(isA<DioException>()),
    );
  });

  test(
    'un signalement gardé hors connexion part au retour du réseau',
    () async {
      await client.queueIssue({
        'order_id': 7,
        'category': 'tache',
        'description': null,
      });
      expect(await client.offlineStore.pendingIssues(), hasLength(1));

      await client.getKeepingCopy('/orders');
      await Future<void>.delayed(const Duration(milliseconds: 50));

      expect(server.posts, ['/orders/7/issues']);
      expect(await client.offlineStore.pendingIssues(), isEmpty);
    },
  );

  test('la déconnexion efface la copie locale', () async {
    await client.getKeepingCopy('/orders');
    await client.offlineStore.clear();
    server.online = false;
    expect(
      () => client.getKeepingCopy('/orders'),
      throwsA(isA<DioException>()),
    );
  });
}
