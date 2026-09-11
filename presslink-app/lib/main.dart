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

void main() async {
  assertSafeNetworkConfig();
  WidgetsFlutterBinding.ensureInitialized();
  await initializeDateFormatting('fr_FR');
  final container = ProviderContainer();
  await container.read(pushNotificationServiceProvider).init();
  runApp(
    UncontrolledProviderScope(
      container: container,
      child: const PressLinkApp(),
    ),
  );
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
      if (next.status == AuthStatus.loggedIn &&
          previous?.status != AuthStatus.loggedIn) {
        ref.read(pushNotificationServiceProvider).syncTokenIfLoggedIn();
      }
    });

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 320),
      switchInCurve: Curves.easeOut,
      switchOutCurve: Curves.easeIn,
      transitionBuilder: (child, animation) =>
          FadeTransition(opacity: animation, child: child),
      child: switch (auth.status) {
        AuthStatus.checking => const _Splash(key: ValueKey('splash')),
        AuthStatus.loggedOut => const PhoneScreen(key: ValueKey('phone')),
        AuthStatus.loggedIn => const MainShell(key: ValueKey('main')),
      },
    );
  }
}

class _Splash extends StatefulWidget {
  const _Splash({super.key});

  @override
  State<_Splash> createState() => _SplashState();
}

class _SplashState extends State<_Splash> with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Center(
        child: FadeTransition(
          opacity: Tween<double>(begin: 0.6, end: 1).animate(
            CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
          ),
          child: const AppNameText(fontSize: 36),
        ),
      ),
    );
  }
}
