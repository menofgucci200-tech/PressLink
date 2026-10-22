import 'package:flutter/material.dart';
import '../theme/app_colors.dart';
import '../theme/app_theme.dart';

/// Rendu texte du nom "PressLink" conforme à l'identité de marque :
/// "Press" en noir/marine, "Link" en bleu primaire, police Inter Bold.
class AppNameText extends StatelessWidget {
  const AppNameText({super.key, this.fontSize = 32});

  final double fontSize;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontFamily: AppTheme.bodyFont,
      fontSize: fontSize,
      fontWeight: FontWeight.w700,
    );

    return RichText(
      text: TextSpan(
        style: style,
        children: [
          TextSpan(
            text: 'Press',
            style: TextStyle(color: context.textPrimary),
          ),
          TextSpan(
            text: 'Link',
            style: TextStyle(color: AppColors.primary),
          ),
        ],
      ),
    );
  }
}
