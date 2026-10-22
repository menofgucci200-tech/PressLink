import 'dart:convert';

import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/network/api_error.dart';
import 'package:presslink_app/core/notifications/push_notification_service.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/core/utils/ivorian_phone.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_screen.dart';
import 'package:presslink_app/features/pressings/domain/pressing_repository.dart';
import 'package:presslink_app/features/profile/presentation/notification_preferences_controller.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Jeton gardé en mémoire (pas de stockage sécurisé en test).
class _MemoryToken extends TokenStorage {
  _MemoryToken([this.token]) : super(const FlutterSecureStorage());

  String? token;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> saveToken(String value) async => token = value;

  @override
  Future<void> clearToken() async => token = null;
}

/// Serveur simulé : chaque requête est notée, la réponse vient de [reply].
class _FakeServer implements HttpClientAdapter {
  _FakeServer(this.reply);

  final ({int status, Object body}) Function(RequestOptions options) reply;
  final List<RequestOptions> requests = [];
  bool online = true;

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
    requests.add(options);
    final answer = reply(options);
    return ResponseBody.fromString(
      jsonEncode(answer.body),
      answer.status,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}

Map<String, dynamic> _customerJson({bool emailNotifications = true}) => {
  'id': 1,
  'first_name': 'Awa',
  'last_name': 'Koné',
  'phone': '+2250708124400',
  'email': 'awa@exemple.ci',
  'gender': null,
  'email_notifications': emailNotifications,
};

class _FakePush implements PushSwitch {
  _FakePush({this.granted = true, this.accepts = true});

  bool granted;
  bool accepts;
  final List<bool> calls = [];

  @override
  bool get isAvailable => true;

  @override
  bool get permissionGranted => granted;

  @override
  Future<bool> setEnabled(bool enabled) async {
    calls.add(enabled);
    return accepts;
  }
}

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));
  setUp(() => SharedPreferences.setMockInitialValues({}));

  group('commandes au-delà de la première page', () {
    test('toutes les pages sont lues, puis gardées hors connexion', () async {
      final server = _FakeServer((options) {
        final page = options.queryParameters['page'] as int;
        expect(options.queryParameters['per_page'], ApiClient.pageSize);
        return (
          status: 200,
          body: {
            'data': [
              {'id': page * 10 + 1},
              {'id': page * 10 + 2},
            ],
            'current_page': page,
            'last_page': 3,
          },
        );
      });
      final client = ApiClient(_MemoryToken('jeton'))
        ..dio.httpClientAdapter = server;

      final body = await client.getAllPagesKeepingCopy('/orders') as Map;
      expect((body['data'] as List).map((o) => o['id']), [
        11,
        12,
        21,
        22,
        31,
        32,
      ]);
      expect(server.requests, hasLength(3));

      server.online = false;
      final copy = await client.getAllPagesKeepingCopy('/orders') as Map;
      expect(copy['data'], hasLength(6));
      expect(client.offlineDataFrom.value, isNotNull);
    });
  });

  group('session refusée par le serveur', () {
    late _MemoryToken tokens;
    late ApiClient client;
    late int status;
    late Object body;

    setUp(() {
      tokens = _MemoryToken('jeton-1');
      status = 401;
      body = {'message': 'Unauthenticated.'};
      client = ApiClient(tokens)
        ..dio.httpClientAdapter = _FakeServer(
          (_) => (status: status, body: body),
        );
    });

    test('un 401 efface la session et prévient l\'appli en français', () async {
      await client.offlineStore.save('/orders', {'data': []});
      final ended = client.sessionEnded.first;

      await expectLater(
        client.dio.get('/orders'),
        throwsA(isA<DioException>()),
      );

      expect(await ended, 'Votre session a expiré. Reconnectez-vous.');
      expect(tokens.token, isNull);
      expect(await client.offlineStore.read('/orders'), isNull);
    });

    test('le message affiché n\'est jamais « Unauthenticated. »', () async {
      try {
        await client.dio.get('/orders');
      } catch (error) {
        expect(apiErrorMessage(error), contains('session a expiré'));
      }
    });

    test('un compte désactivé (403) revient aussi à la connexion', () async {
      status = 403;
      body = {
        'message':
            'Votre compte a été désactivé. Contactez votre pressing ou le support PressLink.',
      };
      final ended = client.sessionEnded.first;
      await expectLater(
        client.dio.get('/orders'),
        throwsA(isA<DioException>()),
      );
      expect(await ended, contains('désactivé'));
      expect(tokens.token, isNull);
    });

    test(
      'un autre 403 (commande d\'un autre client) ne déconnecte pas',
      () async {
        status = 403;
        body = {'message': 'This action is unauthorized.'};
        var ended = false;
        client.sessionEnded.listen((_) => ended = true);
        await expectLater(
          client.dio.get('/orders/9'),
          throwsA(isA<DioException>()),
        );
        await Future<void>.delayed(Duration.zero);
        expect(ended, isFalse);
        expect(tokens.token, 'jeton-1');
      },
    );

    test('l\'écran revient à la connexion avec la raison', () async {
      final controller = AuthController.withState(
        AuthRepository(client, tokens),
        AuthState(
          status: AuthStatus.loggedIn,
          customer: Customer.fromJson(_customerJson()),
        ),
      );
      await expectLater(
        client.dio.get('/orders'),
        throwsA(isA<DioException>()),
      );
      await Future<void>.delayed(Duration.zero);

      expect(controller.state.status, AuthStatus.loggedOut);
      expect(controller.state.notice, contains('session a expiré'));
      controller.dispose();
    });

    test('les délais réseau sont bornés', () {
      expect(client.dio.options.connectTimeout, ApiClient.requestTimeout);
      expect(client.dio.options.receiveTimeout, ApiClient.requestTimeout);
    });
  });

  group('profil', () {
    test('le réglage « Aussi par email » vient du serveur', () {
      expect(
        Customer.fromJson(
          _customerJson(emailNotifications: false),
        ).emailNotifications,
        isFalse,
      );
    });

    test(
      'l\'interrupteur e-mail n\'envoie que ce réglage (jamais le genre)',
      () async {
        final server = _FakeServer(
          (_) => (status: 200, body: _customerJson(emailNotifications: false)),
        );
        final tokens = _MemoryToken('jeton');
        final client = ApiClient(tokens)..dio.httpClientAdapter = server;

        final customer = await AuthRepository(
          client,
          tokens,
        ).updateEmailNotifications(false);

        expect(server.requests.single.data, {'email_notifications': false});
        expect(customer.emailNotifications, isFalse);
        expect(customer.gender, isNull);
        // Copie hors connexion du profil mise à jour.
        final copy = await client.offlineStore.read('/auth/customer/me');
        expect((copy!.data as Map)['email_notifications'], isFalse);
      },
    );

    test('la déconnexion détache les notifications de ce téléphone', () async {
      final server = _FakeServer((_) => (status: 200, body: {'message': 'ok'}));
      final tokens = _MemoryToken('jeton');
      final client = ApiClient(tokens)..dio.httpClientAdapter = server;

      await AuthRepository(client, tokens).logout(playerId: 'telephone-a');

      expect(server.requests.single.path, '/auth/customer/logout');
      expect(server.requests.single.data, {'player_id': 'telephone-a'});
      expect(tokens.token, isNull);
    });
  });

  group('interrupteur « Notifications »', () {
    Future<void> settle() => Future<void>.delayed(Duration.zero);

    test('il agit vraiment sur les envois push', () async {
      final push = _FakePush();
      final controller = NotificationPreferencesController(push);
      await settle();
      expect(controller.state, isTrue);

      expect(await controller.setEnabled(false), isNull);
      expect(push.calls, [false]);
      expect(controller.state, isFalse);
    });

    test('bloqué par le téléphone : il reste éteint et le dit', () async {
      final push = _FakePush(granted: false, accepts: false);
      final controller = NotificationPreferencesController(push);
      await settle();
      expect(controller.state, isFalse);

      final error = await controller.setEnabled(true);
      expect(error, NotificationPreferencesController.blockedMessage);
      expect(controller.state, isFalse);
    });

    test('coupé depuis le profil : il reste coupé au démarrage', () async {
      SharedPreferences.setMockInitialValues({
        PushNotificationService.enabledPrefKey: false,
      });
      final controller = NotificationPreferencesController(_FakePush());
      await settle();
      expect(controller.state, isFalse);
    });
  });

  group('saisie du numéro', () {
    final formatter = IvorianPhoneInputFormatter();

    TextEditingValue type(String before, String after, int cursor) =>
        formatter.formatEditUpdate(
          TextEditingValue(text: before),
          TextEditingValue(
            text: after,
            selection: TextSelection.collapsed(offset: cursor),
          ),
        );

    test('corriger un chiffre au milieu garde le curseur en place', () {
      // « 07 08 1|2 44 00 » : on efface le 1 (le curseur reste après le
      // 8), puis on tape 9.
      final erased = type('07 08 12 44 00', '07 08 2 44 00', 6);
      expect(erased.text, '07 08 24 40 0');
      expect(erased.selection.baseOffset, 5);

      final typed = type(erased.text, '07 089 24 40 0', 6);
      expect(typed.text, '07 08 92 44 00');
      expect(typed.selection.baseOffset, 7);
    });

    test('un champ plein refuse un chiffre de plus', () {
      final value = type('07 08 12 44 00', '507 08 12 44 00', 1);
      expect(value.text, '07 08 12 44 00');
    });

    test('un numéro collé avec l\'indicatif est accepté', () {
      final value = type('', '+225 07 08 12 44 00', 19);
      expect(value.text, '07 08 12 44 00');
      expect(value.selection.baseOffset, value.text.length);
    });
  });

  test('l\'heure des notifications se lit facilement', () {
    final now = DateTime(2026, 10, 22, 12);
    expect(
      relativeTimeFr(now.subtract(const Duration(seconds: 20)), now: now),
      'À l\'instant',
    );
    expect(
      relativeTimeFr(now.subtract(const Duration(minutes: 5)), now: now),
      'Il y a 5 min',
    );
    expect(
      relativeTimeFr(now.subtract(const Duration(hours: 3)), now: now),
      'Il y a 3 h',
    );
    expect(
      relativeTimeFr(now.subtract(const Duration(days: 2)), now: now),
      'Il y a 2 j',
    );
    expect(relativeTimeFr(DateTime(2026, 9, 3), now: now), 'Le 3 septembre');
    expect(
      relativeTimeFr(DateTime(2025, 9, 3), now: now),
      'Le 3 septembre 2025',
    );
  });

  test('les initiales d\'un pressing supportent les espaces doublés', () {
    PressingModel pressing(String name) => PressingModel(
      id: 1,
      name: name,
      city: null,
      code: 'PE-1',
      phone: null,
      address: null,
    );
    expect(pressing('Pressing  Le  Plateau').initials, 'LP');
    expect(pressing('Pressing Élégance').initials, 'ÉL');
  });
}
