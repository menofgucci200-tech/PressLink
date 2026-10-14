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
import 'package:presslink_app/features/home/presentation/home_screen.dart';
import 'package:presslink_app/features/notifications/presentation/notifications_controller.dart';
import 'package:presslink_app/features/orders/presentation/orders_controller.dart';
import 'package:presslink_app/features/pressings/presentation/pressings_controller.dart';
import 'package:presslink_app/features/profile/presentation/change_phone_screen.dart';
import 'package:presslink_app/features/profile/presentation/profile_screen.dart';

void main() {
  setUpAll(() => initializeDateFormatting('fr_FR'));

  final customer = Customer(
    id: 1,
    firstName: 'Aïcha',
    lastName: 'Koné',
    phone: '+2250708124400',
    email: 'aicha@exemple.ci',
    gender: 'femme',
    createdAt: DateTime(2026, 10, 3),
  );

  Widget app(Widget home) {
    final tokenStorage = TokenStorage(const FlutterSecureStorage());
    final repository = AuthRepository(ApiClient(tokenStorage), tokenStorage);
    return ProviderScope(
      overrides: [
        authControllerProvider.overrideWith(
          (ref) => AuthController.withState(
            repository,
            AuthState(status: AuthStatus.loggedIn, customer: customer),
          ),
        ),
        unreadNotificationsCountProvider.overrideWith((ref) async => 0),
        ordersProvider.overrideWith((ref) async => []),
        myPressingsProvider.overrideWith((ref) async => []),
      ],
      child: MaterialApp(theme: AppTheme.light, home: home),
    );
  }

  test('initials and phone are shown in a readable form', () {
    expect(customer.initials, 'AK');
    expect(
      const Customer(id: 2, firstName: '', lastName: '', phone: '').initials,
      '?',
    );
    expect(formatPhoneForDisplay('+2250708124400'), '+225 07 08 12 44 00');
    expect(formatPhoneForDisplay('0708'), '0708');
  });

  testWidgets('the profile gathers identity, appearance and security', (
    tester,
  ) async {
    await tester.pumpWidget(app(const ProfileScreen()));
    await tester.pump();

    expect(find.text('Mon profil'), findsOneWidget);
    expect(find.text('AK'), findsOneWidget);
    expect(find.text('Aïcha Koné'), findsWidgets);
    expect(find.text('+225 07 08 12 44 00'), findsWidgets);
    expect(find.text('Cliente depuis octobre 2026'), findsOneWidget);
    expect(find.text('Modifier mon profil'), findsOneWidget);
    expect(find.text('INFORMATIONS PERSONNELLES'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('APPARENCE'), 300);
    expect(find.text('Sombre'), findsOneWidget);

    await tester.scrollUntilVisible(find.text('Se déconnecter'), 300);
    expect(find.text('SÉCURITÉ'), findsOneWidget);
    expect(find.text('Mot de passe'), findsOneWidget);
  });

  testWidgets('the home screen shows the PressLink top bar', (tester) async {
    await tester.pumpWidget(app(const HomeScreen()));
    await tester.pump();

    expect(find.text('PressLink', findRichText: true), findsOneWidget);
    expect(find.byTooltip('Notifications'), findsOneWidget);
    // Accueil : pas de page où revenir, le « P » PressLink à la place.
    expect(find.byTooltip('Retour'), findsNothing);
  });

  testWidgets('the phone number can be changed from the profile', (
    tester,
  ) async {
    await tester.pumpWidget(app(const ProfileScreen()));
    await tester.pump();

    await tester.ensureVisible(find.text('Téléphone'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Téléphone'));
    await tester.pumpAndSettle();

    expect(find.byType(ChangePhoneScreen), findsOneWidget);
    expect(find.text('Numéro actuel'), findsOneWidget);
    expect(find.text('🇨🇮'), findsOneWidget);

    await tester.tap(find.widgetWithText(ElevatedButton, 'Changer de numéro'));
    await tester.pump();
    expect(find.text('Indiquez votre nouveau numéro.'), findsOneWidget);

    await tester.enterText(find.byType(TextField).first, '07 08 12 44 00');
    await tester.tap(find.widgetWithText(ElevatedButton, 'Changer de numéro'));
    await tester.pump();
    expect(find.text('C\'est déjà votre numéro actuel.'), findsOneWidget);

    await tester.enterText(find.byType(TextField).first, '05 44 33 22 11');
    await tester.tap(find.widgetWithText(ElevatedButton, 'Changer de numéro'));
    await tester.pump();
    expect(find.text('Indiquez votre mot de passe actuel.'), findsOneWidget);
  });
}
