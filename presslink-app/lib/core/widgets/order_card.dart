import 'package:flutter/material.dart';

import '../../shared/models/order_summary.dart';
import '../theme/app_spacing.dart';
import 'status_badge.dart';

/// OrderCard — composant central de l'app client.
/// Le statut doit être compris avant les détails (Wireframes §4-5).
class OrderCard extends StatelessWidget {
  const OrderCard({required this.order, this.onTap, super.key});

  final OrderSummary order;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.lg),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(order.pressingName, style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w700, fontSize: 15)),
                      const SizedBox(height: 2),
                      Text(order.orderNumber, style: theme.textTheme.labelSmall),
                    ],
                  ),
                  StatusBadge(status: order.status),
                ],
              ),
              const SizedBox(height: AppSpacing.sm + 2),
              Text(order.items, style: theme.textTheme.bodyMedium),
              const SizedBox(height: AppSpacing.sm + 4),
              Divider(color: theme.dividerTheme.color),
              const SizedBox(height: AppSpacing.sm),
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Text(
                    order.formattedTotal,
                    style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        'Voir la commande',
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.primary,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(width: 4),
                      Icon(Icons.arrow_forward, size: 14, color: theme.colorScheme.primary),
                    ],
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
