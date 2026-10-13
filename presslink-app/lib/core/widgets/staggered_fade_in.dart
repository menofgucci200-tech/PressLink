import 'package:flutter/material.dart';

/// Fait apparaître un élément en fondu + léger glissement vers le haut, avec
/// un délai proportionnel à son index — effet de cascade à l'ouverture d'une
/// liste ou d'un écran au lieu d'un bloc qui s'affiche d'un coup. Le layout
/// garde sa place dès le départ (seules l'opacité et la position animent)
/// pour ne jamais faire sauter la page pendant la cascade.
class StaggeredFadeIn extends StatefulWidget {
  const StaggeredFadeIn({
    super.key,
    required this.index,
    required this.child,
    this.stepMs = 20,
    this.durationMs = 220,
    this.slide = 0.04,
    this.initialDelayMs = 0,
  });

  final int index;
  final Widget child;

  /// Décalage entre deux éléments consécutifs.
  final int stepMs;
  final int durationMs;

  /// Glissement initial, en fraction de la hauteur de l'élément.
  final double slide;

  /// Délai avant le premier élément (ex. laisser finir une transition de page).
  final int initialDelayMs;

  @override
  State<StaggeredFadeIn> createState() => _StaggeredFadeInState();
}

class _StaggeredFadeInState extends State<StaggeredFadeIn> {
  bool _visible = false;

  @override
  void initState() {
    super.initState();
    final delay = Duration(
      milliseconds:
          widget.initialDelayMs + widget.stepMs * widget.index.clamp(0, 8),
    );
    Future.delayed(delay, () {
      if (mounted) setState(() => _visible = true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final duration = Duration(milliseconds: widget.durationMs);
    return AnimatedOpacity(
      opacity: _visible ? 1 : 0,
      duration: duration,
      curve: Curves.easeOutCubic,
      child: AnimatedSlide(
        offset: _visible ? Offset.zero : Offset(0, widget.slide),
        duration: duration,
        curve: Curves.easeOutCubic,
        child: widget.child,
      ),
    );
  }
}
