import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/link.dart';

import '../../../core/network/api_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/app_page_route.dart';
import '../../../core/widgets/error_state_view.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../../../shared/models/order_summary.dart';
import '../../orders/presentation/order_detail_screen.dart';
import '../../pressings/presentation/pressing_contact.dart';
import '../domain/pickup_repository.dart';
import 'pickups_controller.dart';

/// Suivi d'une demande de ramassage : statut, créneau, adresse, photos ;
/// le client peut l'annuler tant que le linge n'est pas ramassé.
class PickupRequestDetailScreen extends ConsumerStatefulWidget {
  const PickupRequestDetailScreen({super.key, required this.requestId});

  final int requestId;

  @override
  ConsumerState<PickupRequestDetailScreen> createState() =>
      _PickupRequestDetailScreenState();
}

class _PickupRequestDetailScreenState
    extends ConsumerState<PickupRequestDetailScreen> {
  bool _cancelling = false;

  Future<void> _cancel(PickupRequest request) async {
    final confirmed = await showAppConfirm(
      context,
      title: 'Annuler la demande ?',
      message: request.status == PickupStatus.accepted
          ? '${request.pressingName} ne passera pas ${request.scheduleLabel}.'
          : request.openToZone
          ? 'Votre demande aux pressings de ${request.commune} sera retirée.'
          : 'Votre demande à ${request.pressingName} sera retirée.',
      confirmLabel: 'Annuler la demande',
      cancelLabel: 'Garder',
      tone: AppConfirmTone.danger,
      icon: Icons.event_busy_outlined,
    );
    if (!confirmed || !mounted) return;

    setState(() => _cancelling = true);
    try {
      await ref.read(pickupRepositoryProvider).cancel(request.id);
      if (!mounted) return;
      ref.invalidate(pickupRequestProvider(request.id));
      ref.invalidate(pickupRequestsProvider);
      showAppToast(context, 'Demande annulée.');
    } catch (error) {
      if (!mounted) return;
      showAppToast(context, apiErrorMessage(error), type: AppToastType.error);
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final requestAsync = ref.watch(pickupRequestProvider(widget.requestId));

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Demande de ramassage'),
      body: SafeArea(
        child: requestAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorStateView(
            message: apiErrorMessage(e),
            onRetry: () =>
                ref.invalidate(pickupRequestProvider(widget.requestId)),
          ),
          data: (request) => RefreshIndicator(
            onRefresh: () =>
                ref.refresh(pickupRequestProvider(widget.requestId).future),
            child: _buildBody(context, request),
          ),
        ),
      ),
    );
  }

  Widget _buildBody(BuildContext context, PickupRequest request) {
    final theme = Theme.of(context);
    final phone = request.pressingPhone;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      children: [
        PickupStatusBanner(request: request),
        const SizedBox(height: AppSpacing.md),
        _InfoCard(
          children: [
            _InfoLine(
              icon: Icons.store_outlined,
              label: 'Pressing',
              value: request.openToZone
                  ? 'Le premier disponible à ${request.commune}'
                  : request.pressingName,
            ),
            _InfoLine(
              icon: Icons.schedule,
              label: request.status == PickupStatus.accepted
                  ? 'Passage prévu'
                  : 'Créneau demandé',
              value: _capitalize(request.scheduleLabel),
            ),
            _InfoLine(
              icon: Icons.location_on_outlined,
              label: request.addressLabel,
              value: '${request.place}\n${request.landmark}',
            ),
            if (request.itemsEstimate != null)
              _InfoLine(
                icon: Icons.checkroom_outlined,
                label: 'Pièces',
                value: 'Environ ${request.itemsEstimate}',
              ),
            _InfoLine(
              icon: Icons.payments_outlined,
              label: 'Frais de ramassage',
              value: request.openToZone
                  ? 'Ceux du pressing qui accepte'
                  : request.pickupFeeFcfa > 0
                  ? '${formatFcfa(request.pickupFeeFcfa)} F, avec la commande'
                  : 'Gratuit',
              isLast: (request.note ?? '').isEmpty,
            ),
            if ((request.note ?? '').isNotEmpty)
              _InfoLine(
                icon: Icons.chat_bubble_outline,
                label: 'Votre message',
                value: request.note!,
                isLast: true,
              ),
          ],
        ),
        if (request.photos.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          Text(
            'PHOTOS DU LINGE',
            style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final photo in request.photos)
                Link(
                  uri: Uri.parse(photo.url),
                  target: LinkTarget.blank,
                  builder: (context, followLink) => InkWell(
                    onTap: followLink,
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: ClipRRect(
                      borderRadius: BorderRadius.circular(AppRadius.md),
                      child: Image.network(
                        photo.thumbUrl,
                        width: 80,
                        height: 80,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => Container(
                          width: 80,
                          height: 80,
                          color: context.primaryTint,
                          child: const Icon(Icons.image_outlined),
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        ],
        const SizedBox(height: AppSpacing.lg),
        if (request.orderId != null) ...[
          SizedBox(
            width: double.infinity,
            height: 48,
            child: FilledButton.icon(
              onPressed: () => Navigator.of(context).push(
                AppPageRoute(
                  builder: (_) => OrderDetailScreen(orderId: request.orderId!),
                ),
              ),
              icon: const Icon(Icons.receipt_long_outlined, size: 18),
              label: const Text('Voir la commande'),
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        if (phone != null && phone.isNotEmpty)
          SizedBox(
            width: double.infinity,
            height: 48,
            child: OutlinedButton.icon(
              onPressed: () =>
                  openExternalLink(context, Uri(scheme: 'tel', path: phone)),
              icon: const Icon(Icons.call_outlined, size: 18),
              label: Text('Appeler ${request.pressingName}'),
            ),
          ),
        if (request.canCancel) ...[
          const SizedBox(height: AppSpacing.sm),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: TextButton.icon(
              onPressed: _cancelling ? null : () => _cancel(request),
              icon: const Icon(Icons.close, size: 18),
              label: const Text('Annuler la demande'),
              style: TextButton.styleFrom(foregroundColor: AppColors.error),
            ),
          ),
        ],
      ],
    );
  }
}

String _capitalize(String text) =>
    text.isEmpty ? text : text[0].toUpperCase() + text.substring(1);

/// Bandeau de statut d'une demande, avec ce qui va se passer ensuite.
class PickupStatusBanner extends StatelessWidget {
  const PickupStatusBanner({super.key, required this.request});

  final PickupRequest request;

  @override
  Widget build(BuildContext context) {
    final (
      IconData icon,
      Color color,
      Color tint,
      String text,
    ) = switch (request.status) {
      PickupStatus.pending => (
        Icons.hourglass_top_rounded,
        AppColors.warningText,
        context.warningTint,
        request.openToZone
            ? 'Votre demande est partie aux pressings de ${request.commune}. Le premier qui accepte viendra : vous serez prévenu ici.'
            : 'Le pressing va confirmer votre créneau. Vous serez prévenu ici.',
      ),
      PickupStatus.accepted => (
        Icons.local_shipping_outlined,
        AppColors.successText,
        context.successTint,
        request.rescheduled
            ? 'Le pressing a choisi un autre créneau : ${request.scheduleLabel}. S\'il ne vous convient pas, annulez la demande.'
            : 'Le pressing passera chercher votre linge ${request.scheduleLabel}.',
      ),
      PickupStatus.refused => (
        Icons.do_not_disturb_alt_outlined,
        AppColors.error,
        context.errorTint,
        request.refusalReason != null
            ? 'Motif : ${request.refusalReason}. Vous pouvez choisir un autre créneau.'
            : 'Vous pouvez choisir un autre créneau.',
      ),
      PickupStatus.cancelled => (
        Icons.close_rounded,
        context.textMuted,
        context.primaryTint,
        'Vous avez annulé cette demande.',
      ),
      PickupStatus.expired => (
        Icons.timer_off_outlined,
        context.textMuted,
        context.primaryTint,
        request.openToZone
            ? 'Aucun pressing de ${request.commune} n\'a pris la demande à temps. Vous pouvez en envoyer une nouvelle.'
            : 'Le pressing n\'a pas répondu à temps. Vous pouvez envoyer une nouvelle demande.',
      ),
      PickupStatus.pickedUp => (
        Icons.check_circle_outline,
        AppColors.successText,
        context.successTint,
        'Votre linge est au pressing. Il saisira le détail et le prix ; vous suivez la suite dans la commande.',
      ),
      PickupStatus.missed => (
        Icons.person_off_outlined,
        context.textMuted,
        context.primaryTint,
        'Le pressing est passé mais n\'a trouvé personne. Vous pouvez envoyer une nouvelle demande.',
      ),
    };

    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: tint,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 24),
          const SizedBox(width: AppSpacing.sm + 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  request.statusLabel,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: color,
                  ),
                ),
                const SizedBox(height: 3),
                Text(text, style: const TextStyle(fontSize: 13.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(children: children),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({
    required this.icon,
    required this.label,
    required this.value,
    this.isLast = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 12),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 20, color: theme.textTheme.bodyMedium?.color),
          const SizedBox(width: AppSpacing.sm + 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                ),
                const SizedBox(height: 2),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
