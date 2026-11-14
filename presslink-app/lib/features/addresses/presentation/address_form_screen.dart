import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../domain/address_repository.dart';
import 'addresses_controller.dart';

/// Ajouter ou modifier une adresse : nom, commune, quartier et repère,
/// comme on indique une adresse à Abidjan.
class AddressFormScreen extends ConsumerStatefulWidget {
  const AddressFormScreen({super.key, this.address});

  /// Nulle pour une nouvelle adresse.
  final CustomerAddress? address;

  @override
  ConsumerState<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends ConsumerState<AddressFormScreen> {
  static const _suggestedLabels = ['Maison', 'Bureau'];

  late final _labelController = TextEditingController(
    text: widget.address?.label ?? '',
  );
  late final _neighborhoodController = TextEditingController(
    text: widget.address?.neighborhood ?? '',
  );
  late final _landmarkController = TextEditingController(
    text: widget.address?.landmark ?? '',
  );
  late String? _commune = widget.address?.commune;
  late bool _isDefault = widget.address?.isDefault ?? false;
  bool _saving = false;
  String? _error;

  bool get _isNew => widget.address == null;

  @override
  void dispose() {
    _labelController.dispose();
    _neighborhoodController.dispose();
    _landmarkController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final label = _labelController.text.trim();
    final landmark = _landmarkController.text.trim();
    final missing = <String>[
      if (label.isEmpty) 'un nom',
      if (_commune == null) 'la commune',
      if (landmark.isEmpty) 'un repère',
    ];
    if (missing.isNotEmpty) {
      setState(() => _error = 'Indiquez ${missing.join(', ')}.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    final error = await ref
        .read(addressesControllerProvider.notifier)
        .save(
          id: widget.address?.id,
          label: label,
          commune: _commune!,
          neighborhood: _neighborhoodController.text.trim(),
          landmark: landmark,
          isDefault: _isDefault,
        );
    if (!mounted) return;
    if (error != null) {
      setState(() {
        _saving = false;
        _error = error;
      });
      return;
    }
    showAppToast(context, _isNew ? 'Adresse ajoutée.' : 'Adresse modifiée.');
    Navigator.of(context).pop();
  }

  Future<void> _delete() async {
    final confirmed = await showAppConfirm(
      context,
      title: 'Supprimer cette adresse ?',
      message: '« ${widget.address!.label} » ne sera plus proposée.',
      confirmLabel: 'Supprimer',
      tone: AppConfirmTone.danger,
      icon: Icons.delete_outline,
    );
    if (!confirmed || !mounted) return;

    setState(() => _saving = true);
    final error = await ref
        .read(addressesControllerProvider.notifier)
        .delete(widget.address!.id);
    if (!mounted) return;
    if (error != null) {
      setState(() => _saving = false);
      showAppToast(context, error, type: AppToastType.error);
      return;
    }
    showAppToast(context, 'Adresse supprimée.');
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final groupsAsync = ref.watch(communeGroupsProvider);
    final labelStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 12,
      fontWeight: FontWeight.w500,
    );

    return Scaffold(
      appBar: PressLinkTopBar(
        title: _isNew ? 'Nouvelle adresse' : 'Modifier l\'adresse',
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AppTextField(
                controller: _labelController,
                label: 'Nom de l\'adresse',
                hintText: 'ex. Maison',
                textCapitalization: TextCapitalization.sentences,
                onChanged: (_) => setState(() {}),
              ),
              const SizedBox(height: AppSpacing.sm),
              Wrap(
                spacing: AppSpacing.sm,
                children: [
                  for (final suggestion in _suggestedLabels)
                    ChoiceChip(
                      label: Text(suggestion),
                      selected: _labelController.text == suggestion,
                      onSelected: (_) =>
                          setState(() => _labelController.text = suggestion),
                    ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Text('Commune', style: labelStyle),
              const SizedBox(height: 6),
              groupsAsync.when(
                loading: () => const SizedBox(
                  height: 52,
                  child: Center(child: LinearProgressIndicator()),
                ),
                error: (e, _) => TextButton.icon(
                  onPressed: () => ref.invalidate(communeGroupsProvider),
                  icon: const Icon(Icons.refresh, size: 18),
                  label: const Text('Charger les communes'),
                ),
                data: (groups) => DropdownButtonFormField<String>(
                  initialValue: _commune,
                  isExpanded: true,
                  hint: const Text('Choisir la commune'),
                  items: [
                    for (final group in groups) ...[
                      DropdownMenuItem<String>(
                        enabled: false,
                        child: Text(
                          group.name.toUpperCase(),
                          style: theme.textTheme.labelSmall?.copyWith(
                            letterSpacing: 0.6,
                          ),
                        ),
                      ),
                      for (final commune in group.communes)
                        DropdownMenuItem(value: commune, child: Text(commune)),
                    ],
                  ],
                  onChanged: (value) => setState(() => _commune = value),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _neighborhoodController,
                label: 'Quartier (facultatif)',
                hintText: 'ex. Riviera 3',
                textCapitalization: TextCapitalization.words,
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _landmarkController,
                label: 'Repère pour vous trouver',
                hintText: 'ex. Immeuble bleu derrière la pharmacie',
                textCapitalization: TextCapitalization.sentences,
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: AppSpacing.sm),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                value: _isDefault,
                activeThumbColor: AppColors.primary,
                title: const Text(
                  'Adresse par défaut',
                  style: TextStyle(fontSize: 14),
                ),
                subtitle: const Text(
                  'Proposée en premier quand vous envoyez une commande.',
                  style: TextStyle(fontSize: 12),
                ),
                // La seule adresse reste celle par défaut.
                onChanged: widget.address?.isDefault == true
                    ? null
                    : (value) => setState(() => _isDefault = value),
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  _error!,
                  style: TextStyle(
                    color: theme.colorScheme.error,
                    fontSize: 13,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _saving ? null : _submit,
                  child: _saving
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : Text(_isNew ? 'Ajouter l\'adresse' : 'Enregistrer'),
                ),
              ),
              if (!_isNew) ...[
                const SizedBox(height: AppSpacing.sm),
                SizedBox(
                  width: double.infinity,
                  height: 48,
                  child: TextButton.icon(
                    onPressed: _saving ? null : _delete,
                    icon: const Icon(Icons.delete_outline, size: 18),
                    label: const Text('Supprimer cette adresse'),
                    style: TextButton.styleFrom(
                      foregroundColor: AppColors.error,
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
