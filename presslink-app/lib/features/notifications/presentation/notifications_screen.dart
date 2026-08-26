import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../domain/notification_repository.dart';
import 'notifications_controller.dart';

/// Écran Notifications — Cahier §10.2 / Architecture des écrans §7.
/// User Flows §6 : taper une notification ouvre le détail de la commande.
class NotificationsScreen extends ConsumerWidget {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final notificationsAsync = ref.watch(notificationsProvider);
    final canPop = Navigator.of(context).canPop();

    return Scaffold(
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, 0),
              child: Row(
                children: [
                  if (canPop) ...[
                    AppBackButton(onPressed: () => Navigator.of(context).pop()),
                    const SizedBox(width: AppSpacing.sm + 2),
                  ],
                  Text('Notifications', style: theme.textTheme.headlineSmall),
                ],
              ),
            ),
            Expanded(
              child: notificationsAsync.when(
                loading: () => const Center(child: CircularProgressIndicator()),
                error: (error, _) => Center(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.md),
                    child: Text('Impossible de charger les notifications.', style: theme.textTheme.bodyMedium),
                  ),
                ),
                data: (notifications) {
                  if (notifications.isEmpty) {
                    return Center(
                      child: Padding(
                        padding: const EdgeInsets.all(AppSpacing.md),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(Icons.notifications_none, size: 40, color: theme.textTheme.bodyMedium?.color),
                            const SizedBox(height: AppSpacing.sm),
                            const Text('Aucune notification pour le moment.'),
                          ],
                        ),
                      ),
                    );
                  }

                  return RefreshIndicator(
                    onRefresh: () async {
                      ref.invalidate(notificationsProvider);
                      ref.invalidate(unreadNotificationsCountProvider);
                      await ref.read(notificationsProvider.future);
                    },
                    child: ListView.separated(
                      padding: const EdgeInsets.all(AppSpacing.md),
                      itemCount: notifications.length,
                      separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                      itemBuilder: (context, index) {
                        final n = notifications[index];
                        return _NotificationTile(
                          notification: n,
                          onTap: () async {
                            if (!n.isRead) {
                              await ref.read(notificationRepositoryProvider).markAsRead(n.id);
                              ref.invalidate(notificationsProvider);
                              ref.invalidate(unreadNotificationsCountProvider);
                            }
                            if (n.orderId != null && context.mounted) {
                              Navigator.of(context).push(
                                MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: n.orderId!)),
                              );
                            }
                          },
                        );
                      },
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationTile extends StatelessWidget {
  const _NotificationTile({required this.notification, required this.onTap});

  final AppNotification notification;
  final VoidCallback onTap;

  ({IconData icon, Color color}) get _iconStyle {
    final title = notification.title.toLowerCase();
    if (title.contains('prête')) return (icon: Icons.local_laundry_service, color: AppColors.success);
    if (title.contains('récupérée')) return (icon: Icons.check_circle, color: AppColors.textSecondary);
    return (icon: Icons.receipt_long, color: AppColors.primary);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = _iconStyle;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.lg),
      child: Container(
        padding: const EdgeInsets.all(AppSpacing.md - 1),
        decoration: BoxDecoration(
          color: notification.isRead ? theme.cardTheme.color : AppColors.primaryTint,
          border: Border.all(color: theme.dividerTheme.color!),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 34,
              height: 34,
              decoration: BoxDecoration(
                color: style.color.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(style.icon, size: 17, color: style.color),
            ),
            const SizedBox(width: AppSpacing.sm + 5),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(notification.title, style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600, fontSize: 14)),
                  const SizedBox(height: 3),
                  Text(notification.body, style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13)),
                  const SizedBox(height: 6),
                  Text(_relativeTime(notification.createdAt), style: theme.textTheme.labelSmall),
                ],
              ),
            ),
            if (!notification.isRead) ...[
              const SizedBox(width: AppSpacing.xs),
              Container(
                width: 8,
                height: 8,
                margin: const EdgeInsets.only(top: 4),
                decoration: const BoxDecoration(color: AppColors.primary, shape: BoxShape.circle),
              ),
            ],
          ],
        ),
      ),
    );
  }

  String _relativeTime(DateTime dateTime) {
    final diff = DateTime.now().difference(dateTime);
    if (diff.inMinutes < 60) return 'Il y a ${diff.inMinutes} min';
    if (diff.inHours < 24) return 'Il y a ${diff.inHours} h';
    return 'Il y a ${diff.inDays} j';
  }
}
