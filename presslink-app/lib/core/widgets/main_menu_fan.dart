import 'dart:math' as math;
import 'dart:ui' show ImageFilter, PathMetric;

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../features/auth/presentation/auth_controller.dart';
import '../../features/orders/presentation/select_order_for_issue_screen.dart';
import '../../features/pressings/presentation/my_pressings_screen.dart';
import '../../features/profile/presentation/customer_avatar.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../theme/app_colors.dart';
import 'app_page_route.dart';
import 'pwa_install_banner.dart';

/// Voile posé sur l'écran quand le menu est ouvert (repris par la barre de
/// navigation au-dessus de ses encoches, pour un fond uniforme).
Color menuDimColor(bool isDark) =>
    isDark ? const Color(0xA602060F) : const Color(0x800F172A);

/// Menu « bulles en éventail » : ouvert depuis l'emplacement Menu de la
/// barre, ses entrées en sortent en arc de cercle sur un fond flouté.
/// `animation` va de 0 (fermé) à 1 (ouvert) ; toucher le fond referme.
class MainMenuFan extends ConsumerWidget {
  const MainMenuFan({
    super.key,
    required this.animation,
    required this.onClose,
  });

  final Animation<double> animation;
  final VoidCallback onClose;

  static const bubbleSize = 60.0;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final customer = ref.watch(authControllerProvider).customer;

    void open(Widget screen) {
      onClose();
      Navigator.of(context).push(AppPageRoute(builder: (_) => screen));
    }

    final entries = [
      _FanEntry(
        label: 'Mon profil',
        bubble: CustomerAvatar(customer: customer, radius: bubbleSize / 2),
        onTap: () => open(const ProfileScreen()),
      ),
      _FanEntry(
        label: 'Mes pressings',
        icon: Icons.storefront_outlined,
        onTap: () => open(const MyPressingsScreen()),
      ),
      _FanEntry(
        label: 'Signaler un problème',
        icon: Icons.flag_outlined,
        danger: true,
        onTap: () => open(const SelectOrderForIssueScreen()),
      ),
      if (PwaInstallBanner.isInstallable)
        _FanEntry(
          label: 'Installer l\'app',
          icon: Icons.install_mobile_outlined,
          onTap: () {
            onClose();
            showPwaInstallSheet(context);
          },
        ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        // Centre de l'éventail : au-dessus de la bulle « Menu » (dernier des 5
        // emplacements), au ras de la barre.
        final anchor = Offset(
          constraints.maxWidth * 0.9,
          constraints.maxHeight,
        );
        final radius = math.min(250.0, constraints.maxWidth * 0.62);
        // 162° au plus : la dernière bulle et son libellé restent au-dessus de
        // la barre.
        const firstAngle = 95.0, lastAngle = 162.0;

        return AnimatedBuilder(
          animation: animation,
          builder: (context, _) {
            final t = animation.value;
            if (t == 0) return const SizedBox.shrink();

            return Stack(
              clipBehavior: Clip.none,
              children: [
                Positioned.fill(
                  child: GestureDetector(
                    onTap: onClose,
                    behavior: HitTestBehavior.opaque,
                    // ClipRect : le flou reste sur la page, sans déborder sur
                    // la barre du haut.
                    child: ClipRect(
                      child: BackdropFilter(
                        filter: ImageFilter.blur(sigmaX: 6 * t, sigmaY: 6 * t),
                        child: ColoredBox(
                          color: menuDimColor(
                            isDark,
                          ).withValues(alpha: menuDimColor(isDark).a * t),
                        ),
                      ),
                    ),
                  ),
                ),
                Positioned.fill(
                  child: IgnorePointer(
                    child: CustomPaint(
                      painter: _DashedArcPainter(
                        center: anchor,
                        radius: radius,
                        opacity: t,
                      ),
                    ),
                  ),
                ),
                // Bulles d'abord, libellés ensuite : une bulle ne cache
                // jamais le libellé d'une autre (petits écrans).
                for (final layer in [_Layer.bubble, _Layer.label])
                  for (var i = 0; i < entries.length; i++)
                    _buildEntry(
                      entries[i],
                      layer: layer,
                      index: i,
                      t: t,
                      anchor: anchor,
                      target: _pointOnArc(
                        anchor,
                        radius,
                        entries.length == 1
                            ? firstAngle
                            : firstAngle +
                                  i *
                                      (lastAngle - firstAngle) /
                                      (entries.length - 1),
                      ),
                      screenWidth: constraints.maxWidth,
                      isDark: isDark,
                    ),
              ],
            );
          },
        );
      },
    );
  }

  static Offset _pointOnArc(Offset center, double radius, double degrees) {
    final a = degrees * math.pi / 180;
    return Offset(
      center.dx + radius * math.cos(a),
      center.dy - radius * math.sin(a),
    );
  }

  /// Largeur réservée à un libellé : « Signaler un problème » y tient.
  static const _labelWidth = 170.0;

  Widget _buildEntry(
    _FanEntry entry, {
    required _Layer layer,
    required int index,
    required double t,
    required Offset anchor,
    required Offset target,
    required double screenWidth,
    required bool isDark,
  }) {
    // Les bulles sortent l'une après l'autre (léger décalage) avec un petit
    // rebond en fin de course ; à la fermeture, elles rentrent dans le bouton.
    final start = index * 0.08;
    final progress = Interval(
      start,
      math.min(1, 0.62 + start),
      curve: Curves.easeOutBack,
    ).transform(t);
    final position = Offset.lerp(anchor, target, progress)!;
    final opacity = (progress * 1.6).clamp(0.0, 1.0);
    final scale = 0.4 + 0.6 * progress.clamp(0.0, 1.1);

    if (layer == _Layer.bubble) {
      return Positioned(
        left: position.dx - bubbleSize / 2,
        top: position.dy - bubbleSize / 2,
        width: bubbleSize,
        height: bubbleSize,
        child: Opacity(
          opacity: opacity,
          child: Transform.scale(
            scale: scale,
            child: Semantics(
              button: true,
              label: entry.label,
              excludeSemantics: true,
              child: GestureDetector(
                onTap: entry.onTap,
                behavior: HitTestBehavior.opaque,
                child: Container(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: isDark ? const Color(0xFF16233D) : Colors.white,
                    border: isDark
                        ? Border.all(color: const Color(0xFF2D3F5F))
                        : null,
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.45),
                        blurRadius: 22,
                        offset: const Offset(0, 12),
                        spreadRadius: -10,
                      ),
                    ],
                  ),
                  alignment: Alignment.center,
                  child:
                      entry.bubble ??
                      Icon(
                        entry.icon,
                        size: 26,
                        color: entry.danger
                            ? AppColors.error
                            : (isDark
                                  ? const Color(0xFF93C5FD)
                                  : AppColors.primary),
                      ),
                ),
              ),
            ),
          ),
        ),
      );
    }

    // Libellé centré sous sa bulle, mais toujours entier à l'écran : près
    // d'un bord, il glisse vers l'intérieur au lieu d'être coupé.
    const margin = 4.0;
    final maxLeft = math.max(margin, screenWidth - _labelWidth - margin);
    final left = (position.dx - _labelWidth / 2).clamp(margin, maxLeft);
    final alignX = ((position.dx - left) / _labelWidth * 2 - 1).clamp(
      -1.0,
      1.0,
    );

    return Positioned(
      left: left,
      top: position.dy + bubbleSize / 2 * scale + 7,
      width: _labelWidth,
      child: Opacity(
        opacity: opacity,
        child: ExcludeSemantics(
          child: Align(
            alignment: Alignment(alignX, 0),
            child: GestureDetector(
              onTap: entry.onTap,
              behavior: HitTestBehavior.opaque,
              child: Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: const Color(0xB30F172A),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  entry.label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

enum _Layer { bubble, label }

class _FanEntry {
  const _FanEntry({
    required this.label,
    required this.onTap,
    this.icon,
    this.bubble,
    this.danger = false,
  });

  final String label;
  final VoidCallback onTap;
  final IconData? icon;

  /// Contenu de la bulle à la place de l'icône (photo de profil).
  final Widget? bubble;
  final bool danger;
}

/// Arc pointillé discret qui relie les bulles au bouton Menu.
class _DashedArcPainter extends CustomPainter {
  const _DashedArcPainter({
    required this.center,
    required this.radius,
    required this.opacity,
  });

  final Offset center;
  final double radius;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final arc = Path()
      ..addArc(
        Rect.fromCircle(center: center, radius: radius),
        -math.pi / 2,
        -math.pi / 2,
      );
    final paint = Paint()
      ..color = Colors.white.withValues(alpha: 0.22 * opacity)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 2
      ..strokeCap = StrokeCap.round;

    for (final PathMetric metric in arc.computeMetrics()) {
      for (var d = 0.0; d < metric.length; d += 10) {
        canvas.drawPath(metric.extractPath(d, d + 3), paint);
      }
    }
  }

  @override
  bool shouldRepaint(_DashedArcPainter oldDelegate) =>
      oldDelegate.center != center ||
      oldDelegate.radius != radius ||
      oldDelegate.opacity != opacity;
}
