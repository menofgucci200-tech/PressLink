import 'package:flutter/material.dart';

import '../../features/orders/presentation/select_order_for_issue_screen.dart';
import '../../features/pressings/presentation/my_pressings_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Feuille de menu ouverte depuis l'onglet "Menu" (icône burger) —
/// regroupe Profil, Signaler un problème, et les futurs menus de l'app.
Future<void> showMainMenuSheet(BuildContext context) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).cardTheme.color,
    shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
    builder: (context) => const _MainMenuSheet(),
  );
}

class _MainMenuSheet extends StatelessWidget {
  const _MainMenuSheet();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AppSpacing.md, AppSpacing.sm, AppSpacing.md, AppSpacing.md),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Center(
              child: Container(
                width: 36,
                height: 4,
                margin: const EdgeInsets.only(bottom: AppSpacing.md),
                decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            Text('Menu', style: theme.textTheme.headlineSmall?.copyWith(fontSize: 17)),
            const SizedBox(height: AppSpacing.md),
            _MenuTile(
              icon: Icons.person_outline,
              label: 'Profil',
              onTap: () {
                Navigator.of(context).pop();
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ProfileScreen()));
              },
            ),
            _MenuTile(
              icon: Icons.store_outlined,
              label: 'Mes pressings',
              onTap: () {
                Navigator.of(context).pop();
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const MyPressingsScreen()));
              },
            ),
            _MenuTile(
              icon: Icons.flag_outlined,
              label: 'Signaler un problème',
              iconColor: AppColors.error,
              onTap: () {
                Navigator.of(context).pop();
                Navigator.of(context).push(MaterialPageRoute(builder: (_) => const SelectOrderForIssueScreen()));
              },
            ),
            // Les prochains menus (fidélité, promotions, statistiques…) viendront
            // s'ajouter ici au fil des évolutions V1/V2 du cahier des fonctionnalités.
          ],
        ),
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({required this.icon, required this.label, required this.onTap, this.iconColor});

  final IconData icon;
  final String label;
  final Color? iconColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm + 4),
        child: Row(
          children: [
            Icon(icon, size: 21, color: iconColor ?? AppColors.textSecondary),
            const SizedBox(width: AppSpacing.sm + 6),
            Text(label, style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500)),
            const Spacer(),
            const Icon(Icons.chevron_right, size: 18, color: AppColors.border),
          ],
        ),
      ),
    );
  }
}
