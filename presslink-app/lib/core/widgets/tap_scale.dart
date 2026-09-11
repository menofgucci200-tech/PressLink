import 'package:flutter/material.dart';

/// Enveloppe un widget cliquable d'un léger effet d'enfoncement (scale down)
/// au tap, en plus du ripple Material déjà fourni par `InkWell` — pour que
/// les cartes/boutons importants de l'app donnent une vraie sensation
/// physique au toucher plutôt qu'un simple changement d'état.
class TapScale extends StatefulWidget {
  const TapScale({super.key, required this.child, this.scale = 0.97});

  final Widget child;
  final double scale;

  @override
  State<TapScale> createState() => _TapScaleState();
}

class _TapScaleState extends State<TapScale> {
  bool _pressed = false;

  void _setPressed(bool value) {
    if (_pressed != value) setState(() => _pressed = value);
  }

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: (_) => _setPressed(true),
      onPointerUp: (_) => _setPressed(false),
      onPointerCancel: (_) => _setPressed(false),
      child: AnimatedScale(
        scale: _pressed ? widget.scale : 1.0,
        duration: const Duration(milliseconds: 100),
        curve: Curves.easeOut,
        child: widget.child,
      ),
    );
  }
}
