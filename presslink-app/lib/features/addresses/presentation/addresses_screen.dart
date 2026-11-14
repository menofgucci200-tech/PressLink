import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/api_error.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_page_route.dart';
import '../../../core/widgets/error_state_view.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../domain/address_repository.dart';
import 'address_form_screen.dart';
import 'addresses_controller.dart';

/// Profil > Mes adresses : où le pressing passe chercher le linge et où il
/// le livre quand il est prêt.
class AddressesScreen extends ConsumerWidget {
  const AddressesScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final addressesAsync = ref.watch(addressesControllerProvider);
    final addresses = addressesAsync.valueOrNull ?? const <CustomerAddress>[];
    final canAdd = addresses.length < AddressRepository.maxAddresses;

    void openForm([CustomerAddress? address]) => Navigator.of(
      context,
    ).push(AppPageRoute(builder: (_) => AddressFormScreen(address: address)));

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Mes adresses'),
      body: SafeArea(
        child: addressesAsync.when(
          loading: () => const Center(child: CircularProgressIndicator()),
          error: (e, _) => ErrorStateView(
            message: apiErrorMessage(e),
            onRetry: () => ref.invalidate(addressesControllerProvider),
          ),
          data: (addresses) => ListView(
            padding: const EdgeInsets.all(AppSpacing.md),
            children: [
              Text(
                'Le pressing passe chercher votre linge à l\'adresse choisie, '
                'et vous le livre quand il est prêt.',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.md),
              if (addresses.isEmpty)
                Container(
                  padding: const EdgeInsets.all(AppSpacing.lg),
                  decoration: BoxDecoration(
                    color: theme.cardTheme.color,
                    border: Border.all(color: theme.dividerTheme.color!),
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                  ),
                  child: Column(
                    children: [
                      Icon(
                        Icons.location_on_outlined,
                        size: 32,
                        color: context.textMuted,
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        'Aucune adresse pour l\'instant.',
                        textAlign: TextAlign.center,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              for (final address in addresses) ...[
                _AddressCard(address: address, onTap: () => openForm(address)),
                const SizedBox(height: AppSpacing.sm),
              ],
              const SizedBox(height: AppSpacing.sm),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: canAdd ? () => openForm() : null,
                  icon: const Icon(Icons.add, size: 20, color: Colors.white),
                  label: const Text('Ajouter une adresse'),
                ),
              ),
              if (!canAdd) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  'Vous avez déjà ${AddressRepository.maxAddresses} adresses. '
                  'Supprimez-en une pour en ajouter une autre.',
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _AddressCard extends StatelessWidget {
  const _AddressCard({required this.address, required this.onTap});

  final CustomerAddress address;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Material(
      color: theme.cardTheme.color,
      shape: RoundedRectangleBorder(
        side: BorderSide(
          color: address.isDefault
              ? AppColors.primary
              : theme.dividerTheme.color!,
          width: address.isDefault ? 1.5 : 1,
        ),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                address.isDefault
                    ? Icons.home_rounded
                    : Icons.location_on_outlined,
                size: 22,
                color: address.isDefault
                    ? AppColors.primary
                    : theme.textTheme.bodyMedium?.color,
              ),
              const SizedBox(width: AppSpacing.sm + 4),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            address.label,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        if (address.isDefault) ...[
                          const SizedBox(width: AppSpacing.sm),
                          Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 8,
                              vertical: 2,
                            ),
                            decoration: BoxDecoration(
                              color: AppColors.primary.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(
                                AppRadius.pill,
                              ),
                            ),
                            child: const Text(
                              'Par défaut',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: AppColors.primary,
                              ),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 3),
                    Text(address.place, style: const TextStyle(fontSize: 14)),
                    const SizedBox(height: 2),
                    Text(
                      address.landmark,
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
