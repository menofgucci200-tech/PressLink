import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../domain/order_repository.dart';
import 'orders_controller.dart';
import 'report_issue_screen.dart';

/// Détail d'une commande — l'écran le plus important de l'app client
/// (UI haute fidélité §7 / prototype "cIsDetail").
class OrderDetailScreen extends ConsumerWidget {
  const OrderDetailScreen({required this.orderId, super.key});

  final int orderId;

  static const _canonicalSteps = [
    OrderStatus.recue,
    OrderStatus.traitement,
    OrderStatus.prete,
    OrderStatus.recuperee,
  ];

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final orderAsync = ref.watch(orderDetailProvider(orderId));

    return Scaffold(
      body: SafeArea(
        child: orderAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => Center(
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.md),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const AppBackButton(),
                  const SizedBox(height: AppSpacing.md),
                  Text('Impossible de charger cette commande.', style: theme.textTheme.bodyMedium),
                ],
              ),
            ),
          ),
          data: (order) => _OrderDetailBody(order: order, canonicalSteps: _canonicalSteps),
        ),
      ),
    );
  }
}

class _OrderDetailBody extends StatelessWidget {
  const _OrderDetailBody({required this.order, required this.canonicalSteps});

  final OrderModel order;
  final List<OrderStatus> canonicalSteps;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dateFormat = DateFormat('d MMMM', 'fr_FR');
    final currentIndex = canonicalSteps.indexOf(order.status);

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        Row(
          children: [
            const AppBackButton(),
            const SizedBox(width: AppSpacing.sm + 2),
            Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text('Commande ${order.orderNumber}', style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w700, fontSize: 16)),
                Text(order.pressingName, style: theme.textTheme.labelSmall),
              ],
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),

        // Hero de statut
        Container(
          width: double.infinity,
          padding: const EdgeInsets.symmetric(vertical: 26, horizontal: AppSpacing.md),
          decoration: BoxDecoration(
            color: order.status.tint,
            border: Border.all(color: order.status.color.withValues(alpha: 0.3)),
            borderRadius: BorderRadius.circular(AppRadius.xl),
          ),
          child: Column(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(color: order.status.color, shape: BoxShape.circle),
                child: const Icon(Icons.check, color: Colors.white, size: 24),
              ),
              const SizedBox(height: AppSpacing.sm + 6),
              Text(
                order.status.label.toUpperCase(),
                style: TextStyle(fontSize: 19, fontWeight: FontWeight.w700, letterSpacing: 0.5, color: order.status.color),
              ),
              const SizedBox(height: 8),
              Text(
                _heroMessage(order),
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium,
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.lg),

        if (order.issues.isNotEmpty) ...[
          Text('SIGNALEMENTS', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
          const SizedBox(height: AppSpacing.sm + 4),
          for (final issue in order.issues) ...[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(AppSpacing.sm + 6),
              margin: const EdgeInsets.only(bottom: AppSpacing.sm),
              decoration: BoxDecoration(
                color: issue.isResolved ? AppColors.border.withValues(alpha: 0.2) : AppColors.errorTint,
                borderRadius: BorderRadius.circular(AppRadius.md),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    issue.isResolved ? Icons.check_circle_outline : Icons.error_outline,
                    size: 18,
                    color: issue.isResolved ? AppColors.textSecondary : AppColors.error,
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          issue.category.label,
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: issue.isResolved ? AppColors.textSecondary : AppColors.error,
                          ),
                        ),
                        if (issue.description != null) ...[
                          const SizedBox(height: 2),
                          Text(issue.description!, style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5)),
                        ],
                        const SizedBox(height: 2),
                        Text(issue.isResolved ? 'Résolu' : 'En attente de traitement', style: theme.textTheme.labelSmall),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.sm),
        ],

        // Progression
        Text('PROGRESSION', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
        const SizedBox(height: AppSpacing.sm + 4),
        for (var i = 0; i < canonicalSteps.length; i++)
          _TimelineRow(
            status: canonicalSteps[i],
            isDone: currentIndex >= 0 && i <= currentIndex,
            isLast: i == canonicalSteps.length - 1,
            time: _timeFor(canonicalSteps[i]),
          ),

        const SizedBox(height: AppSpacing.lg),

        // Articles
        Text('ARTICLES', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
        const SizedBox(height: AppSpacing.sm + 4),
        Container(
          decoration: BoxDecoration(
            color: theme.cardTheme.color,
            border: Border.all(color: theme.dividerTheme.color!),
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
          child: Column(
            children: [
              for (final item in order.items)
                Container(
                  padding: const EdgeInsets.symmetric(vertical: 13),
                  decoration: BoxDecoration(
                    border: Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text('${item.name} × ${item.quantity}', style: theme.textTheme.bodyLarge?.copyWith(fontSize: 14)),
                      Text('${_formatFcfa(item.subtotalFcfa)} F', style: const TextStyle(fontWeight: FontWeight.w500)),
                    ],
                  ),
                ),
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 15),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text('Total', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
                    Text(
                      '${_formatFcfa(order.totalFcfa)} FCFA',
                      style: theme.textTheme.headlineSmall?.copyWith(fontSize: 18),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AppSpacing.md),

        Row(
          children: [
            Expanded(
              child: _DateCard(label: 'Déposée le', date: dateFormat.format(order.droppedOffAt)),
            ),
            const SizedBox(width: AppSpacing.sm + 2),
            if (order.expectedAt != null)
              Expanded(
                child: _DateCard(label: 'Retrait prévu', date: dateFormat.format(order.expectedAt!)),
              ),
          ],
        ),
        const SizedBox(height: AppSpacing.lg),

        SizedBox(
          width: double.infinity,
          height: 48,
          child: OutlinedButton.icon(
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => ReportIssueScreen(orderId: order.id, orderNumber: order.orderNumber),
              ),
            ),
            icon: const Icon(Icons.flag_outlined, size: 18, color: AppColors.error),
            label: const Text('Signaler un problème', style: TextStyle(color: AppColors.error, fontWeight: FontWeight.w600)),
            style: OutlinedButton.styleFrom(side: const BorderSide(color: Color(0xFFFECACA))),
          ),
        ),
      ],
    );
  }

  String _heroMessage(OrderModel order) => switch (order.status) {
        OrderStatus.prete => 'Votre commande est prête à être récupérée.',
        OrderStatus.recuperee => 'Cette commande a été récupérée. Merci de votre confiance !',
        OrderStatus.traitement => 'Vos vêtements sont en cours de traitement.',
        OrderStatus.attente => 'Votre commande est en attente.',
        OrderStatus.annulee => 'Cette commande a été annulée.',
        OrderStatus.recue => 'Votre commande a bien été enregistrée.',
      };

  String? _timeFor(OrderStatus status) {
    final event = order.history.where((h) => h.status == status).firstOrNull;
    if (event == null) return null;
    return DateFormat('d MMM · HH:mm', 'fr_FR').format(event.at);
  }

  String _formatFcfa(int value) {
    final s = value.toString();
    final buffer = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i != 0 && (s.length - i) % 3 == 0) buffer.write(' ');
      buffer.write(s[i]);
    }
    return buffer.toString();
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.status, required this.isDone, required this.isLast, this.time});

  final OrderStatus status;
  final bool isDone;
  final bool isLast;
  final String? time;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = isDone ? AppColors.success : AppColors.border;

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Column(
            children: [
              Container(
                width: 22,
                height: 22,
                decoration: BoxDecoration(color: isDone ? color : Colors.white, shape: BoxShape.circle, border: Border.all(color: color, width: 2)),
                child: isDone ? const Icon(Icons.check, size: 12, color: Colors.white) : null,
              ),
              if (!isLast) Expanded(child: Container(width: 2, color: color)),
            ],
          ),
          const SizedBox(width: AppSpacing.sm + 6),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.only(bottom: AppSpacing.md),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    status.label,
                    style: theme.textTheme.bodyLarge?.copyWith(fontSize: 14, fontWeight: isDone ? FontWeight.w600 : FontWeight.w400, color: isDone ? null : AppColors.textMuted),
                  ),
                  if (time != null) ...[
                    const SizedBox(height: 2),
                    Text(time!, style: theme.textTheme.labelSmall),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _DateCard extends StatelessWidget {
  const _DateCard({required this.label, required this.date});

  final String label;
  final String date;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sm + 6),
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.md),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: theme.textTheme.labelSmall),
          const SizedBox(height: 4),
          Text(date, style: const TextStyle(fontWeight: FontWeight.w500, fontSize: 14)),
        ],
      ),
    );
  }
}
