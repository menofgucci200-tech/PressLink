import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/home/presentation/home_screen.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_controller.dart';
import 'package:presslink_app/features/orders/presentation/orders_controller.dart';
import 'package:presslink_app/features/pressings/presentation/pressings_controller.dart';

void main() {
  testWidgets('Home screen renders greeting and key sections', (tester) async {
    final tokenStorage = TokenStorage(const FlutterSecureStorage());
    final repository = AuthRepository(ApiClient(tokenStorage), tokenStorage);
    const customer = Customer(id: 1, firstName: 'Stéphane', lastName: 'SAY', phone: '+2250700000000');

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            (ref) => AuthController.withState(repository, const AuthState(status: AuthStatus.loggedIn, customer: customer)),
          ),
          unreadNotificationsCountProvider.overrideWith((ref) async => 0),
          ordersProvider.overrideWith((ref) async => []),
          myPressingsProvider.overrideWith((ref) async => []),
        ],
        child: const MaterialApp(home: HomeScreen()),
      ),
    );
    await tester.pump();

    expect(find.text('Bonjour Stéphane'), findsOneWidget);
    expect(find.text('MES PRESSINGS'), findsOneWidget);
    expect(find.text('Ajouter un pressing'), findsOneWidget);
  });
}
