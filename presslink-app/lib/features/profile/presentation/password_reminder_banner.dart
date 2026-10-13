import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_page_route.dart';
import '../../auth/presentation/auth_controller.dart';
import 'change_password_screen.dart';

/// Rappel affiché tant que le client garde un mot de passe réinitialisé
/// (ou attribué par défaut par son pressing) — même message que sur le
/// dashboard web.
class PasswordReminderBanner extends ConsumerWidget {
  const PasswordReminderBanner({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mustChange =
        ref.watch(authControllerProvider).customer?.mustChangePassword ?? false;
    if (!mustChange) return const SizedBox.shrink();

    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.sm + 6),
        decoration: BoxDecoration(
          color: isDark
              ? AppColors.warning.withValues(alpha: 0.14)
              : AppColors.warningTint,
          borderRadius: BorderRadius.circular(AppRadius.lg),
          border: Border.all(color: const Color(0xFFFDE68A)),
        ),
        child: Row(
          children: [
            const Icon(
              Icons.lock_outline_rounded,
              color: AppColors.warningText,
              size: 22,
            ),
            const SizedBox(width: AppSpacing.sm + 4),
            const Expanded(
              child: Text.rich(
                TextSpan(
                  children: [
                    TextSpan(
                      text: 'Votre mot de passe a été réinitialisé. ',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    TextSpan(
                      text:
                          'Choisissez-en un nouveau pour sécuriser votre compte.',
                    ),
                  ],
                ),
                style: TextStyle(
                  color: AppColors.warningText,
                  fontSize: 13.5,
                  height: 1.35,
                ),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            TextButton(
              onPressed: () => Navigator.of(context).push(
                AppPageRoute(builder: (_) => const ChangePasswordScreen()),
              ),
              style: TextButton.styleFrom(
                foregroundColor: Colors.white,
                backgroundColor: AppColors.warningText,
                padding: const EdgeInsets.symmetric(horizontal: 12),
                minimumSize: const Size(0, 36),
              ),
              child: const Text('Changer'),
            ),
          ],
        ),
      ),
    );
  }
}
