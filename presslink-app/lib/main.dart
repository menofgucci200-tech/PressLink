import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'core/config/app_config.dart';
import 'core/notifications/push_notification_service.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_mode_controller.dart';
import 'core/widgets/app_name_text.dart';
import 'core/widgets/main_shell.dart';
import 'features/auth/presentation/auth_controller.dart';
import 'features/auth/presentation/phone_screen.dart';

/// Clé de navigation globale — permet à [PushNotificationService] de router
/// vers le détail d'une commande depuis un tap sur une notification système,
/// en dehors de tout `BuildContext` d'écran.
final rootNavigatorKey = GlobalKey<NavigatorState>();

/// Reçoit les messages FCM quand l'app est en arrière-plan ou tuée. Doit
/// être une fonction top-level (ou statique) : Firebase la relance dans un
/// isolate dédié, sans le contexte de `main()`. Sans cet enregistrement,
/// certains messages (notamment data-only) sont silencieusement ignorés
/// par le SDK au lieu d'être remis au système.
@pragma('vm:entry-point')
Future<void> _firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  await Firebase.initializeApp();
}

void main() async {
  assertSafeNetworkConfig();
  WidgetsFlutterBinding.ensureInitialized();
  FirebaseMessaging.onBackgroundMessage(_firebaseMessagingBackgroundHandler);
  await initializeDateFormatting('fr_FR');
  final container = ProviderContainer();
  await container.read(pushNotificationServiceProvider).init();
  runApp(UncontrolledProviderScope(container: container, child: const PressLinkApp()));
}

class PressLinkApp extends ConsumerWidget {
  const PressLinkApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final themeMode = ref.watch(themeModeControllerProvider);

    return MaterialApp(
      navigatorKey: rootNavigatorKey,
      title: 'PressLink',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: themeMode,
      home: const AuthGate(),
    );
  }
}

/// Bascule Splash → Téléphone/OTP → Accueil selon l'état d'authentification.
class AuthGate extends ConsumerWidget {
  const AuthGate({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authControllerProvider);

    ref.listen(authControllerProvider, (previous, next) {
      if (next.status == AuthStatus.loggedIn && previous?.status != AuthStatus.loggedIn) {
        ref.read(pushNotificationServiceProvider).syncTokenIfLoggedIn();
      }
    });

    return switch (auth.status) {
      AuthStatus.checking => const _Splash(),
      AuthStatus.loggedOut => const PhoneScreen(),
      AuthStatus.loggedIn => const MainShell(),
    };
  }
}

class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      body: Center(child: AppNameText(fontSize: 36)),
    );
  }
}
