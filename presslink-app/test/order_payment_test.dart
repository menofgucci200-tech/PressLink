import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/core/theme/app_colors.dart';
import 'package:presslink_app/core/theme/app_theme.dart';
import 'package:presslink_app/core/widgets/order_card.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_controller.dart';
import 'package:presslink_app/features/orders/domain/order_repository.dart';
import 'package:presslink_app/features/orders/presentation/order_detail_screen.dart';
import 'package:presslink_app/features/orders/presentation/orders_controller.dart';
import 'package:presslink_app/shared/models/order_summary.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  final json = <String, dynamic>{
    'id': 7,
    'order_number': 'PL-000007',
    'pressing': {'id': 1, 'name': 'Pressing du Plateau'},
    'status': 'prete',
    'total_fcfa': 9000,
    'amount_paid_fcfa': 4000,
    'balance_due_fcfa': 5000,
    'payment_status': 'acompte',
    'dropped_off_at': '2026-10-12T09:00:00.000000Z',
    'items': [
      {'name': 'Costume', 'quantity': 1, 'subtotal_fcfa': 9000},
    ],
    'payments': [
      {
        'amount_fcfa': 4000,
        'method_label': 'Orange Money',
        'paid_at': '2026-10-12T09:01:00.000000Z',
      },
    ],
  };

  test('the order reads what was paid and what is left to pay', () {
    final order = OrderModel.fromJson(json);

    expect(order.amountPaidFcfa, 4000);
    expect(order.balanceDueFcfa, 5000);
    expect(order.paymentState, PaymentState.partial);
    expect(order.payments.single.methodLabel, 'Orange Money');
  });

  testWidgets('the order detail shows the deposit and the balance due', (
    tester,
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
            7,
          ).overrideWith((ref) async => OrderModel.fromJson(json)),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: const OrderDetailScreen(orderId: 7),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(find.text('Reste à payer'), 200);
    expect(find.text('Acompte versé'), findsOneWidget);
    expect(find.textContaining('Orange Money'), findsOneWidget);
    expect(find.text('5 000 F'), findsWidgets);
  });

  testWidgets('order cards tell what is left to pay', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const Scaffold(
          body: Column(
            children: [
              OrderCard(
                order: OrderSummary(
                  orderNumber: 'PL-1',
                  pressingName: 'Pressing du Plateau',
                  items: '1 Costume',
                  status: OrderStatus.prete,
                  totalFcfa: 9000,
                  balanceDueFcfa: 5000,
                ),
              ),
              OrderCard(
                order: OrderSummary(
                  orderNumber: 'PL-2',
                  pressingName: 'Pressing du Plateau',
                  items: '1 Chemise',
                  status: OrderStatus.recue,
                  totalFcfa: 1500,
                  balanceDueFcfa: 0,
                ),
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Reste 5 000 F à payer'), findsOneWidget);
    expect(find.text('Payée'), findsOneWidget);
  });
}
