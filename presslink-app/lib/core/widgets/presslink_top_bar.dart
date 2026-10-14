import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/notifications/presentation/notifications_controller.dart';
import '../../features/notifications/presentation/notifications_screen.dart';
import '../theme/app_colors.dart';
import 'app_page_route.dart';

/// Onglet affiché par la barre du bas (0 Accueil, 1 Commandes, 2 Support).
/// Partagé avec la barre du haut : sur un onglet autre que l'Accueil, sa
/// flèche retour y ramène.
final mainTabIndexProvider = StateProvider.autoDispose<int>((ref) => 0);

/// Barre du haut de l'application, sur toutes les pages une fois connecté :
/// flèche retour (ou le « P » PressLink sur l'Accueil), nom de la page et
/// cloche des notifications. Même dégradé bleu que la barre du bas.
class PressLinkTopBar extends ConsumerWidget implements PreferredSizeWidget {
  const PressLinkTopBar({
    super.key,
    required this.title,
    this.onBack,
    this.showNotifications = true,
  });

  /// Nom de la page, au centre de la barre.
  final String title;

  /// Retour personnalisé (ex. renvoyer un résultat) ; par défaut, page
  /// précédente, ou Accueil depuis un autre onglet.
  final VoidCallback? onBack;

  /// Masquée sur l'écran des notifications lui-même.
  final bool showNotifications;

  static const height = 60.0;

  static const gradient = LinearGradient(
    colors: [Color(0xFF1E3A8A), Color(0xFF1E40AF), AppColors.primary],
    stops: [0, 0.55, 1],
  );

  @override
  Size get preferredSize => const Size.fromHeight(height);

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final loggedIn =
        ref.watch(authControllerProvider).status == AuthStatus.loggedIn;
    // La route de l'écran lui-même : un onglet n'a pas de page en dessous.
    final canPop = ModalRoute.of(context)?.canPop ?? false;
    final tab = ref.watch(mainTabIndexProvider);

    final VoidCallback? back =
        onBack ??
        (canPop
            ? () => Navigator.of(context).maybePop()
            : (tab != 0
                  ? () => ref.read(mainTabIndexProvider.notifier).state = 0
                  : null));

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Container(
        decoration: const BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.vertical(bottom: Radius.circular(24)),
          // Ombre légère : la barre se détache du contenu sans le salir.
          boxShadow: [
            BoxShadow(
              color: Color(0x2E1E3A8A),
              blurRadius: 10,
              offset: Offset(0, 4),
              spreadRadius: -4,
            ),
          ],
        ),
        child: SafeArea(
          bottom: false,
          child: SizedBox(
            height: height,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14),
              child: Row(
                children: [
                  if (back != null)
                    _BarButton(
                      tooltip: 'Retour',
                      onTap: back,
                      child: const Icon(
                        Icons.arrow_back,
                        color: Colors.white,
                        size: 21,
                      ),
                    )
                  else
                    _BarButton(
                      child: Text(
                        'P',
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  Expanded(
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      child: Semantics(
                        header: true,
                        child: Text(
                          title,
                          textAlign: TextAlign.center,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.headlineSmall?.copyWith(
                            color: Colors.white,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                            letterSpacing: -0.2,
                          ),
                        ),
                      ),
                    ),
                  ),
                  if (showNotifications && loggedIn)
                    const _NotificationsButton()
                  else
                    // Garde le nom de l'appli parfaitement centré.
                    const SizedBox(width: _BarButton.size),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _BarButton extends StatelessWidget {
  const _BarButton({required this.child, this.onTap, this.tooltip});

  static const size = 42.0;

  final Widget child;
  final VoidCallback? onTap;
  final String? tooltip;

  @override
  Widget build(BuildContext context) {
    final box = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
      ),
      alignment: Alignment.center,
      child: child,
    );
    if (onTap == null) return ExcludeSemantics(child: box);

    return Tooltip(
      message: tooltip ?? '',
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(13),
          child: box,
        ),
      ),
    );
  }
}

class _NotificationsButton extends ConsumerWidget {
  const _NotificationsButton();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final unread = ref.watch(unreadNotificationsCountProvider).valueOrNull ?? 0;

    return Semantics(
      button: true,
      label: unread > 0
          ? 'Notifications, $unread non lue${unread > 1 ? 's' : ''}'
          : 'Notifications',
      excludeSemantics: true,
      child: _BarButton(
        tooltip: 'Notifications',
        onTap: () => Navigator.of(
          context,
        ).push(AppPageRoute(builder: (_) => const NotificationsScreen())),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            const Icon(
              Icons.notifications_outlined,
              color: Colors.white,
              size: 22,
            ),
            Positioned(
              top: -9,
              right: -11,
              child: TweenAnimationBuilder<double>(
                key: ValueKey(unread > 0),
                tween: Tween(begin: 0, end: unread > 0 ? 1.0 : 0.0),
                duration: const Duration(milliseconds: 220),
                curve: Curves.easeOutBack,
                builder: (context, scale, child) =>
                    Transform.scale(scale: scale, child: child),
                child: Container(
                  height: 18,
                  constraints: const BoxConstraints(minWidth: 18),
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  decoration: BoxDecoration(
                    color: AppColors.error,
                    borderRadius: BorderRadius.circular(9),
                    border: Border.all(color: AppColors.primary, width: 2),
                  ),
                  alignment: Alignment.center,
                  child: Text(
                    unread > 99 ? '99+' : '$unread',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      height: 1,
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
