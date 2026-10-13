import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/date_symbol_data_local.dart';

import 'core/config/app_config.dart';
import 'core/notifications/push_notification_service.dart';
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

    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 560),
      reverseDuration: const Duration(milliseconds: 420),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeInCubic,
      transitionBuilder: (child, animation) {
        // Sortie de l'écran de démarrage : le logo s'efface en grandissant
        // légèrement, comme s'il s'ouvrait sur l'application.
        if (child.key == const ValueKey('splash')) {
          return FadeTransition(
            opacity: animation,
            child: ScaleTransition(
              scale: Tween<double>(begin: 1.08, end: 1).animate(animation),
              child: child,
            ),
          );
        }
        // Entrée de l'écran suivant : fondu + léger glissement vers le haut.
        return FadeTransition(
          opacity: animation,
          child: SlideTransition(
            position: Tween<Offset>(
              begin: const Offset(0, 0.025),
              end: Offset.zero,
            ).animate(animation),
            child: child,
          ),
        );
      },
      child: switch (auth.status) {
        AuthStatus.checking => const _Splash(key: ValueKey('splash')),
        AuthStatus.loggedOut => const PhoneScreen(key: ValueKey('phone')),
        AuthStatus.loggedIn => const MainShell(key: ValueKey('main')),
      },
    );
  }
}

/// Écran de démarrage Flutter : reproduit à l'identique celui de
/// web/index.html (même logo, même taille, même position, même halo et barre
/// de chargement) pour que le relais HTML → Flutter soit invisible. Sur
/// l'APK (pas de splash HTML), le logo fait lui-même son entrée animée.
class _Splash extends StatefulWidget {
  const _Splash({super.key});

  @override
  State<_Splash> createState() => _SplashState();
}

class _SplashState extends State<_Splash> with TickerProviderStateMixin {
  late final _intro = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 800),
    value: kIsWeb ? 1 : 0,
  )..forward();
  late final _breath = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1200),
  )..repeat(reverse: true);
  late final _bar = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat();

  @override
  void dispose() {
    _intro.dispose();
    _breath.dispose();
    _bar.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final intro = CurvedAnimation(
      parent: _intro,
      curve: const Cubic(0.2, 0.8, 0.2, 1),
    );
    final breath = CurvedAnimation(parent: _breath, curve: Curves.easeInOut);

    return Scaffold(
      backgroundColor: Colors.white,
      body: Center(
        child: Stack(
          alignment: Alignment.center,
          clipBehavior: Clip.none,
          children: [
            OverflowBox(
              maxWidth: 420,
              maxHeight: 420,
              child: FadeTransition(
                opacity: Tween<double>(begin: 0.7, end: 1).animate(breath),
                child: ScaleTransition(
                  scale: Tween<double>(begin: 0.9, end: 1.05).animate(breath),
                  child: Container(
                    width: 420,
                    height: 420,
                    decoration: const BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: RadialGradient(
                        colors: [Color(0x1C2563EB), Color(0x002563EB)],
                        stops: [0, 0.65],
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                FadeTransition(
                  opacity: intro,
                  child: SlideTransition(
                    position: Tween<Offset>(
                      begin: const Offset(0, 0.18),
                      end: Offset.zero,
                    ).animate(intro),
                    child: ScaleTransition(
                      scale: Tween<double>(begin: 0.94, end: 1).animate(intro),
                      child: Image.asset(
                        'assets/branding/logo_wordmark.png',
                        width: 220,
                        gaplessPlayback: true,
                      ),
                    ),
                  ),
                ),
                const SizedBox(height: 28),
                _LoadingBar(animation: _bar),
              ],
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
