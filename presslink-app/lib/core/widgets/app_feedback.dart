import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Ton d'une boîte de confirmation : couleur de l'icône et du bouton.
enum AppConfirmTone { primary, warning, danger }

/// Type d'une notification (toast).
enum AppToastType { success, error, warning, info }

/// Boîte de confirmation PressLink — même présentation que celle du
/// dashboard web : icône, titre, explication des conséquences, bouton
/// nommé selon l'action. Résout `true` si l'utilisateur confirme.
Future<bool> showAppConfirm(
  BuildContext context, {
  required String title,
  required String message,
  required String confirmLabel,
  String cancelLabel = 'Annuler',
  AppConfirmTone tone = AppConfirmTone.primary,
  IconData? icon,
}) async {
  final result = await showGeneralDialog<bool>(
    context: context,
    barrierDismissible: true,
    barrierLabel: cancelLabel,
    barrierColor: const Color(0x800F172A),
    transitionDuration: const Duration(milliseconds: 180),
    pageBuilder: (context, _, _) => _ConfirmDialog(
      title: title,
      message: message,
      confirmLabel: confirmLabel,
      cancelLabel: cancelLabel,
      tone: tone,
      icon: icon,
    ),
    transitionBuilder: (context, animation, _, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutCubic,
      );
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          scale: Tween<double>(begin: 0.96, end: 1).animate(curved),
          child: child,
        ),
      );
    },
  );
  return result ?? false;
}

/// Notification PressLink en bas de l'écran (succès, erreur…), à la place
/// du SnackBar Material par défaut.
void showAppToast(
  BuildContext context,
  String message, {
  AppToastType type = AppToastType.success,
}) {
  final messenger = ScaffoldMessenger.maybeOf(context);
  if (messenger == null) return;

  final isDark = Theme.of(context).brightness == Brightness.dark;
  final (Color fg, Color bg, IconData icon) = switch (type) {
    AppToastType.success => (
      AppColors.successText,
      context.successTint,
      Icons.check_rounded,
    ),
    AppToastType.error => (
      AppColors.error,
      context.errorTint,
      Icons.close_rounded,
    ),
    AppToastType.warning => (
      AppColors.warningText,
      context.warningTint,
      Icons.priority_high_rounded,
    ),
    AppToastType.info => (
      AppColors.info,
      context.infoTint,
      Icons.info_outline_rounded,
    ),
  };

  messenger
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        backgroundColor: Colors.transparent,
        padding: EdgeInsets.zero,
        margin: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          0,
          AppSpacing.md,
          AppSpacing.md,
        ),
        duration: Duration(seconds: type == AppToastType.error ? 6 : 4),
        content: Semantics(
          liveRegion: true,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
            decoration: BoxDecoration(
              color: isDark ? AppColors.surfaceDark : context.surface,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              border: Border.all(
                color: isDark ? AppColors.borderDark : context.border,
              ),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x260F172A),
                  blurRadius: 18,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
                  width: 28,
                  height: 28,
                  decoration: BoxDecoration(
                    color: isDark ? fg.withValues(alpha: 0.18) : bg,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(icon, size: 16, color: fg),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    message,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w500,
                      height: 1.35,
                      color: isDark
                          ? AppColors.textPrimaryDark
                          : context.textPrimary,
                    ),
                  ),
                ),
                const SizedBox(width: 4),
                InkWell(
                  borderRadius: BorderRadius.circular(AppRadius.md),
                  onTap: messenger.hideCurrentSnackBar,
                  child: Padding(
                    padding: const EdgeInsets.all(4),
                    child: Icon(
                      Icons.close_rounded,
                      size: 18,
                      semanticLabel: 'Fermer',
                      color: isDark
                          ? AppColors.textMutedDark
                          : context.textMuted,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
}

class _ConfirmDialog extends StatelessWidget {
  const _ConfirmDialog({
    required this.title,
    required this.message,
    required this.confirmLabel,
    required this.cancelLabel,
    required this.tone,
    this.icon,
  });

  final String title;
  final String message;
  final String confirmLabel;
  final String cancelLabel;
  final AppConfirmTone tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final (Color accent, Color tint, IconData defaultIcon) = switch (tone) {
      AppConfirmTone.danger => (
        AppColors.error,
        context.errorTint,
        Icons.delete_outline_rounded,
      ),
      AppConfirmTone.warning => (
        AppColors.warning,
        context.warningTint,
        Icons.warning_amber_rounded,
      ),
      AppConfirmTone.primary => (
        AppColors.primary,
        context.primaryTint,
        Icons.info_outline_rounded,
      ),
    };
    final iconColor = tone == AppConfirmTone.warning
        ? AppColors.warningText
        : accent;

    return SafeArea(
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 420),
            child: Material(
              color: isDark ? AppColors.surfaceDark : context.surface,
              borderRadius: BorderRadius.circular(20),
              clipBehavior: Clip.antiAlias,
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: BoxDecoration(
                        color: isDark ? accent.withValues(alpha: 0.18) : tint,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        icon ?? defaultIcon,
                        color: iconColor,
                        size: 22,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      title,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.sm),
                    Text(
                      message,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        height: 1.45,
                        color: isDark
                            ? AppColors.textSecondaryDark
                            : context.textSecondary,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.lg),
                    Row(
                      children: [
                        Expanded(
                          child: SizedBox(
                            height: 46,
                            child: OutlinedButton(
                              onPressed: () => Navigator.of(context).pop(false),
                              child: Text(cancelLabel),
                            ),
                          ),
                        ),
                        const SizedBox(width: AppSpacing.sm),
                        Expanded(
                          child: SizedBox(
                            height: 46,
                            child: FilledButton(
                              autofocus: true,
                              style: FilledButton.styleFrom(
                                backgroundColor: accent,
                                foregroundColor: Colors.white,
                              ),
                              onPressed: () => Navigator.of(context).pop(true),
                              child: Text(
                                confirmLabel,
                                textAlign: TextAlign.center,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
