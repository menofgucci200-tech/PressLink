import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_page_route.dart';
import '../../pressings/presentation/pressings_controller.dart';
import '../domain/pickup_repository.dart';
import 'new_pickup_request_screen.dart';
import 'pickup_request_detail_screen.dart';
import 'pickups_controller.dart';

/// Accueil : « Faire ramasser mon linge » quand l'un des pressings du
/// client le propose, et les demandes en cours.
class PickupHomeSection extends ConsumerWidget {
  const PickupHomeSection({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (!AppConfig.deliveryEnabled) return const SizedBox.shrink();

    final offersPickup =
        ref
            .watch(myPressingsProvider)
            .valueOrNull
            ?.any((p) => p.offersPickup) ??
        false;
    final open = (ref.watch(pickupRequestsProvider).valueOrNull ?? const [])
        .where((r) => r.status.isOpen)
        .toList();

    if (!offersPickup && open.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (offersPickup)
            Material(
              color: AppColors.primary,
              borderRadius: BorderRadius.circular(AppRadius.lg),
              clipBehavior: Clip.antiAlias,
              child: InkWell(
                onTap: () => Navigator.of(context)
                    .push(
                      AppPageRoute(
                        builder: (_) => const NewPickupRequestScreen(),
                      ),
                    )
                    .then((_) => ref.invalidate(pickupRequestsProvider)),
                child: const Padding(
                  padding: EdgeInsets.all(AppSpacing.md),
                  child: Row(
                    children: [
                      Icon(
                        Icons.local_shipping_outlined,
                        color: Colors.white,
                        size: 28,
                      ),
                      SizedBox(width: AppSpacing.sm + 4),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              'Faire ramasser mon linge',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            SizedBox(height: 2),
                            Text(
                              'Votre pressing passe le chercher chez vous.',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Icon(Icons.chevron_right, color: Colors.white),
                    ],
                  ),
                ),
              ),
            ),
          for (final request in open) ...[
            const SizedBox(height: AppSpacing.sm),
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
                      '${request.statusLabel} · ${request.pressingName}',
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
