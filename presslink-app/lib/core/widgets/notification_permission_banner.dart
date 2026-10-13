import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../notifications/push_notification_service.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// PWA : invite à activer les notifications push. La demande d'autorisation
/// du navigateur part d'un tap (exigé par Safari iOS, et mieux accepté
/// partout qu'une demande à froid). Masquée si la permission est déjà
/// accordée ou refusée, si le navigateur ne gère pas le push, ou pendant
/// 7 jours après « Plus tard ».
class NotificationPermissionBanner extends ConsumerStatefulWidget {
  const NotificationPermissionBanner({super.key});

  @override
  ConsumerState<NotificationPermissionBanner> createState() =>
      _NotificationPermissionBannerState();
}

class _NotificationPermissionBannerState
    extends ConsumerState<NotificationPermissionBanner> {
  static const _dismissedKey = 'push_prompt_dismissed_at';
  static const _snooze = Duration(days: 7);

  bool _dismissed = true;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _loadDismissed();
  }

  Future<void> _loadDismissed() async {
    final prefs = await SharedPreferences.getInstance();
    final at = prefs.getInt(_dismissedKey);
    final stillSnoozed =
        at != null &&
        DateTime.now().difference(DateTime.fromMillisecondsSinceEpoch(at)) <
            _snooze;
    if (mounted) setState(() => _dismissed = stillSnoozed);
  }

  Future<void> _dismiss() async {
    setState(() => _dismissed = true);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_dismissedKey, DateTime.now().millisecondsSinceEpoch);
  }

  Future<void> _enable() async {
    setState(() => _busy = true);
    final granted = await ref
        .read(pushNotificationServiceProvider)
        .requestWebPermission();
    if (!mounted) return;
    setState(() => _busy = false);
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          granted
              ? 'Notifications activées : vous serez prévenu dès que votre commande est prête.'
              : 'Notifications non autorisées. Vous pouvez les activer dans les réglages du navigateur.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final service = ref.watch(pushNotificationServiceProvider);
    if (_dismissed || !service.webPermissionPending) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Container(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.sm + 4,
          AppSpacing.sm,
          AppSpacing.md,
        ),
        decoration: BoxDecoration(
          color: AppColors.primaryTint,
          border: Border.all(color: AppColors.primary.withValues(alpha: 0.25)),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.notifications_active_outlined,
                    size: 20,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm + 4),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Activer les notifications',
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'Soyez prévenu dès que votre commande est prête.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 12.5,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Plus tard',
                  onPressed: _dismiss,
                  visualDensity: VisualDensity.compact,
                  icon: const Icon(
                    Icons.close,
                    size: 18,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm + 4),
            Padding(
              padding: const EdgeInsets.only(right: AppSpacing.sm),
              child: SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _busy ? null : _enable,
                  child: const Text('Activer'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
