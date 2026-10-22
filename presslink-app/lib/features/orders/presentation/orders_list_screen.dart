import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/app_page_route.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_theme.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/error_state_view.dart';
import '../../../core/widgets/order_card.dart';
import '../../../core/widgets/staggered_fade_in.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../domain/order_repository.dart';
import '../../../shared/models/order_summary.dart';
import '../../pressings/presentation/join_pressing_screen.dart';
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

  bool _matches(OrderModel order) => _isIn(_filter, order);

  static bool _isIn(_Filter filter, OrderModel order) => switch (filter) {
    _Filter.toutes => true,
    // « En attente » est une commande encore au pressing : en cours.
    _Filter.enCours =>
      order.status == OrderStatus.recue ||
          order.status == OrderStatus.traitement ||
          order.status == OrderStatus.attente,
    _Filter.pretes => order.status == OrderStatus.prete,
    // Commandes terminées : récupérées ou annulées.
    _Filter.historique =>
      order.status == OrderStatus.recuperee ||
          order.status == OrderStatus.annulee,
  };

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final ordersAsync = ref.watch(ordersProvider);
    // Nombre de commandes par filtre, affiché sur chaque filtre une fois la
    // liste chargée (même liste que celle qui est filtrée plus bas).
    final loadedOrders = ordersAsync.valueOrNull;

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Mes commandes'),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    for (final f in _Filter.values) ...[
                      _FilterChip(
                        label: _labelFor(f),
                        palette: _paletteFor(f),
                        count: loadedOrders
                            ?.where((order) => _isIn(f, order))
                            .length,
                        selected: _filter == f,
                        onTap: () => setState(() => _filter = f),
                      ),
                      const SizedBox(width: AppSpacing.xs + 1),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Expanded(
                child: ordersAsync.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (e, _) => ErrorStateView(
                    message: apiErrorMessage(e),
                    onRetry: () => ref.invalidate(ordersProvider),
                  ),
                  data: (orders) {
                    final filtered = orders.where(_matches).toList();
                    if (filtered.isEmpty && orders.isEmpty) {
                      return Center(
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.receipt_long_outlined,
                                size: 32,
                                color: theme.textTheme.bodyMedium?.color,
                              ),
                              const SizedBox(height: AppSpacing.sm),
                              const Text(
                                'Vous n\'avez encore aucune commande.',
                              ),
                              const SizedBox(height: AppSpacing.sm + 4),
                              OutlinedButton.icon(
                                onPressed: () => Navigator.of(context).push(
                                  AppPageRoute(
                                    builder: (_) => const JoinPressingScreen(),
                                  ),
                                ),
                                icon: const Icon(Icons.add, size: 18),
                                label: const Text('Rejoindre un pressing'),
                              ),
                            ],
                          ),
                        ),
                      );
                    }
                    if (filtered.isEmpty) {
                      return Center(
                        child: Text(
                          'Aucune commande ici.',
                          style: theme.textTheme.bodyMedium,
                        ),
                      );
                    }
                    return RefreshIndicator(
                      onRefresh: () async {
                        ref.invalidate(ordersProvider);
                        await ref.read(ordersProvider.future);
                      },
                      child: ListView.separated(
                        itemCount: filtered.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final order = filtered[index];
                          return StaggeredFadeIn(
                            key: ValueKey('$_filter-${order.id}'),
                            index: index,
                            child: OrderCard(
                              order: OrderSummary(
                                orderNumber: order.orderNumber,
                                pressingName: order.pressingName,
                                items: order.itemsLabel,
                                status: order.status,
                                totalFcfa: order.totalFcfa,
                                balanceDueFcfa: order.balanceDueFcfa,
                              ),
                              onTap: () => Navigator.of(context)
                                  .push(
                                    AppPageRoute(
                                      builder: (_) =>
                                          OrderDetailScreen(orderId: order.id),
                                    ),
                                  )
                                  .then((_) => ref.invalidate(ordersProvider)),
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

  static _FilterPalette _paletteFor(_Filter f) => switch (f) {
    _Filter.toutes => const _FilterPalette(
      solid: AppColors.primary,
      text: AppColors.primaryDark,
      textDark: Color(0xFF93C5FD),
    ),
    _Filter.enCours => const _FilterPalette(
      solid: Color(0xFF0891B2),
      text: Color(0xFF0E7490),
      textDark: Color(0xFF67E8F9),
    ),
    _Filter.pretes => const _FilterPalette(
      solid: AppColors.successText,
      text: Color(0xFF047857),
      textDark: Color(0xFF6EE7B7),
    ),
    _Filter.historique => const _FilterPalette(
      solid: Color(0xFF64748B),
      text: AppColors.textSecondary,
      textDark: Color(0xFFCBD5E1),
    ),
  };

  String _labelFor(_Filter f) => switch (f) {
    _Filter.toutes => 'Toutes',
    _Filter.enCours => 'En cours',
    _Filter.pretes => 'Prêtes',
    _Filter.historique => 'Historique',
  };
}

/// Couleurs d'un filtre, reprises des statuts de commande (badges des
/// cartes) : bleu PressLink pour Toutes, cyan « en traitement » pour En
/// cours, vert « prête » pour Prêtes, gris « récupérée » pour Historique.
class _FilterPalette {
  const _FilterPalette({
    required this.solid,
    required this.text,
    required this.textDark,
  });

  /// Fond du filtre sélectionné (texte blanc dessus).
  final Color solid;

  /// Texte du filtre non sélectionné, en mode jour / en mode nuit.
  final Color text;
  final Color textDark;
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.palette,
    required this.count,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final _FilterPalette palette;

  /// Nombre de commandes du filtre (inconnu tant que la liste charge).
  final int? count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final solid = palette.solid;
    final text = isDark ? palette.textDark : palette.text;

    return Semantics(
      button: true,
      selected: selected,
      label: count == null ? label : '$label, $count',
      excludeSemantics: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          curve: Curves.easeOut,
          padding: EdgeInsets.only(
            left: 10,
            right: count == null ? 10 : 4,
            top: 6,
            bottom: 6,
          ),
          decoration: BoxDecoration(
            color: selected
                ? solid
                : solid.withValues(alpha: isDark ? 0.14 : 0.08),
            border: Border.all(
              color: selected
                  ? solid
                  : solid.withValues(alpha: isDark ? 0.4 : 0.3),
            ),
            borderRadius: BorderRadius.circular(999),
            boxShadow: selected
                ? [
                    BoxShadow(
                      color: solid.withValues(alpha: 0.35),
                      blurRadius: 10,
                      offset: const Offset(0, 4),
                      spreadRadius: -4,
                    ),
                  ]
                : null,
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              AnimatedDefaultTextStyle(
                duration: const Duration(milliseconds: 200),
                style: TextStyle(
                  fontFamily: AppTheme.bodyFont,
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: selected ? Colors.white : text,
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Text(label),
                ),
              ),
              if (count != null) ...[
                const SizedBox(width: 4),
                AnimatedContainer(
                  duration: const Duration(milliseconds: 200),
                  constraints: const BoxConstraints(minWidth: 20),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 5,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: selected
                        ? Colors.white.withValues(alpha: 0.24)
                        : solid.withValues(alpha: isDark ? 0.24 : 0.14),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: Text(
                    '$count',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                      color: selected
                          ? Colors.white
                          : text.withValues(alpha: count == 0 ? 0.6 : 1),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
