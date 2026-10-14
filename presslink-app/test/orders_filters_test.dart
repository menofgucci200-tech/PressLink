import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/core/theme/app_colors.dart';
import 'package:presslink_app/core/theme/app_theme.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_controller.dart';
import 'package:presslink_app/features/orders/domain/order_repository.dart';
import 'package:presslink_app/features/orders/presentation/orders_controller.dart';
import 'package:presslink_app/features/orders/presentation/orders_list_screen.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  OrderModel order(int id, OrderStatus status) => OrderModel(
    id: id,
    orderNumber: 'PL-00$id',
    pressingId: 1,
    pressingName: 'Pressing du Plateau',
    status: status,
    totalFcfa: 2000,
    droppedOffAt: DateTime(2026, 10, 1),
    items: const [],
  );

  testWidgets('each order filter shows how many orders it holds', (
    tester,
  ) async {
    final semantics = tester.ensureSemantics();
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
          ordersProvider.overrideWith(
            (ref) async => [
              order(1, OrderStatus.recue),
              order(2, OrderStatus.traitement),
              order(3, OrderStatus.prete),
              order(4, OrderStatus.recuperee),
              order(5, OrderStatus.recuperee),
              order(6, OrderStatus.annulee),
            ],
          ),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const OrdersListScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Mes commandes'), findsOneWidget);
    expect(find.bySemanticsLabel('Toutes, 6'), findsOneWidget);
    expect(find.bySemanticsLabel('En cours, 2'), findsOneWidget);
    expect(find.bySemanticsLabel('Prêtes, 1'), findsOneWidget);
    expect(find.bySemanticsLabel('Historique, 2'), findsOneWidget);

    await tester.tap(find.text('Historique'));
    await tester.pumpAndSettle();
    expect(find.text('PL-004'), findsOneWidget);
    expect(find.text('PL-001'), findsNothing);

    semantics.dispose();
  });
}
