import 'package:flutter/material.dart';

import 'app_page_route.dart';
import '../../features/orders/presentation/select_order_for_issue_screen.dart';
import '../../features/pressings/presentation/my_pressings_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/support/presentation/support_screen.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import 'pwa_install_banner.dart';
import 'staggered_fade_in.dart';

/// Feuille de menu ouverte depuis l'onglet "Menu" (icône burger) —
/// regroupe Profil, Signaler un problème, et les futurs menus de l'app.
Future<void> showMainMenuSheet(BuildContext context) {
  return showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).cardTheme.color,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (context) => const _MainMenuSheet(),
  );
}

class _MainMenuSheet extends StatelessWidget {
  const _MainMenuSheet();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SafeArea(
      child: SingleChildScrollView(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            AppSpacing.sm,
            AppSpacing.md,
            AppSpacing.md,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 36,
                  height: 4,
                  margin: const EdgeInsets.only(bottom: AppSpacing.md),
                  decoration: BoxDecoration(
                    color: context.border,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              Text(
                'Menu',
                style: theme.textTheme.headlineSmall?.copyWith(fontSize: 17),
              ),
              const SizedBox(height: AppSpacing.md),
              StaggeredFadeIn(
                index: 0,
                child: _MenuTile(
                  icon: Icons.person_outline,
                  label: 'Profil',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(
                      context,
                    ).push(AppPageRoute(builder: (_) => const ProfileScreen()));
                  },
                ),
              ),
              StaggeredFadeIn(
                index: 1,
                child: _MenuTile(
                  icon: Icons.store_outlined,
                  label: 'Mes pressings',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      AppPageRoute(builder: (_) => const MyPressingsScreen()),
                    );
                  },
                ),
              ),
              StaggeredFadeIn(
                index: 2,
                child: _MenuTile(
                  icon: Icons.flag_outlined,
                  label: 'Signaler un problème',
                  iconColor: AppColors.error,
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(context).push(
                      AppPageRoute(
                        builder: (_) => const SelectOrderForIssueScreen(),
                      ),
                    );
                  },
                ),
              ),
              if (PwaInstallBanner.isInstallable)
                StaggeredFadeIn(
                  index: 3,
                  child: _MenuTile(
                    icon: Icons.install_mobile_outlined,
                    label: 'Installer l\'application',
                    iconColor: AppColors.primary,
                    onTap: () {
                      Navigator.of(context).pop();
                      showPwaInstallSheet(context);
                    },
                  ),
                ),
              StaggeredFadeIn(
                index: 4,
                child: _MenuTile(
                  icon: Icons.support_agent_outlined,
                  label: 'Aide & support',
                  onTap: () {
                    Navigator.of(context).pop();
                    Navigator.of(
                      context,
                    ).push(AppPageRoute(builder: (_) => const SupportScreen()));
                  },
                ),
              ),
              // Les prochains menus (fidélité, promotions, statistiques…) viendront
              // s'ajouter ici au fil des évolutions V1/V2 du cahier des fonctionnalités.
            ],
          ),
        ),
      ),
    );
  }
}

class _MenuTile extends StatelessWidget {
  const _MenuTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.iconColor,
  });

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
            Icon(icon, size: 21, color: iconColor ?? context.textSecondary),
            const SizedBox(width: AppSpacing.sm + 6),
            Text(
              label,
              style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w500),
            ),
            const Spacer(),
            Icon(Icons.chevron_right, size: 18, color: context.border),
          ],
        ),
      ),
    );
  }
}
