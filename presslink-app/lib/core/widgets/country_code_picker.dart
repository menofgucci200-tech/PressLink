import 'package:flutter/material.dart';

import '../data/countries.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Sélecteur d'indicatif pays — drapeau + code, ouvre une liste
/// recherchable plutôt que de laisser l'utilisateur taper l'indicatif.
class CountryCodePicker extends StatelessWidget {
  const CountryCodePicker({required this.selected, required this.onChanged, super.key});

  final Country selected;
  final ValueChanged<Country> onChanged;

  Future<void> _openPicker(BuildContext context) async {
    final country = await showModalBottomSheet<Country>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Theme.of(context).cardTheme.color,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => const _CountryPickerSheet(),
    );

    if (country != null) onChanged(country);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: () => _openPicker(context),
      borderRadius: BorderRadius.circular(10),
      child: Container(
        height: 52,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm + 2),
        decoration: BoxDecoration(
          border: Border.all(color: theme.dividerTheme.color ?? AppColors.border),
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(selected.flag, style: const TextStyle(fontSize: 20)),
            const SizedBox(width: 6),
            Text(selected.dialCode, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
            const SizedBox(width: 2),
            Icon(Icons.keyboard_arrow_down, size: 18, color: theme.textTheme.bodyMedium?.color),
          ],
        ),
      ),
    );
  }
}

class _CountryPickerSheet extends StatefulWidget {
  const _CountryPickerSheet();

  @override
  State<_CountryPickerSheet> createState() => _CountryPickerSheetState();
}

class _CountryPickerSheetState extends State<_CountryPickerSheet> {
  String _query = '';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final results = kCountries.where((c) {
      final q = _query.toLowerCase();
      return q.isEmpty || c.name.toLowerCase().contains(q) || c.dialCode.contains(q);
    }).toList();

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.only(top: AppSpacing.sm),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 36,
              height: 4,
              margin: const EdgeInsets.only(bottom: AppSpacing.md),
              decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: Text('Choisir un pays', style: theme.textTheme.headlineSmall?.copyWith(fontSize: 17)),
            ),
            const SizedBox(height: AppSpacing.sm),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: TextField(
                autofocus: false,
                onChanged: (v) => setState(() => _query = v),
                decoration: const InputDecoration(
                  prefixIcon: Icon(Icons.search, size: 20),
                  hintText: 'Rechercher un pays ou un indicatif',
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.sm),
            SizedBox(
              height: 360,
              child: ListView.builder(
                itemCount: results.length,
                itemBuilder: (context, index) {
                  final country = results[index];
                  return ListTile(
                    leading: Text(country.flag, style: const TextStyle(fontSize: 22)),
                    title: Text(country.name),
                    trailing: Text(country.dialCode, style: const TextStyle(fontWeight: FontWeight.w600)),
                    onTap: () => Navigator.of(context).pop(country),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
