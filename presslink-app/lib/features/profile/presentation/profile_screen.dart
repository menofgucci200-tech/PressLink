import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/theme_mode_controller.dart';
import '../../auth/presentation/auth_controller.dart';
import 'change_password_screen.dart';
import 'edit_profile_screen.dart';
import 'notification_preferences_controller.dart';
import 'static_page_screen.dart';

/// Profil client — Cahier §10.2 / Architecture des écrans §8.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final customer = ref.watch(authControllerProvider).customer;
    final initials = customer != null && customer.fullName.isNotEmpty
        ? customer.fullName.trim().split(' ').map((w) => w.isNotEmpty ? w[0] : '').take(2).join().toUpperCase()
        : '?';

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Text('Profil', style: theme.textTheme.headlineSmall),
            const SizedBox(height: AppSpacing.lg),
            InkWell(
              onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EditProfileScreen())),
              borderRadius: BorderRadius.circular(AppRadius.lg),
              child: Container(
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: BoxDecoration(
                  color: theme.cardTheme.color,
                  border: Border.all(color: theme.dividerTheme.color!),
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                ),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 28,
                      backgroundColor: AppColors.primaryTint,
                      backgroundImage: customer?.photoUrl != null ? NetworkImage(customer!.photoUrl!) : null,
                      child: customer?.photoUrl == null
                          ? Text(initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 18))
                          : null,
                    ),
                    const SizedBox(width: AppSpacing.sm + 6),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(customer?.fullName ?? '—', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w600)),
                          const SizedBox(height: 3),
                          Text(customer?.phone ?? '', style: theme.textTheme.bodyMedium),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right, size: 20, color: AppColors.border),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Text('APPARENCE', style: theme.textTheme.labelSmall?.copyWith(letterSpacing: 0.6)),
            const SizedBox(height: AppSpacing.sm + 4),
            const _ThemeModeSelector(),
            const SizedBox(height: AppSpacing.lg),
            Container(
              decoration: BoxDecoration(
                color: theme.cardTheme.color,
                border: Border.all(color: theme.dividerTheme.color!),
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md),
              child: Column(
                children: [
                  _ProfileRow(
                    label: 'Email',
                    value: customer?.email ?? 'Non renseigné',
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EditProfileScreen())),
                  ),
                  _ProfileRow(
                    label: 'Genre',
                    value: customer?.gender == 'femme' ? 'Femme' : (customer?.gender == 'homme' ? 'Homme' : '—'),
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const EditProfileScreen())),
                  ),
                  _ProfileRow(
                    label: 'Mot de passe',
                    value: '••••••••',
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => const ChangePasswordScreen())),
                  ),
                  const _NotificationToggleRow(),
                  _ProfileRow(
                    label: 'Aide',
                    value: '',
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const StaticPageScreen(title: 'Aide', sections: StaticPages.help),
                    )),
                  ),
                  _ProfileRow(
                    label: 'Conditions d\'utilisation',
                    value: '',
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const StaticPageScreen(title: 'Conditions d\'utilisation', sections: StaticPages.terms),
                    )),
                  ),
                  _ProfileRow(
                    label: 'Politique de confidentialité',
                    value: '',
                    isLast: true,
                    onTap: () => Navigator.of(context).push(MaterialPageRoute(
                      builder: (_) => const StaticPageScreen(title: 'Politique de confidentialité', sections: StaticPages.privacy),
                    )),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton(
                onPressed: () => ref.read(authControllerProvider.notifier).logout(),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.error,
                  side: const BorderSide(color: Color(0xFFFECACA)),
                ),
                child: const Text('Se déconnecter'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ThemeModeSelector extends ConsumerWidget {
  const _ThemeModeSelector();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final current = ref.watch(themeModeControllerProvider);
    final options = [
      (mode: ThemeMode.light, label: 'Clair', icon: Icons.light_mode_outlined),
      (mode: ThemeMode.dark, label: 'Sombre', icon: Icons.dark_mode_outlined),
      (mode: ThemeMode.system, label: 'Système', icon: Icons.smartphone_outlined),
    ];

    return Row(
      children: [
        for (final option in options) ...[
          Expanded(
            child: _ThemeOptionButton(
              label: option.label,
              icon: option.icon,
              selected: current == option.mode,
              onTap: () => ref.read(themeModeControllerProvider.notifier).setThemeMode(option.mode),
            ),
          ),
          if (option != options.last) const SizedBox(width: AppSpacing.sm),
        ],
      ],
    );
  }
}

class _ThemeOptionButton extends StatelessWidget {
  const _ThemeOptionButton({required this.label, required this.icon, required this.selected, required this.onTap});

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm + 4),
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryTint : Colors.white,
          border: Border.all(color: selected ? AppColors.primary : AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Column(
          children: [
            Icon(icon, size: 20, color: selected ? AppColors.primary : AppColors.textSecondary),
            const SizedBox(height: 6),
            Text(
              label,
              style: TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: selected ? AppColors.primary : AppColors.textSecondary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationToggleRow extends ConsumerWidget {
  const _NotificationToggleRow();

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final enabled = ref.watch(notificationPreferencesControllerProvider);

    return Container(
      padding: const EdgeInsets.symmetric(vertical: 8),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          const Text('Notifications', style: TextStyle(fontSize: 14)),
          Switch(
            value: enabled,
            activeThumbColor: AppColors.primary,
            onChanged: (value) => ref.read(notificationPreferencesControllerProvider.notifier).setEnabled(value),
          ),
        ],
      ),
    );
  }
}

class _ProfileRow extends StatelessWidget {
  const _ProfileRow({required this.label, required this.value, this.isLast = false, this.onTap});

  final String label;
  final String value;
  final bool isLast;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 15),
        decoration: BoxDecoration(
          border: isLast ? null : Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: const TextStyle(fontSize: 14)),
            Row(
              children: [
                if (value.isNotEmpty) ...[
                  Text(value, style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13)),
                  const SizedBox(width: 8),
                ],
                const Icon(Icons.chevron_right, size: 18, color: AppColors.border),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
