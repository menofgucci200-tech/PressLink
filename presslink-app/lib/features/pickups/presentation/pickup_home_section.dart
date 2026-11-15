import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_page_route.dart';
import '../domain/pickup_repository.dart';
import 'pickup_request_detail_screen.dart';
import 'pickups_controller.dart';

/// Accueil : les demandes de ramassage en cours. La demande elle-même se
/// fait depuis le bouton « + » de la barre du bas.
class PickupHomeSection extends ConsumerWidget {
  const PickupHomeSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!AppConfig.deliveryEnabled) return const SizedBox.shrink();

    final open = (ref.watch(pickupRequestsProvider).valueOrNull ?? const [])
        .where((r) => r.status.isOpen)
        .toList();
    if (open.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final (i, request) in open.indexed) ...[
            if (i > 0) const SizedBox(height: AppSpacing.sm),
            _OpenRequestCard(request: request),
          ],
        ],
      ),
    );
  }
}

class _OpenRequestCard extends ConsumerWidget {
  const _OpenRequestCard({required this.request});

  final PickupRequest request;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final accepted = request.status == PickupStatus.accepted;
    final color = accepted ? AppColors.successText : AppColors.warningText;

    return Material(
      color: theme.cardTheme.color,
      shape: RoundedRectangleBorder(
        side: BorderSide(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context)
            .push(
              AppPageRoute(
                builder: (_) =>
                    PickupRequestDetailScreen(requestId: request.id),
              ),
            )
            .then((_) => ref.invalidate(pickupRequestsProvider)),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            children: [
              Icon(
                accepted
                    ? Icons.local_shipping_outlined
                    : Icons.hourglass_top_rounded,
                color: color,
              ),
              const SizedBox(width: AppSpacing.sm + 4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      request.openToZone
                          ? 'En attente d\'un pressing de ${request.commune}'
                          : '${request.statusLabel} · ${request.pressingName}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: color,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${request.scheduleLabel[0].toUpperCase()}${request.scheduleLabel.substring(1)} · ${request.addressLabel}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, size: 18, color: context.textMuted),
            ],
          ),
        ),
      ),
    );
  }
}
