import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/order_card.dart';
import '../../../shared/models/order_summary.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../notifications/presentation/notifications_controller.dart';
import '../../notifications/presentation/notifications_screen.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../orders/presentation/orders_controller.dart';
import '../../pressings/presentation/join_pressing_screen.dart';
import '../../pressings/presentation/pressings_controller.dart';

/// Écran Accueil Client — cf. Wireframes & Layouts §3, UI haute fidélité §2.
class HomeScreen extends ConsumerWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final customer = ref.watch(authControllerProvider).customer;
    final firstName = customer?.firstName.isNotEmpty == true ? customer!.firstName : '';
    final ordersAsync = ref.watch(ordersProvider);
    final pressingsAsync = ref.watch(myPressingsProvider);

    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async {
            ref.invalidate(ordersProvider);
            ref.invalidate(myPressingsProvider);
            await Future.wait([ref.read(ordersProvider.future), ref.read(myPressingsProvider.future)]);
          },
          child: ListView(
            padding: const EdgeInsets.fromLTRB(
              AppSpacing.md,
              AppSpacing.sm,
              AppSpacing.md,
              AppSpacing.xl,
            ),
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('Bonjour $firstName', style: theme.textTheme.headlineSmall),
                      const SizedBox(height: 4),
                      Text('Voici l\'état de vos commandes', style: theme.textTheme.bodyMedium),
                    ],
                  ),
                  Row(
                    children: [
                      _NotificationBell(
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute(builder: (_) => const NotificationsScreen()),
                        ),
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      IconButton(
                        tooltip: 'Se déconnecter',
                        onPressed: () => ref.read(authControllerProvider.notifier).logout(),
                        icon: const Icon(Icons.logout, size: 20),
                      ),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              ordersAsync.when(
                loading: () => const _SummaryCardSkeleton(),
                error: (e, _) => const SizedBox.shrink(),
                data: (orders) {
                  final ready = orders.where((o) => o.status == OrderStatus.prete).length;
                  final inProgress = orders.where((o) => o.status == OrderStatus.recue || o.status == OrderStatus.traitement).length;
                  return _SummaryCard(total: ready + inProgress, ready: ready, inProgress: inProgress);
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              Text('COMMANDES RÉCENTES', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
              const SizedBox(height: AppSpacing.sm + 4),
              ordersAsync.when(
                loading: () => const Padding(
                  padding: EdgeInsets.symmetric(vertical: AppSpacing.lg),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (e, _) => Text('Impossible de charger vos commandes.', style: theme.textTheme.bodyMedium),
                data: (orders) {
                  if (orders.isEmpty) {
                    return Text('Aucune commande pour le moment.', style: theme.textTheme.bodyMedium);
                  }
                  return Column(
                    children: [
                      for (final order in orders.take(3)) ...[
                        OrderCard(
                          order: OrderSummary(
                            orderNumber: order.orderNumber,
                            pressingName: order.pressingName,
                            items: order.itemsLabel,
                            status: order.status,
                            totalFcfa: order.totalFcfa,
                          ),
                          onTap: () => Navigator.of(context).push(
                            MaterialPageRoute(builder: (_) => OrderDetailScreen(orderId: order.id)),
                          ),
                        ),
                        const SizedBox(height: AppSpacing.sm),
                      ],
                    ],
                  );
                },
              ),
              const SizedBox(height: AppSpacing.lg),
              Text('MES PRESSINGS', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
              const SizedBox(height: AppSpacing.sm + 4),
              pressingsAsync.when(
                loading: () => const Padding(
                  padding: EdgeInsets.symmetric(vertical: AppSpacing.md),
                  child: Center(child: CircularProgressIndicator()),
                ),
                error: (e, _) => Text('Impossible de charger vos pressings.', style: theme.textTheme.bodyMedium),
                data: (pressings) => Column(
                  children: [
                    for (final pressing in pressings) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm + 3, vertical: AppSpacing.sm + 1),
                        decoration: BoxDecoration(
                          color: theme.cardTheme.color,
                          border: Border.all(color: theme.dividerTheme.color!),
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 38,
                              height: 38,
                              decoration: BoxDecoration(color: AppColors.primaryTint, borderRadius: BorderRadius.circular(10)),
                              child: Center(
                                child: Text(pressing.initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w600, fontSize: 13)),
                              ),
                            ),
                            const SizedBox(width: AppSpacing.sm + 5),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(pressing.name, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
                                  const SizedBox(height: 2),
                                  Text('${pressing.city ?? ''} · ${pressing.ordersCount} commande(s)', style: theme.textTheme.labelSmall),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                    ],
                    InkWell(
                      onTap: () async {
                        final joined = await Navigator.of(context).push<bool>(
                          MaterialPageRoute(builder: (_) => const JoinPressingScreen()),
                        );
                        if (joined == true) {
                          ref.invalidate(myPressingsProvider);
                        }
                      },
                      borderRadius: BorderRadius.circular(AppRadius.lg),
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(AppSpacing.sm + 6),
                        decoration: BoxDecoration(
                          border: Border.all(color: AppColors.border, style: BorderStyle.solid),
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: const [
                            Icon(Icons.add, size: 16, color: AppColors.textSecondary),
                            SizedBox(width: 8),
                            Text('Ajouter un pressing', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: AppColors.textSecondary)),
                          ],
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.total, required this.ready, required this.inProgress});

  final int total;
  final int ready;
  final int inProgress;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md + 4),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.primary, AppColors.secondary],
        ),
        borderRadius: BorderRadius.circular(AppRadius.xl),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text('Vos commandes', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500)),
              const SizedBox(height: 6),
              Text('$total', style: const TextStyle(color: Colors.white, fontSize: 34, fontWeight: FontWeight.w800)),
              const SizedBox(height: 4),
              Text('$ready prête(s) · $inProgress en cours', style: const TextStyle(color: Colors.white70, fontSize: 12.5)),
            ],
          ),
          Container(
            width: 56,
            height: 56,
            decoration: BoxDecoration(color: Colors.white.withValues(alpha: 0.16), shape: BoxShape.circle),
            child: const Center(child: Text('🧺', style: TextStyle(fontSize: 22))),
          ),
        ],
      ),
    );
  }
}

class _SummaryCardSkeleton extends StatelessWidget {
  const _SummaryCardSkeleton();

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 118,
      decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(AppRadius.xl)),
    );
  }
}

class _NotificationBell extends ConsumerWidget {
  const _NotificationBell({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final unreadCount = ref.watch(unreadNotificationsCountProvider).valueOrNull ?? 0;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: theme.scaffoldBackgroundColor,
          shape: BoxShape.circle,
          border: Border.all(color: theme.dividerTheme.color ?? AppColors.border),
        ),
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            Center(
              child: Icon(Icons.notifications_outlined, color: theme.textTheme.bodyMedium?.color, size: 20),
            ),
            if (unreadCount > 0)
              Positioned(
                top: -2,
                right: -2,
                child: Container(
                  padding: const EdgeInsets.all(3),
                  constraints: const BoxConstraints(minWidth: 16, minHeight: 16),
                  decoration: const BoxDecoration(color: AppColors.error, shape: BoxShape.circle),
                  child: Center(
                    child: Text(
                      '$unreadCount',
                      style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
