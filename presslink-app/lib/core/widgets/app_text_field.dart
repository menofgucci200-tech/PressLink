import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Champ de saisie fidèle au prototype : label statique au-dessus,
/// hauteur 52, radius 10, bordure claire, focus bleu PressLink.
class AppTextField extends StatelessWidget {
  const AppTextField({
    required this.controller,
    this.label,
    this.hintText,
    this.keyboardType,
    this.obscureText = false,
    this.autofocus = false,
    this.textCapitalization = TextCapitalization.none,
    this.suffixIcon,
    this.onSubmitted,
    super.key,
  });

  final TextEditingController controller;
  final String? label;
  final String? hintText;
  final TextInputType? keyboardType;
  final bool obscureText;
  final bool autofocus;
  final TextCapitalization textCapitalization;
  final Widget? suffixIcon;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null) ...[
          Text(
            label!,
            style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12, fontWeight: FontWeight.w500),
          ),
          const SizedBox(height: 6),
        ],
        SizedBox(
          height: 52,
          child: TextField(
            controller: controller,
            keyboardType: keyboardType,
            obscureText: obscureText,
            autofocus: autofocus,
            textCapitalization: textCapitalization,
            onSubmitted: onSubmitted,
            style: theme.textTheme.bodyLarge?.copyWith(fontSize: 15, fontWeight: FontWeight.w600),
            decoration: InputDecoration(
              hintText: hintText,
              suffixIcon: suffixIcon,
              hintStyle: theme.textTheme.bodyLarge?.copyWith(
                fontSize: 15,
                fontWeight: FontWeight.w400,
                color: AppColors.textMuted,
              ),
              contentPadding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
            ),
          ),
        ),
      ],
    );
  }
}
