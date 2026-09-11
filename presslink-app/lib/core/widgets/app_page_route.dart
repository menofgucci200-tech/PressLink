import 'package:flutter/material.dart';

/// Transition de navigation standard de l'app — un léger glissement depuis
/// la droite combiné à un fondu, pour que chaque changement d'écran soit
/// fluide plutôt que le "cut" brut du `MaterialPageRoute` par défaut.
/// À utiliser à la place de `MaterialPageRoute` partout où l'app pousse un
/// nouvel écran (`Navigator.of(context).push(AppPageRoute(builder: ...))`).
class AppPageRoute<T> extends PageRouteBuilder<T> {
  AppPageRoute({
    required WidgetBuilder builder,
    super.settings,
    super.fullscreenDialog,
  }) : super(
         transitionDuration: const Duration(milliseconds: 280),
         reverseTransitionDuration: const Duration(milliseconds: 220),
         pageBuilder: (context, animation, secondaryAnimation) =>
             builder(context),
         transitionsBuilder: (context, animation, secondaryAnimation, child) {
           final curved = CurvedAnimation(
             parent: animation,
             curve: Curves.easeOutCubic,
             reverseCurve: Curves.easeInCubic,
           );
           return FadeTransition(
             opacity: curved,
             child: SlideTransition(
               position: Tween<Offset>(
                 begin: const Offset(0.04, 0),
                 end: Offset.zero,
               ).animate(curved),
               child: child,
             ),
           );
         },
       );
}
