import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/app_page_route.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/error_state_view.dart';
import '../../../core/widgets/status_badge.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import 'orders_controller.dart';
import 'report_issue_screen.dart';

/// Étape intermédiaire du menu "Signaler un problème" : on choisit d'abord
/// la commande concernée avant d'ouvrir le formulaire de signalement.
class SelectOrderForIssueScreen extends ConsumerWidget {
  const SelectOrderForIssueScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final ordersAsync = ref.watch(ordersProvider);

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Signaler un problème'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Padding(
                padding: EdgeInsets.zero,
                child: Text(
                  'Sur quelle commande ?',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Expanded(
                child: ordersAsync.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (e, _) => ErrorStateView(
                    message: apiErrorMessage(e),
                    onRetry: () => ref.invalidate(ordersProvider),
                  ),
                  data: (orders) {
                    if (orders.isEmpty) {
                      return Center(
                        child: Text(
                          'Vous n\'avez aucune commande.',
                          style: theme.textTheme.bodyMedium,
                        ),
                      );
                    }
                    return ListView.separated(
                      itemCount: orders.length,
                      separatorBuilder: (_, _) =>
                          const SizedBox(height: AppSpacing.sm),
                      itemBuilder: (context, index) {
                        final order = orders[index];
                        return InkWell(
                          onTap: () => Navigator.of(context).push(
                            AppPageRoute(
                              builder: (_) => ReportIssueScreen(
                                orderId: order.id,
                                orderNumber: order.orderNumber,
                              ),
                            ),
                          ),
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                          child: Container(
                            padding: const EdgeInsets.all(AppSpacing.sm + 6),
                            decoration: BoxDecoration(
                              border: Border.all(
                                color: theme.dividerTheme.color!,
                              ),
                              borderRadius: BorderRadius.circular(AppRadius.lg),
                            ),
                            child: Row(
                              children: [
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        order.pressingName,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w600,
                                          fontSize: 14,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        order.orderNumber,
                                        style: theme.textTheme.labelSmall,
                                      ),
                                    ],
                                  ),
                                ),
                                StatusBadge(status: order.status),
                              ],
                            ),
                          ),
                        );
                      },
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
