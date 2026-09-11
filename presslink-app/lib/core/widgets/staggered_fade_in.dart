import 'package:flutter/material.dart';

/// Fait apparaître un élément de liste en fondu + léger glissement vers le
/// haut, avec un délai proportionnel à son index — effet de cascade à
/// l'ouverture d'une liste (commandes, notifications, pressings…) au lieu
/// d'un bloc qui s'affiche d'un coup. Le layout garde sa place dès le
/// départ (seule l'opacité/la position animent) pour ne jamais faire
/// sauter la liste pendant la cascade.
class StaggeredFadeIn extends StatefulWidget {
  const StaggeredFadeIn({super.key, required this.index, required this.child});

  final int index;
  final Widget child;

  @override
  State<StaggeredFadeIn> createState() => _StaggeredFadeInState();
}

class _StaggeredFadeInState extends State<StaggeredFadeIn> {
  bool _visible = false;

  @override
  void initState() {
    super.initState();
    final delay = Duration(milliseconds: 30 * widget.index.clamp(0, 8));
    Future.delayed(delay, () {
      if (mounted) setState(() => _visible = true);
    });
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedOpacity(
      opacity: _visible ? 1 : 0,
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOutCubic,
      child: AnimatedSlide(
        offset: _visible ? Offset.zero : const Offset(0, 0.04),
        duration: const Duration(milliseconds: 280),
        curve: Curves.easeOutCubic,
        child: widget.child,
      ),
    );
  }
}
