import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/app_page_route.dart';
import '../../../core/widgets/error_state_view.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../../../shared/models/order_summary.dart';
import '../../addresses/domain/address_repository.dart';
import '../../addresses/presentation/address_form_screen.dart';
import '../../addresses/presentation/addresses_controller.dart';
import '../../pickups/domain/pickup_repository.dart';
import '../../pickups/presentation/new_pickup_request_screen.dart';
import '../domain/order_repository.dart';
import 'orders_controller.dart';

/// Me faire livrer : pour une commande prête, le client choisit l'adresse,
/// le jour et le créneau ; il paie le reste et les frais au livreur.
class NewDeliveryScreen extends ConsumerStatefulWidget {
  const NewDeliveryScreen({super.key, required this.order});

  final OrderModel order;

  @override
  ConsumerState<NewDeliveryScreen> createState() => _NewDeliveryScreenState();
}

class _NewDeliveryScreenState extends ConsumerState<NewDeliveryScreen> {
  int? _addressId;
  String? _date;
  PickupSlot? _slot;
  final _noteController = TextEditingController();
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final missing = <String>[
      if (_addressId == null) 'l\'adresse',
      if (_date == null || _slot == null) 'le jour et le créneau',
    ];
    if (missing.isNotEmpty) {
      setState(() => _error = 'Choisissez ${missing.join(' et ')}.');
      return;
    }

    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      await ref
          .read(orderRepositoryProvider)
          .requestDelivery(
            orderId: widget.order.id,
            addressId: _addressId!,
            date: _date!,
            slot: _slot!,
            note: _noteController.text,
          );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _error = apiErrorMessage(error);
      });
      // Le créneau a pu passer entre-temps : la liste est rafraîchie.
      ref.invalidate(deliveryOfferProvider(widget.order.id));
      return;
    }

    if (!mounted) return;
    ref.invalidate(orderDetailProvider(widget.order.id));
    ref.invalidate(ordersProvider);
    showAppToast(context, 'Livraison demandée à ${widget.order.pressingName}.');
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final offerAsync = ref.watch(deliveryOfferProvider(widget.order.id));

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Me faire livrer'),
      body: SafeArea(
        child: offerAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorStateView(
            message: apiErrorMessage(e),
            onRetry: () =>
                ref.invalidate(deliveryOfferProvider(widget.order.id)),
          ),
          data: (offer) => offer.available
              ? _buildForm(context, offer)
              : Center(
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.lg),
                    child: Text(
                      offer.reason ?? 'La livraison n\'est pas disponible.',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ),
                ),
        ),
      ),
    );
  }

  Widget _buildForm(BuildContext context, DeliveryOffer offer) {
    final theme = Theme.of(context);
    final order = widget.order;
    final addressesAsync = ref.watch(addressesControllerProvider);
    final toPay = order.balanceDueFcfa + offer.feeFcfa;

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      children: [
        Text(
          '${order.pressingName} vous apporte la commande ${order.orderNumber}.',
          style: theme.textTheme.bodyMedium,
        ),

        // ===== Adresse =====
        const FormSectionTitle('OÙ LIVRER ?'),
        addressesAsync.when(
          loading: () => const LinearProgressIndicator(),
          error: (e, _) => TextButton.icon(
            onPressed: () => ref.invalidate(addressesControllerProvider),
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Charger mes adresses'),
          ),
          data: (addresses) {
            final served = addresses
                .where((a) => offer.communes.contains(a.commune))
                .toList();
            if (_addressId == null && served.isNotEmpty) {
              final preferred = served.firstWhere(
                (a) => a.isDefault,
                orElse: () => served.first,
              );
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted && _addressId == null) {
                  setState(() => _addressId = preferred.id);
                }
              });
            }
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final address in addresses) ...[
                  AddressOption(
                    address: address,
                    served: offer.communes.contains(address.commune),
                    selected: address.id == _addressId,
                    onTap: () => setState(() => _addressId = address.id),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                ],
                if (addresses.length < AddressRepository.maxAddresses)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () => Navigator.of(context).push(
                        AppPageRoute(builder: (_) => const AddressFormScreen()),
                      ),
                      icon: const Icon(Icons.add, size: 18),
                      label: const Text('Ajouter une adresse'),
                    ),
                  ),
                if (served.isEmpty)
                  Text(
                    '${order.pressingName} livre à : ${offer.communes.join(', ')}.',
                    style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5),
                  ),
              ],
            );
          },
        ),

        // ===== Jour et créneau =====
        const FormSectionTitle('QUAND ?'),
        if (offer.days.isEmpty)
          Text(
            'Aucun créneau disponible pour le moment.',
            style: theme.textTheme.bodyMedium,
          )
        else
          _DayAndSlotPicker(
            days: offer.days,
            date: _date,
            slot: _slot,
            onDay: (day) => setState(() {
              _date = day.date;
              if (!day.slots.contains(_slot)) {
                _slot = day.slots.length == 1 ? day.slots.first : null;
              }
            }),
            onSlot: (slot) => setState(() => _slot = slot),
          ),

        const FormSectionTitle('MESSAGE POUR LE LIVREUR (FACULTATIF)'),
        TextField(
          controller: _noteController,
          minLines: 2,
          maxLines: 4,
          maxLength: 500,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            hintText: 'ex. Appelez en arrivant, portail vert',
            contentPadding: EdgeInsets.all(AppSpacing.md),
          ),
        ),

        // ===== Récapitulatif =====
        const SizedBox(height: AppSpacing.sm),
        Container(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: context.primaryTint,
            borderRadius: BorderRadius.circular(AppRadius.lg),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                offer.feeFcfa > 0
                    ? 'Frais de livraison : ${formatFcfa(offer.feeFcfa)} F'
                    : 'Livraison gratuite',
                style: const TextStyle(
                  fontSize: 13.5,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                toPay > 0
                    ? 'À payer au livreur : ${formatFcfa(toPay)} F, en espèces ou par mobile money.'
                    : 'Rien à payer au livreur : la commande est réglée.',
                style: const TextStyle(fontSize: 13.5),
              ),
            ],
          ),
        ),
        if (_error != null) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            _error!,
            style: TextStyle(color: theme.colorScheme.error, fontSize: 13),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        SizedBox(
          width: double.infinity,
          height: 52,
          child: ElevatedButton(
            onPressed: _sending ? null : _submit,
            child: _sending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Demander la livraison'),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
      ],
    );
  }
}

class _DayAndSlotPicker extends StatelessWidget {
  const _DayAndSlotPicker({
    required this.days,
    required this.date,
    required this.slot,
    required this.onDay,
    required this.onSlot,
  });

  final List<PickupDay> days;
  final String? date;
  final PickupSlot? slot;
  final ValueChanged<PickupDay> onDay;
  final ValueChanged<PickupSlot> onSlot;

  @override
  Widget build(BuildContext context) {
    final day = days.where((d) => d.date == date).firstOrNull;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          height: 40,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: days.length,
            separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
            itemBuilder: (_, i) => ChoiceChip(
              label: Text(days[i].label),
              selected: days[i].date == date,
              onSelected: (_) => onDay(days[i]),
            ),
          ),
        ),
        if (day != null) ...[
          const SizedBox(height: AppSpacing.sm + 4),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final s in day.slots)
                ChoiceChip(
                  avatar: const Icon(Icons.schedule, size: 16),
                  label: Text(s.label),
                  selected: s == slot,
                  onSelected: (_) => onSlot(s),
                ),
            ],
          ),
        ],
      ],
    );
  }
}
