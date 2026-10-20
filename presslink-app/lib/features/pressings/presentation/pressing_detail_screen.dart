import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../domain/pressing_repository.dart';

const _dayLabels = {
  'lundi': 'Lundi',
  'mardi': 'Mardi',
  'mercredi': 'Mercredi',
  'jeudi': 'Jeudi',
  'vendredi': 'Vendredi',
  'samedi': 'Samedi',
  'dimanche': 'Dimanche',
};
const _dayOrder = [
  'lundi',
  'mardi',
  'mercredi',
  'jeudi',
  'vendredi',
  'samedi',
  'dimanche',
];

String _formatFcfa(int amount) {
  final s = amount.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i != 0 && (s.length - i) % 3 == 0) buffer.write(' ');
    buffer.write(s[i]);
  }
  return '${buffer.toString()} FCFA';
}

/// Fiche détaillée d'un pressing — accessible depuis "Mes pressings" et les
/// commandes. User Flows : localisation, horaires, contact direct, code
/// pressing, logo, et grille tarifaire si le pressing l'a rendue visible.
class PressingDetailScreen extends StatelessWidget {
  const PressingDetailScreen({super.key, required this.pressing});

  final PressingModel pressing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Détail du pressing'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Center(
              child: Column(
                children: [
                  Container(
                    width: 72,
                    height: 72,
                    decoration: BoxDecoration(
                      color: context.primaryTint,
                      borderRadius: BorderRadius.circular(18),
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: pressing.logoUrl != null
                        ? Image.network(pressing.logoUrl!, fit: BoxFit.cover)
                        : Center(
                            child: Text(
                              pressing.initials,
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w700,
                                fontSize: 24,
                              ),
                            ),
                          ),
                  ),
                  const SizedBox(height: AppSpacing.sm + 4),
                  Text(
                    pressing.name,
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Code : ${pressing.code}',
                    style: theme.textTheme.labelSmall,
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            _SectionCard(
              title: 'Contact & localisation',
              children: [
                if (pressing.phone != null)
                  _InfoRow(icon: Icons.call, label: pressing.phone!),
                if (pressing.email != null)
                  _InfoRow(icon: Icons.email_outlined, label: pressing.email!),
                if (pressing.address != null || pressing.city != null)
                  _InfoRow(
                    icon: Icons.location_on_outlined,
                    label: [
                      pressing.address,
                      pressing.city,
                    ].where((e) => e != null && e.isNotEmpty).join(', '),
                  ),
              ],
            ),
            if (pressing.description != null &&
                pressing.description!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                title: 'À propos',
                children: [
                  Text(
                    pressing.description!,
                    style: theme.textTheme.bodyMedium,
                  ),
                ],
              ),
            ],
            if (pressing.openingHours != null) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                title: "Horaires d'ouverture",
                children: [
                  for (final day in _dayOrder)
                    if (pressing.openingHours![day] != null)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 3),
                        child: Row(
                          children: [
                            SizedBox(
                              width: 90,
                              child: Text(
                                _dayLabels[day]!,
                                style: theme.textTheme.bodyMedium,
                              ),
                            ),
                            Text(
                              pressing.openingHours![day]!.closed
                                  ? 'Fermé'
                                  : '${pressing.openingHours![day]!.open} – ${pressing.openingHours![day]!.close}',
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: pressing.openingHours![day]!.closed
                                    ? context.textMuted
                                    : null,
                              ),
                            ),
                          ],
                        ),
                      ),
                ],
              ),
            ],
            if ((pressing.terms?.isNotEmpty ?? false) ||
                pressing.storageFeeClause != null) ...[
              const SizedBox(height: AppSpacing.md),
              _SectionCard(
                title: 'Conditions et règlement',
                children: [
                  if (pressing.terms?.isNotEmpty ?? false)
                    Text(pressing.terms!, style: theme.textTheme.bodyMedium),
                  if (pressing.storageFeeClause != null) ...[
                    if (pressing.terms?.isNotEmpty ?? false)
                      const SizedBox(height: AppSpacing.sm),
                    Text(
                      pressing.storageFeeClause!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ],
              ),
            ],
            if (pressing.prestations != null &&
                pressing.prestations!.isNotEmpty) ...[
              for (final prestation in pressing.prestations!) ...[
                const SizedBox(height: AppSpacing.md),
                _SectionCard(
                  title: prestation.name,
                  leading: Icon(
                    _prestationIcon(prestation.icon),
                    size: 18,
                    color: AppColors.primary,
                  ),
                  children: [
                    for (final piece in prestation.pieces) ...[
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                piece.name,
                                style: theme.textTheme.bodyMedium,
                              ),
                            ),
                            if (piece.priceFcfa != null)
                              Text(
                                _formatFcfa(piece.priceFcfa!),
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                          ],
                        ),
                      ),
                      for (final variant in piece.variants)
                        Padding(
                          padding: const EdgeInsets.only(
                            left: AppSpacing.md,
                            bottom: 4,
                          ),
                          child: Row(
                            children: [
                              Expanded(
                                child: Text(
                                  '· ${variant.name}',
                                  style: theme.textTheme.bodySmall,
                                ),
                              ),
                              Text(
                                _formatFcfa(variant.priceFcfa),
                                style: theme.textTheme.bodySmall,
                              ),
                            ],
                          ),
                        ),
                    ],
                  ],
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

/// Icône Material la plus proche de l'icône du service choisie par le pressing.
IconData _prestationIcon(String key) => switch (key) {
  'repassage' => Icons.iron_outlined,
  'nettoyage' || 'blanchisserie' => Icons.local_laundry_service_outlined,
  'nettoyage_sec' => Icons.dry_cleaning_outlined,
  'retouche' => Icons.content_cut,
  'detachage' => Icons.water_drop_outlined,
  'teinture' => Icons.palette_outlined,
  'express' => Icons.bolt,
  'cordonnerie' => Icons.hiking,
  'tapis' => Icons.texture,
  'livraison' => Icons.local_shipping_outlined,
  _ => Icons.auto_awesome_outlined,
};

class _SectionCard extends StatelessWidget {
  const _SectionCard({
    required this.title,
    required this.children,
    this.leading,
  });

  final String title;
  final List<Widget> children;
  final Widget? leading;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.md - 1),
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              if (leading != null) ...[leading!, const SizedBox(width: 8)],
              Text(
                title,
                style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          ...children,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Icon(icon, size: 18, color: context.textSecondary),
          const SizedBox(width: AppSpacing.sm),
          Expanded(child: Text(label, style: theme.textTheme.bodyMedium)),
        ],
      ),
    );
  }
}
