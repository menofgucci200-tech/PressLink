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
import '../domain/pickup_repository.dart';
import 'pickup_request_detail_screen.dart';
import 'pickups_controller.dart';

/// Faire ramasser mon linge : le client choisit l'adresse, puis l'un des
/// pressings qui passent dans sa commune, ou laisse la demande au premier
/// pressing disponible de la zone. Il n'a pas besoin d'être déjà client d'un
/// pressing. Photos et message sont facultatifs.
class NewPickupRequestScreen extends ConsumerStatefulWidget {
  const NewPickupRequestScreen({super.key, this.pressingId});

  /// Pressing choisi d'avance (depuis sa fiche), s'il passe à l'adresse.
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

  int? _addressId;

  /// Pressing choisi ; null = le premier pressing disponible de la zone.
  late int? _pressingId = widget.pressingId;
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

  void _selectAddress(int addressId) {
    if (addressId == _addressId) return;
    setState(() {
      _addressId = addressId;
      _date = null;
      _slot = null;
      _error = null;
    });
  }

  void _selectPressing(int? pressingId) {
    setState(() {
      _pressingId = pressingId;
      _date = null;
      _slot = null;
      _error = null;
    });
  }

  Future<void> _submit(PickupOptions options, PickupOption? pressing) async {
    if (_date == null || _slot == null) {
      setState(() => _error = 'Choisissez le jour et le créneau.');
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
        pressingId: pressing?.id,
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
      // Le créneau a pu passer entre-temps : la liste est rafraîchie.
      ref.invalidate(pickupOptionsProvider(_addressId!));
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
      failedPhotos > 0
          ? 'Demande envoyée, mais $failedPhotos photo(s) n\'ont pas pu être jointes.'
          : pressing == null
          ? 'Demande envoyée aux pressings de ${options.commune}.'
          : 'Demande envoyée à ${pressing.name}.',
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
    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Faire ramasser mon linge'),
      body: SafeArea(
        child: ref
            .watch(addressesControllerProvider)
            .when(
              loading: () => const Center(child: CircularProgressIndicator()),
              error: (e, _) => ErrorStateView(
                message: apiErrorMessage(e),
                onRetry: () => ref.invalidate(addressesControllerProvider),
              ),
              data: (addresses) {
                if (addresses.isEmpty) {
                  return _EmptyMessage(
                    icon: Icons.location_on_outlined,
                    text:
                        'Ajoutez l\'adresse où passer chercher votre linge : nous vous montrerons les pressings qui passent chez vous.',
                    actionLabel: 'Ajouter une adresse',
                    onAction: () => Navigator.of(context).push(
                      AppPageRoute(builder: (_) => const AddressFormScreen()),
                    ),
                  );
                }
                // Par défaut : l'adresse par défaut du client.
                final addressId = addresses.any((a) => a.id == _addressId)
                    ? _addressId!
                    : addresses
                          .firstWhere(
                            (a) => a.isDefault,
                            orElse: () => addresses.first,
                          )
                          .id;
                if (addressId != _addressId) {
                  WidgetsBinding.instance.addPostFrameCallback((_) {
                    if (mounted && _addressId != addressId) {
                      setState(() => _addressId = addressId);
                    }
                  });
                }
                return _buildForm(context, addresses, addressId);
              },
            ),
      ),
    );
  }

  Widget _buildForm(
    BuildContext context,
    List<CustomerAddress> addresses,
    int addressId,
  ) {
    final theme = Theme.of(context);
    final optionsAsync = ref.watch(pickupOptionsProvider(addressId));

    return ListView(
      padding: const EdgeInsets.all(AppSpacing.md),
      keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
      children: [
        // ===== Adresse =====
        const FormSectionTitle('OÙ PASSER ?'),
        for (final address in addresses) ...[
          AddressOption(
            address: address,
            served: true,
            selected: address.id == addressId,
            onTap: () => _selectAddress(address.id),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        if (addresses.length < AddressRepository.maxAddresses)
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: () => Navigator.of(
                context,
              ).push(AppPageRoute(builder: (_) => const AddressFormScreen())),
              icon: const Icon(Icons.add, size: 18),
              label: const Text('Ajouter une adresse'),
            ),
          ),

        ...optionsAsync.when(
          loading: () => const [
            SizedBox(height: AppSpacing.lg),
            LinearProgressIndicator(),
          ],
          error: (e, _) => [
            const SizedBox(height: AppSpacing.md),
            TextButton.icon(
              onPressed: () => ref.invalidate(pickupOptionsProvider(addressId)),
              icon: const Icon(Icons.refresh, size: 18),
              label: const Text('Charger les pressings'),
            ),
          ],
          data: (options) => _buildChoices(context, theme, options),
        ),
      ],
    );
  }

  List<Widget> _buildChoices(
    BuildContext context,
    ThemeData theme,
    PickupOptions options,
  ) {
    if (options.pressings.isEmpty) {
      return [
        const SizedBox(height: AppSpacing.lg),
        _NoPressingHere(commune: options.commune),
      ];
    }

    // Un pressing choisi qui ne passe pas à cette adresse : retour à la zone.
    final pressing = options.pressings
        .where((p) => p.id == _pressingId)
        .firstOrNull;
    final days = pressing?.days ?? options.zoneDays;
    final day = days.where((d) => d.date == _date).firstOrNull;
    final zoneFee = options.zoneFeeMinFcfa == options.zoneFeeMaxFcfa
        ? (options.zoneFeeMinFcfa > 0
              ? '${formatFcfa(options.zoneFeeMinFcfa)} F'
              : 'gratuit')
        : 'de ${formatFcfa(options.zoneFeeMinFcfa)} à ${formatFcfa(options.zoneFeeMaxFcfa)} F selon le pressing';
    final count = options.pressings.length;

    return [
      // ===== Pressing =====
      const FormSectionTitle('QUEL PRESSING ?'),
      _ChoiceCard(
        selected: pressing == null,
        onTap: () => _selectPressing(null),
        leading: const CircleAvatar(
          backgroundColor: AppColors.primary,
          child: Icon(Icons.bolt_rounded, color: Colors.white),
        ),
        title: 'Le premier pressing disponible',
        subtitle:
            '$count pressing${count > 1 ? 's passent' : ' passe'} à ${options.commune}. '
            'Le premier qui accepte vient chercher votre linge. Frais : $zoneFee.',
      ),
      for (final option in options.pressings) ...[
        const SizedBox(height: AppSpacing.sm),
        _ChoiceCard(
          selected: option.id == pressing?.id,
          onTap: () => _selectPressing(option.id),
          leading: _PressingAvatar(option: option),
          title: option.name,
          badge: option.isMine ? 'Mon pressing' : null,
          subtitle: [
            if ((option.address ?? '').isNotEmpty) option.address!,
            option.feeFcfa > 0
                ? 'Frais : ${formatFcfa(option.feeFcfa)} F'
                : 'Ramassage gratuit',
          ].join(' · '),
        ),
      ],

      // ===== Jour et créneau =====
      const FormSectionTitle('QUAND ?'),
      if (days.isEmpty)
        Text(
          'Aucun créneau disponible pour le moment.',
          style: theme.textTheme.bodyMedium,
        )
      else ...[
        SizedBox(
          height: 40,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: days.length,
            separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
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
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
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
          pressing == null
              ? 'Votre demande part aux pressings de ${options.commune}. Frais de ramassage : $zoneFee, à régler avec votre commande. Vous saurez quel pressing vient dès qu\'il accepte.'
              : pressing.feeFcfa > 0
              ? 'Frais de ramassage : ${formatFcfa(pressing.feeFcfa)} F, à régler avec votre commande.'
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
          onPressed: _sending ? null : () => _submit(options, pressing),
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
    ];
  }
}

/// Aucun pressing ne passe encore à cette commune.
class _NoPressingHere extends StatelessWidget {
  const _NoPressingHere({required this.commune});

  final String commune;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.md),
      decoration: BoxDecoration(
        color: context.primaryTint,
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.local_shipping_outlined, color: context.textMuted),
          const SizedBox(width: AppSpacing.sm + 4),
          Expanded(
            child: Text(
              'Aucun pressing ne passe encore à $commune. Choisissez une autre adresse, ou réessayez bientôt : de nouveaux pressings rejoignent PressLink.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ),
        ],
      ),
    );
  }
}

/// Carte à choisir : un pressing, ou « le premier pressing disponible ».
class _ChoiceCard extends StatelessWidget {
  const _ChoiceCard({
    required this.selected,
    required this.onTap,
    required this.leading,
    required this.title,
    required this.subtitle,
    this.badge,
  });

  final bool selected;
  final VoidCallback onTap;
  final Widget leading;
  final String title;
  final String subtitle;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
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
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.sm + 4),
          child: Row(
            children: [
              leading,
              const SizedBox(width: AppSpacing.sm + 4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 6,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          title,
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        if (badge != null)
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 6,
                              vertical: 1,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              badge!,
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 12.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                size: 20,
                color: selected ? AppColors.primary : context.textMuted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PressingAvatar extends StatelessWidget {
  const _PressingAvatar({required this.option});

  final PickupOption option;

  @override
  Widget build(BuildContext context) {
    final initial = option.name.isEmpty ? '?' : option.name[0].toUpperCase();
    return CircleAvatar(
      backgroundColor: context.primaryTint,
      foregroundImage: option.logoUrl != null
          ? NetworkImage(option.logoUrl!)
          : null,
      child: Text(
        initial,
        style: const TextStyle(
          fontWeight: FontWeight.w700,
          color: AppColors.primary,
        ),
      ),
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
  const _EmptyMessage({
    required this.icon,
    required this.text,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String text;
  final String? actionLabel;
  final VoidCallback? onAction;

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
            if (actionLabel != null && onAction != null) ...[
              const SizedBox(height: AppSpacing.md),
              ElevatedButton(onPressed: onAction, child: Text(actionLabel!)),
            ],
          ],
        ),
      ),
    );
  }
}
