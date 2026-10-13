import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'core/config/app_config.dart';
import 'core/notifications/push_notification_service.dart';
import 'core/pwa/web_splash.dart';
import 'core/theme/app_theme.dart';
import 'core/theme/theme_mode_controller.dart';
import 'core/widgets/main_shell.dart';
import 'core/widgets/responsive_frame.dart';
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
      builder: (context, child) =>
          ResponsiveFrame(child: child ?? const SizedBox.shrink()),
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

    // Transitions courtes et légères (simple fondu) : l'ouverture doit
    // paraître instantanée, y compris sur les téléphones d'entrée de gamme.
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 220),
      reverseDuration: const Duration(milliseconds: 160),
      switchInCurve: Curves.easeOut,
      switchOutCurve: Curves.easeIn,
      child: switch (auth.status) {
        AuthStatus.checking => const _Splash(key: ValueKey('splash')),
        AuthStatus.loggedOut => const PhoneScreen(key: ValueKey('phone')),
        AuthStatus.loggedIn => const MainShell(key: ValueKey('main')),
      },
    );
  }
}

/// Écran de démarrage Flutter : même logo, même taille et même position que
/// celui de web/index.html, sans animation d'entrée. Dès que le logo est
/// dessiné, le calque HTML est retiré : le relais est invisible. La barre de
/// chargement n'apparaît que si la vérification de session traîne.
class _Splash extends StatefulWidget {
  const _Splash({super.key});

  @override
  State<_Splash> createState() => _SplashState();
}

class _SplashState extends State<_Splash> with SingleTickerProviderStateMixin {
  late final _bar = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1000),
  )..repeat();
  bool _showBar = false;
  bool _logoShown = false;

  @override
  void initState() {
    super.initState();
    Future<void>.delayed(const Duration(milliseconds: 800), () {
      if (mounted) setState(() => _showBar = true);
    });
  }

  @override
  void dispose() {
    _bar.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image.asset(
              'assets/branding/logo_wordmark.png',
              width: 220,
              filterQuality: FilterQuality.medium,
              gaplessPlayback: true,
              frameBuilder: (context, child, frame, wasSynchronouslyLoaded) {
                if (frame != null && !_logoShown) {
                  _logoShown = true;
                  WidgetsBinding.instance.addPostFrameCallback(
                    (_) => removeWebSplash(),
                  );
                }
                return child;
              },
            ),
            const SizedBox(height: 28),
            AnimatedOpacity(
              opacity: _showBar ? 1 : 0,
              duration: const Duration(milliseconds: 200),
              child: _LoadingBar(animation: _bar),
            ),
          ],
        ),
      ),
    );
  }
}

class _LoadingBar extends StatelessWidget {
  const _LoadingBar({required this.animation});

  final Animation<double> animation;

  static const _width = 120.0;
  static const _curve = Cubic(0.65, 0, 0.35, 1);

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(3),
      child: Container(
        width: _width,
        height: 3,
        color: const Color(0xFFE2E8F0),
        child: AnimatedBuilder(
          animation: animation,
          builder: (context, _) {
            final t = _curve.transform(animation.value);
            return Stack(
              children: [
                Positioned(
                  left: (-0.4 + 1.4 * t) * _width,
                  top: 0,
                  bottom: 0,
                  width: _width * 0.4,
                  child: const DecoratedBox(
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.all(Radius.circular(3)),
                      gradient: LinearGradient(
                        colors: [Color(0xFF2563EB), Color(0xFF06B6D4)],
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
