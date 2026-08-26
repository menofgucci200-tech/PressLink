import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/order_card.dart';
import '../domain/order_repository.dart';
import '../../../shared/models/order_summary.dart';
import 'order_detail_screen.dart';
import 'orders_controller.dart';

enum _Filter { toutes, enCours, pretes, historique }

/// Mes commandes — Cahier §10.2 (filtres En cours / Prêtes / Historique).
class OrdersListScreen extends ConsumerStatefulWidget {
  const OrdersListScreen({super.key});

  @override
  ConsumerState<OrdersListScreen> createState() => _OrdersListScreenState();
}

class _OrdersListScreenState extends ConsumerState<OrdersListScreen> {
  _Filter _filter = _Filter.toutes;

  bool _matches(OrderModel order) => switch (_filter) {
        _Filter.toutes => true,
        _Filter.enCours => order.status == OrderStatus.recue || order.status == OrderStatus.traitement,
        _Filter.pretes => order.status == OrderStatus.prete,
        _Filter.historique => order.status == OrderStatus.recuperee,
      };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final ordersAsync = ref.watch(ordersProvider);

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Mes commandes', style: theme.textTheme.headlineSmall),
              const SizedBox(height: AppSpacing.md),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final f in _Filter.values) ...[
                      _FilterChip(label: _labelFor(f), selected: _filter == f, onTap: () => setState(() => _filter = f)),
                      const SizedBox(width: AppSpacing.xs + 4),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: ordersAsync.when(
                  loading: () => const Center(child: CircularProgressIndicator()),
                  error: (e, _) => Center(child: Text('Impossible de charger vos commandes.', style: theme.textTheme.bodyMedium)),
                  data: (orders) {
                    final filtered = orders.where(_matches).toList();
                    if (filtered.isEmpty) {
                      return Center(
                        child: Text('Aucune commande ici.', style: theme.textTheme.bodyMedium),
                      );
                    }
                    return RefreshIndicator(
                      onRefresh: () async {
                        ref.invalidate(ordersProvider);
                        await ref.read(ordersProvider.future);
                      },
                      child: ListView.separated(
                        itemCount: filtered.length,
                        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final order = filtered[index];
                          return OrderCard(
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
      ),
    );
  }

  String _labelFor(_Filter f) => switch (f) {
        _Filter.toutes => 'Toutes',
        _Filter.enCours => 'En cours',
        _Filter.pretes => 'Prêtes',
        _Filter.historique => 'Historique',
      };
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: selected ? AppColors.primary : Colors.white,
          border: Border.all(color: selected ? AppColors.primary : AppColors.border),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          label,
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w500, color: selected ? Colors.white : AppColors.textSecondary),
        ),
      ),
    );
  }
}
