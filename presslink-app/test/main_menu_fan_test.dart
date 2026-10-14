import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:presslink_app/core/network/api_client.dart';
import 'package:presslink_app/core/storage/token_storage.dart';
import 'package:presslink_app/core/theme/app_theme.dart';
import 'package:presslink_app/core/widgets/main_menu_fan.dart';
import 'package:presslink_app/core/widgets/main_shell.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';
import 'package:presslink_app/features/auth/presentation/auth_controller.dart';
import 'package:presslink_app/features/pressings/presentation/my_pressings_screen.dart';
import 'package:presslink_app/features/pressings/presentation/pressings_controller.dart';

void main() {
  testWidgets('the fan menu lists the entries and opens them', (tester) async {
    final tokenStorage = TokenStorage(const FlutterSecureStorage());
    final repository = AuthRepository(ApiClient(tokenStorage), tokenStorage);
    var closed = 0;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authControllerProvider.overrideWith(
            (ref) => AuthController.withState(
              repository,
              const AuthState(
                status: AuthStatus.loggedIn,
                customer: Customer(
                  id: 1,
                  firstName: 'Aïcha',
                  lastName: 'Koné',
                  phone: '+2250708124400',
                ),
              ),
            ),
          ),
          myPressingsProvider.overrideWith((ref) async => []),
        ],
        child: MaterialApp(
          theme: AppTheme.light,
          home: Scaffold(
            body: MainMenuFan(
              animation: const AlwaysStoppedAnimation(1),
              onClose: () => closed++,
            ),
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Mon profil'), findsOneWidget);
    expect(find.text('AK'), findsOneWidget);
    expect(find.text('Mes pressings'), findsOneWidget);
    expect(find.text('Signaler un problème'), findsOneWidget);

    await tester.tap(find.text('Mes pressings'));
    await tester.pumpAndSettle();

    expect(closed, 1);
    expect(find.byType(MyPressingsScreen), findsOneWidget);
  });

  testWidgets('the fan menu draws nothing while closed', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        child: MaterialApp(
          theme: AppTheme.light,
          home: Scaffold(
            body: MainMenuFan(
              animation: const AlwaysStoppedAnimation(0),
              onClose: () {},
            ),
          ),
        ),
      ),
    );

    expect(find.text('Mes pressings'), findsNothing);
  });

  testWidgets('with the menu open, the Menu slot becomes a close button', (
    tester,
  ) async {
    var menuTaps = 0;
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          bottomNavigationBar: NotchedBottomNav(
            index: 0,
            menuOpen: true,
            onTabSelected: (_) {},
            onAdd: () {},
            onMenu: () => menuTaps++,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Fermer'), findsOneWidget);
    expect(find.text('Menu'), findsNothing);

    await tester.tap(find.byIcon(Icons.close));
    await tester.pump();
    expect(menuTaps, 1);
  });
}
