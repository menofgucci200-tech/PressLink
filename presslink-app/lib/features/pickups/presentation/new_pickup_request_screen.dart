import 'dart:typed_data';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

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
import '../../pressings/domain/pressing_repository.dart';
import '../../pressings/presentation/pressings_controller.dart';
import '../domain/pickup_repository.dart';
import 'pickup_request_detail_screen.dart';
import 'pickups_controller.dart';

/// Faire ramasser mon linge : le client choisit son pressing, l'adresse,
/// le jour et le créneau ; photos et message sont facultatifs.
class NewPickupRequestScreen extends ConsumerStatefulWidget {
  const NewPickupRequestScreen({super.key, this.pressingId});

  /// Pressing choisi d'avance (depuis sa fiche).
  final int? pressingId;

  @override
  ConsumerState<NewPickupRequestScreen> createState() =>
      _NewPickupRequestScreenState();
}

class _PickedPhoto {
  const _PickedPhoto(this.file, this.bytes);

  final XFile file;
  final Uint8List bytes;
}

class _NewPickupRequestScreenState
    extends ConsumerState<NewPickupRequestScreen> {
  static const _maxItems = 200;

  late int? _pressingId = widget.pressingId;
  int? _addressId;
  String? _date;
  PickupSlot? _slot;
  int _items = 0;
  final _noteController = TextEditingController();
  final List<_PickedPhoto> _photos = [];
  bool _sending = false;
  String? _error;

  @override
  void dispose() {
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _pickPhotos(ImageSource source) async {
    final room = PickupRepository.maxPhotos - _photos.length;
    if (room <= 0) return;
    try {
      final List<XFile> picked;
      if (source == ImageSource.camera) {
        final shot = await ImagePicker().pickImage(
          source: source,
          maxWidth: 1600,
          imageQuality: 85,
        );
        picked = shot == null ? const [] : [shot];
      } else {
        picked = await ImagePicker().pickMultiImage(
          maxWidth: 1600,
          imageQuality: 85,
          limit: room,
        );
      }
      final kept = <_PickedPhoto>[];
      for (final file in picked.take(room)) {
        kept.add(_PickedPhoto(file, await file.readAsBytes()));
      }
      if (mounted) setState(() => _photos.addAll(kept));
    } catch (_) {
      if (!mounted) return;
      showAppToast(
        context,
        source == ImageSource.camera
            ? 'Impossible d\'accéder à l\'appareil photo. Vérifiez les autorisations.'
            : 'Impossible d\'accéder à la galerie. Vérifiez les autorisations.',
        type: AppToastType.error,
      );
    }
  }

  Future<void> _submit(PressingModel pressing) async {
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
    final repository = ref.read(pickupRepositoryProvider);
    PickupRequest request;
    try {
      request = await repository.create(
        pressingId: pressing.id,
        addressId: _addressId!,
        date: _date!,
        slot: _slot!,
        itemsEstimate: _items > 0 ? _items : null,
        note: _noteController.text,
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _error = apiErrorMessage(error);
      });
      // Le créneau a pu être pris entre-temps : la liste est rafraîchie.
      ref.invalidate(pickupAvailabilityProvider(pressing.id));
      return;
    }

    // La demande est partie ; une photo refusée ne l'annule pas.
    var failedPhotos = 0;
    for (final photo in _photos) {
      try {
        await repository.addPhoto(request.id, photo.file);
      } catch (_) {
        failedPhotos++;
      }
    }

    if (!mounted) return;
    ref.invalidate(pickupRequestsProvider);
    showAppToast(
      context,
      failedPhotos == 0
          ? 'Demande envoyée à ${pressing.name}.'
          : 'Demande envoyée, mais $failedPhotos photo(s) n\'ont pas pu être jointes.',
      type: failedPhotos == 0 ? AppToastType.success : AppToastType.warning,
    );
    Navigator.of(context).pushReplacement(
      AppPageRoute(
        builder: (_) => PickupRequestDetailScreen(requestId: request.id),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final pressingsAsync = ref.watch(myPressingsProvider);

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Faire ramasser mon linge'),
      body: SafeArea(
        child: pressingsAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorStateView(
            message: apiErrorMessage(e),
            onRetry: () => ref.invalidate(myPressingsProvider),
          ),
          data: (pressings) {
            final eligible = pressings.where((p) => p.offersPickup).toList();
            if (eligible.isEmpty) {
              return const _EmptyMessage(
                icon: Icons.local_shipping_outlined,
                text:
                    'Aucun de vos pressings ne propose encore le ramassage à domicile.',
              );
            }
            final pressing = eligible.firstWhere(
              (p) => p.id == _pressingId,
              orElse: () => eligible.first,
            );
            return _buildForm(context, eligible, pressing);
          },
        ),
      ),
    );
  }

  Widget _buildForm(
    BuildContext context,
    List<PressingModel> eligible,
    PressingModel pressing,
  ) {
    final theme = Theme.of(context);
    final services = pressing.deliveryServices!;
    final addressesAsync = ref.watch(addressesControllerProvider);
    final daysAsync = ref.watch(pickupAvailabilityProvider(pressing.id));

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      children: [
        // ===== Pressing =====
        if (eligible.length > 1) ...[
          const FormSectionTitle('PRESSING'),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: [
              for (final p in eligible)
                ChoiceChip(
                  label: Text(p.name),
                  selected: p.id == pressing.id,
                  onSelected: (_) => setState(() {
                    _pressingId = p.id;
                    _addressId = null;
                    _date = null;
                    _slot = null;
                  }),
                ),
            ],
          ),
        ] else
          Text(
            'Votre demande part chez ${pressing.name}.',
            style: theme.textTheme.bodyMedium,
          ),

        // ===== Adresse =====
        const FormSectionTitle('OÙ PASSER ?'),
        addressesAsync.when(
          loading: () => const LinearProgressIndicator(),
          error: (e, _) => TextButton.icon(
            onPressed: () => ref.invalidate(addressesControllerProvider),
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Charger mes adresses'),
          ),
          data: (addresses) {
            final served = addresses
                .where((a) => services.communes.contains(a.commune))
                .toList();
            // Par défaut : l'adresse par défaut si elle est desservie.
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
                    served: services.communes.contains(address.commune),
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
                    '${pressing.name} passe à : ${services.communes.join(', ')}.',
                    style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5),
                  ),
              ],
            );
          },
        ),

        // ===== Jour et créneau =====
        const FormSectionTitle('QUAND ?'),
        daysAsync.when(
          loading: () => const LinearProgressIndicator(),
          error: (e, _) => TextButton.icon(
            onPressed: () =>
                ref.invalidate(pickupAvailabilityProvider(pressing.id)),
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Charger les créneaux'),
          ),
          data: (days) {
            if (days.isEmpty) {
              return Text(
                'Aucun créneau disponible pour le moment.',
                style: theme.textTheme.bodyMedium,
              );
            }
            final day = days.where((d) => d.date == _date).firstOrNull;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                SizedBox(
                  height: 40,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: days.length,
                    separatorBuilder: (_, _) =>
                        const SizedBox(width: AppSpacing.sm),
                    itemBuilder: (_, i) => ChoiceChip(
                      label: Text(days[i].label),
                      selected: days[i].date == _date,
                      onSelected: (_) => setState(() {
                        _date = days[i].date;
                        if (!days[i].slots.contains(_slot)) {
                          _slot = days[i].slots.length == 1
                              ? days[i].slots.first
                              : null;
                        }
                      }),
                    ),
                  ),
                ),
                if (day != null) ...[
                  const SizedBox(height: AppSpacing.sm + 4),
                  Wrap(
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: [
                      for (final slot in day.slots)
                        ChoiceChip(
                          avatar: const Icon(Icons.schedule, size: 16),
                          label: Text(slot.label),
                          selected: slot == _slot,
                          onSelected: (_) => setState(() => _slot = slot),
                        ),
                    ],
                  ),
                ],
              ],
            );
          },
        ),

        // ===== Linge =====
        const FormSectionTitle('VOTRE LINGE (FACULTATIF)'),
        Row(
          children: [
            const Expanded(
              child: Text(
                'Nombre de pièces (environ)',
                style: TextStyle(fontSize: 14),
              ),
            ),
            IconButton.outlined(
              onPressed: _items > 0 ? () => setState(() => _items--) : null,
              icon: const Icon(Icons.remove, size: 18),
              tooltip: 'Moins',
            ),
            SizedBox(
              width: 44,
              child: Text(
                _items > 0 ? '$_items' : '—',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            IconButton.outlined(
              onPressed: _items < _maxItems
                  ? () => setState(() => _items++)
                  : null,
              icon: const Icon(Icons.add, size: 18),
              tooltip: 'Plus',
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.md),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            for (var i = 0; i < _photos.length; i++)
              Stack(
                clipBehavior: Clip.none,
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: Image.memory(
                      _photos[i].bytes,
                      width: 72,
                      height: 72,
                      fit: BoxFit.cover,
                    ),
                  ),
                  Positioned(
                    top: -8,
                    right: -8,
                    child: IconButton.filledTonal(
                      visualDensity: VisualDensity.compact,
                      iconSize: 16,
                      onPressed: () => setState(() => _photos.removeAt(i)),
                      icon: const Icon(Icons.close),
                      tooltip: 'Retirer la photo',
                    ),
                  ),
                ],
              ),
          ],
        ),
        if (_photos.length < PickupRepository.maxPhotos) ...[
          if (_photos.isNotEmpty) const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickPhotos(ImageSource.camera),
                  icon: const Icon(Icons.photo_camera_outlined, size: 18),
                  label: const Text('Photo'),
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => _pickPhotos(ImageSource.gallery),
                  icon: const Icon(Icons.photo_library_outlined, size: 18),
                  label: const Text('Galerie'),
                ),
              ),
            ],
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        Text(
          'Message pour le pressing',
          style: theme.textTheme.bodyMedium?.copyWith(
            fontSize: 12,
            fontWeight: FontWeight.w500,
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: _noteController,
          minLines: 3,
          maxLines: 5,
          maxLength: 500,
          textCapitalization: TextCapitalization.sentences,
          decoration: const InputDecoration(
            hintText: 'ex. Sonnez au portail vert, 2 costumes à nettoyer',
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
          child: Text(
            services.pickupFeeFcfa > 0
                ? 'Frais de ramassage : ${formatFcfa(services.pickupFeeFcfa)} F, à régler avec votre commande.'
                : 'Ramassage gratuit.',
            style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w600),
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
            onPressed: _sending ? null : () => _submit(pressing),
            child: _sending
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text('Envoyer la demande'),
          ),
        ),
        const SizedBox(height: AppSpacing.md),
      ],
    );
  }
}

/// Titre de section des formulaires de ramassage et de livraison.
class FormSectionTitle extends StatelessWidget {
  const FormSectionTitle(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(
        top: AppSpacing.lg,
        bottom: AppSpacing.sm + 2,
      ),
      child: Text(
        text,
        style: Theme.of(
          context,
        ).textTheme.labelSmall?.copyWith(letterSpacing: 0.6),
      ),
    );
  }
}

/// Adresse à choisir, grisée quand le pressing ne dessert pas sa commune.
class AddressOption extends StatelessWidget {
  const AddressOption({
    super.key,
    required this.address,
    required this.served,
    required this.selected,
    required this.onTap,
  });

  final CustomerAddress address;
  final bool served;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Opacity(
      opacity: served ? 1 : 0.55,
      child: Material(
        color: selected ? context.primaryTint : theme.cardTheme.color,
        shape: RoundedRectangleBorder(
          side: BorderSide(
            color: selected ? AppColors.primary : theme.dividerTheme.color!,
            width: selected ? 1.5 : 1,
          ),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: served ? onTap : null,
          child: Padding(
            padding: const EdgeInsets.all(AppSpacing.sm + 4),
            child: Row(
              children: [
                Icon(
                  selected
                      ? Icons.radio_button_checked
                      : Icons.radio_button_off,
                  size: 20,
                  color: selected ? AppColors.primary : context.textMuted,
                ),
                const SizedBox(width: AppSpacing.sm + 4),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        address.label,
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      Text(
                        served
                            ? '${address.place} · ${address.landmark}'
                            : '${address.place} · hors de la zone du pressing',
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          fontSize: 12.5,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _EmptyMessage extends StatelessWidget {
  const _EmptyMessage({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 36, color: context.textMuted),
            const SizedBox(height: AppSpacing.sm),
            Text(
              text,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}
