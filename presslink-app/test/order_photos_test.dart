import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/core/theme/app_theme.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_controller.dart';
import 'package:presslink_app/features/orders/domain/order_repository.dart';
import 'package:presslink_app/features/orders/presentation/order_detail_screen.dart';
import 'package:presslink_app/features/orders/presentation/orders_controller.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  Map<String, dynamic> json({List<Map<String, dynamic>> photos = const []}) => {
    'id': 8,
    'order_number': 'PL-000008',
    'pressing': {'id': 1, 'name': 'Pressing du Plateau'},
    'status': 'traitement',
    'total_fcfa': 2000,
    'dropped_off_at': '2026-11-06T09:00:00.000000Z',
    'items': [
      {'name': 'Chemise', 'quantity': 2, 'subtotal_fcfa': 2000},
    ],
    'photos': photos,
  };

  final photo = {
    'id': 3,
    'url': 'https://presslink.test/photos-commandes/3?signature=a',
    'thumb_url':
        'https://presslink.test/photos-commandes/3?miniature=1&signature=b',
    'taken_at': '2026-11-06T09:02:00+00:00',
  };

  test('the order reads the photos taken at drop-off', () {
    final order = OrderModel.fromJson(json(photos: [photo]));

    expect(order.photos.single.id, 3);
    expect(order.photos.single.thumbUrl, contains('miniature=1'));
    expect(OrderModel.fromJson(json()).photos, isEmpty);
  });

  Future<void> pumpDetail(
    WidgetTester tester,
    Map<String, dynamic> data,
  ) async {
    final tokenStorage = TokenStorage(const FlutterSecureStorage());
    final repository = AuthRepository(ApiClient(tokenStorage), tokenStorage);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            (ref) => AuthController.withState(
              repository,
              const AuthState(status: AuthStatus.loggedIn),
            ),
          ),
          unreadNotificationsCountProvider.overrideWith((ref) async => 0),
          orderDetailProvider(
            8,
          ).overrideWith((ref) async => OrderModel.fromJson(data)),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const OrderDetailScreen(orderId: 8),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets(
    'the order detail shows the drop-off photos and how to report a problem',
    (tester) async {
      await pumpDetail(
        tester,
        json(
          photos: [
            photo,
            {...photo, 'id': 4},
          ],
        ),
      );

      await tester.scrollUntilVisible(find.text('PHOTOS DU DÉPÔT'), 200);
      expect(find.bySemanticsLabel('Agrandir la photo 2'), findsOneWidget);
      expect(find.text('Le signaler'), findsOneWidget);
    },
  );

  testWidgets('no photo section without photos', (tester) async {
    await pumpDetail(tester, json());

    expect(find.text('PHOTOS DU DÉPÔT'), findsNothing);
  });
}
