import 'package:flutter/material.dart';

/// Sur grand écran (tablette, ordinateur — typiquement la PWA ouverte dans
/// un navigateur), recentre l'app dans une colonne de largeur « téléphone »
/// au lieu de l'étirer sur toute la largeur : mêmes écrans, mêmes
/// proportions que sur mobile. Sous le seuil, ne change rien (téléphones).
class ResponsiveFrame extends StatelessWidget {
  const ResponsiveFrame({super.key, required this.child});

  final Widget child;

  static const double breakpoint = 600;
  static const double contentWidth = 480;

  @override
  Widget build(BuildContext context) {
    final media = MediaQuery.of(context);
    if (media.size.width <= breakpoint) return child;

    final isDark = Theme.of(context).brightness == Brightness.dark;

    return ColoredBox(
      color: isDark ? const Color(0xFF05080F) : const Color(0xFFE8EDF5),
      child: Center(
        child: Container(
          width: contentWidth,
          decoration: BoxDecoration(
            boxShadow: [
              BoxShadow(
                color: Colors.black.withValues(alpha: isDark ? 0.5 : 0.10),
                blurRadius: 32,
              ),
            ],
          ),
          child: ClipRect(
            child: MediaQuery(
              data: media.copyWith(size: Size(contentWidth, media.size.height)),
              child: child,
            ),
          ),
        ),
      ),
    );
  }
}
