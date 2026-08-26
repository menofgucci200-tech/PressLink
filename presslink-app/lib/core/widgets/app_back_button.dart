import 'package:flutter/material.dart';

import '../theme/app_colors.dart';

/// Bouton retour fidèle au prototype : carré 38px, radius 11, fond blanc,
/// bordure claire (cf. UI haute fidélité, écrans "Détail" et "Rejoindre").
class AppBackButton extends StatelessWidget {
  const AppBackButton({this.onPressed, super.key});

  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: onPressed ?? () => Navigator.of(context).maybePop(),
      borderRadius: BorderRadius.circular(11),
      child: Container(
        width: 38,
        height: 38,
        decoration: BoxDecoration(
          color: theme.cardTheme.color,
          borderRadius: BorderRadius.circular(11),
          border: Border.all(color: theme.dividerTheme.color ?? AppColors.border),
        ),
        child: const Icon(Icons.arrow_back, size: 18),
      ),
    );
  }
}
