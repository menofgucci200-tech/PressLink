import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/config/app_config.dart';

import '../../../core/widgets/app_page_route.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/theme_mode_controller.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../auth/presentation/logout_confirmation.dart';
import '../../pressings/presentation/my_pressings_screen.dart';
import '../../pressings/presentation/pressings_controller.dart';
import 'change_password_screen.dart';
import 'change_phone_screen.dart';
import 'edit_profile_screen.dart';
import 'notification_preferences_controller.dart';
import 'profile_photo_picker.dart';
import 'static_page_screen.dart';

/// Mon profil — Cahier §10.2 / Architecture des écrans §8. Même logique que
/// « Mon profil » des dashboards : photo, informations personnelles,
/// apparence, préférences et sécurité au même endroit.
class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final customer = ref.watch(authControllerProvider).customer;
    final pressingsCount = ref.watch(myPressingsProvider).valueOrNull?.length;
    final isFemale = customer?.gender == 'femme';

    void openEditProfile() => Navigator.of(
      context,
    ).push(AppPageRoute(builder: (_) => const EditProfileScreen()));

    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Row(
              children: [
                AppBackButton(onPressed: () => Navigator.of(context).pop()),
                const SizedBox(width: AppSpacing.sm + 2),
                Text('Mon profil', style: theme.textTheme.headlineSmall),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),

            // ===== Photo et identité =====
            _Card(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.md,
                AppSpacing.lg,
                AppSpacing.md,
                AppSpacing.md,
              ),
              child: Column(
                children: [
                  const EditableCustomerAvatar(radius: 46),
                  const SizedBox(height: AppSpacing.sm + 4),
                  Text(
                    customer?.fullName.isNotEmpty == true
                        ? customer!.fullName
                        : '—',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    formatPhoneForDisplay(customer?.phone ?? ''),
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: AppSpacing.sm + 4),
                  Wrap(
                    alignment: WrapAlignment.center,
                    spacing: AppSpacing.sm,
                    runSpacing: AppSpacing.sm,
                    children: [
                      if (customer?.createdAt != null)
                        _Chip(
                          icon: Icons.event_outlined,
                          label:
                              '${isFemale ? 'Cliente' : 'Client'} depuis ${DateFormat('MMMM y', 'fr_FR').format(customer!.createdAt!)}',
                        ),
                      if (pressingsCount != null)
                        _Chip(
                          icon: Icons.store_outlined,
                          label: pressingsCount > 1
                              ? '$pressingsCount pressings'
                              : '$pressingsCount pressing',
                        ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.md),
                  SizedBox(
                    width: double.infinity,
                    height: 44,
                    child: OutlinedButton.icon(
                      onPressed: openEditProfile,
                      icon: const Icon(Icons.edit_outlined, size: 18),
                      label: const Text('Modifier mon profil'),
                    ),
                  ),
                ],
              ),
            ),

            // ===== Informations personnelles =====
            const _SectionTitle('INFORMATIONS PERSONNELLES'),
            _Card(
              child: Column(
                children: [
                  _ProfileRow(
                    icon: Icons.badge_outlined,
                    label: 'Nom',
                    value: customer?.fullName ?? '',
                    onTap: openEditProfile,
                  ),
                  _ProfileRow(
                    icon: Icons.wc_outlined,
                    label: 'Genre',
                    value: isFemale
                        ? 'Femme'
                        : (customer?.gender == 'homme'
                              ? 'Homme'
                              : 'À préciser'),
                    onTap: openEditProfile,
                  ),
                  _ProfileRow(
                    icon: Icons.alternate_email,
                    label: 'Email',
                    value: customer?.email ?? 'Non renseigné',
                    onTap: openEditProfile,
                  ),
                  _ProfileRow(
                    icon: Icons.phone_outlined,
                    label: 'Téléphone',
                    value: formatPhoneForDisplay(customer?.phone ?? ''),
                    hint: 'Il sert à vous connecter.',
                    isLast: true,
                    onTap: () => Navigator.of(context).push(
                      AppPageRoute(builder: (_) => const ChangePhoneScreen()),
                    ),
                  ),
                ],
              ),
            ),

            // ===== Apparence =====
            const _SectionTitle('APPARENCE'),
            const _ThemeModeSelector(),

            // ===== Préférences =====
            const _SectionTitle('PRÉFÉRENCES'),
            _Card(
              child: Column(
                children: [
                  const _NotificationToggleRow(),
                  _ProfileRow(
                    icon: Icons.store_outlined,
                    label: 'Mes pressings',
                    value: pressingsCount?.toString() ?? '',
                    isLast: true,
                    onTap: () => Navigator.of(context).push(
                      AppPageRoute(builder: (_) => const MyPressingsScreen()),
                    ),
                  ),
                ],
              ),
            ),

            // ===== Sécurité =====
            const _SectionTitle('SÉCURITÉ'),
            _Card(
              child: _ProfileRow(
                icon: Icons.lock_outline,
                label: 'Mot de passe',
                value: '••••••••',
                isLast: true,
                onTap: () => Navigator.of(context).push(
                  AppPageRoute(builder: (_) => const ChangePasswordScreen()),
                ),
              ),
            ),

            // ===== Aide et informations =====
            const _SectionTitle('AIDE ET INFORMATIONS'),
            _Card(
              child: Column(
                children: [
                  _ProfileRow(
                    icon: Icons.help_outline,
                    label: 'Aide',
                    value: '',
                    onTap: () => Navigator.of(context).push(
                      AppPageRoute(
                        builder: (_) => const StaticPageScreen(
                          title: 'Aide',
                          sections: StaticPages.help,
                        ),
                      ),
                    ),
                  ),
                  _ProfileRow(
                    icon: Icons.description_outlined,
                    label: 'Conditions d\'utilisation',
                    value: '',
                    onTap: () => launchUrl(Uri.parse(AppConfig.termsUrl)),
                  ),
                  _ProfileRow(
                    icon: Icons.privacy_tip_outlined,
                    label: 'Politique de confidentialité',
                    value: '',
                    isLast: true,
                    onTap: () => launchUrl(Uri.parse(AppConfig.privacyUrl)),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            SizedBox(
              width: double.infinity,
              height: 48,
              child: OutlinedButton.icon(
                onPressed: () => confirmAndLogout(context, ref),
                icon: const Icon(Icons.logout, size: 18),
                label: const Text('Se déconnecter'),
                style: OutlinedButton.styleFrom(
                  foregroundColor: AppColors.error,
                  side: BorderSide(
                    color: AppColors.error.withValues(alpha: 0.35),
                  ),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.md),
          ],
        ),
      ),
    );
  }
}

/// « +2250708124400 » → « +225 07 08 12 44 00 » (lecture plus facile).
String formatPhoneForDisplay(String phone) {
  final match = RegExp(r'^\+225(\d{10})$').firstMatch(phone);
  if (match == null) return phone;
  final digits = match.group(1)!;
  final pairs = [
    for (var i = 0; i < digits.length; i += 2) digits.substring(i, i + 2),
  ];
  return '+225 ${pairs.join(' ')}';
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(
        top: AppSpacing.lg,
        bottom: AppSpacing.sm + 2,
        left: 2,
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

class _Card extends StatelessWidget {
  const _Card({
    required this.child,
    this.padding = const EdgeInsets.symmetric(horizontal: AppSpacing.md),
  });

  final Widget child;
  final EdgeInsetsGeometry padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: padding,
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: child,
    );
  }
}

class _Chip extends StatelessWidget {
  const _Chip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final muted = isDark
        ? AppColors.textSecondaryDark
        : AppColors.textSecondary;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.pill),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: muted),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w500,
              color: muted,
            ),
          ),
        ],
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
      (
        mode: ThemeMode.system,
        label: 'Système',
        icon: Icons.smartphone_outlined,
      ),
    ];

    return Row(
      children: [
        for (final option in options) ...[
          Expanded(
            child: _ThemeOptionButton(
              label: option.label,
              icon: option.icon,
              selected: current == option.mode,
              onTap: () => ref
                  .read(themeModeControllerProvider.notifier)
                  .setThemeMode(option.mode),
            ),
          ),
          if (option != options.last) const SizedBox(width: AppSpacing.sm),
        ],
      ],
    );
  }
}

class _ThemeOptionButton extends StatelessWidget {
  const _ThemeOptionButton({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final selectedColor = isDark ? const Color(0xFF93C5FD) : AppColors.primary;
    final idleColor = isDark
        ? AppColors.textSecondaryDark
        : AppColors.textSecondary;
    final foreground = selected ? selectedColor : idleColor;

    return Semantics(
      selected: selected,
      button: true,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadius.md),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.sm + 4),
          decoration: BoxDecoration(
            color: selected
                ? (isDark
                      ? AppColors.primary.withValues(alpha: 0.18)
                      : AppColors.primaryTint)
                : theme.cardTheme.color,
            border: Border.all(
              color: selected ? AppColors.primary : theme.dividerTheme.color!,
              width: selected ? 1.5 : 1,
            ),
            borderRadius: BorderRadius.circular(AppRadius.md),
          ),
          child: Column(
            children: [
              Icon(icon, size: 20, color: foreground),
              const SizedBox(height: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12.5,
                  fontWeight: FontWeight.w600,
                  color: foreground,
                ),
              ),
            ],
          ),
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
      padding: const EdgeInsets.symmetric(vertical: 6),
      decoration: BoxDecoration(
        border: Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
      ),
      child: Row(
        children: [
          Icon(
            Icons.notifications_none,
            size: 20,
            color: theme.textTheme.bodyMedium?.color,
          ),
          const SizedBox(width: AppSpacing.sm + 4),
          const Expanded(
            child: Text('Notifications', style: TextStyle(fontSize: 14)),
          ),
          Switch(
            value: enabled,
            activeThumbColor: AppColors.primary,
            onChanged: (value) => ref
                .read(notificationPreferencesControllerProvider.notifier)
                .setEnabled(value),
          ),
        ],
      ),
    );
  }
}

class _ProfileRow extends StatelessWidget {
  const _ProfileRow({
    required this.icon,
    required this.label,
    required this.value,
    this.hint,
    this.isLast = false,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final String value;

  /// Précision sous le libellé (ex. champ non modifiable ici).
  final String? hint;
  final bool isLast;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodyMedium?.color;
    return InkWell(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 14),
        decoration: BoxDecoration(
          border: isLast
              ? null
              : Border(bottom: BorderSide(color: theme.dividerTheme.color!)),
        ),
        child: Row(
          children: [
            Icon(icon, size: 20, color: muted),
            const SizedBox(width: AppSpacing.sm + 4),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(label, style: const TextStyle(fontSize: 14)),
                      const SizedBox(width: AppSpacing.sm),
                      Expanded(
                        child: Text(
                          value,
                          textAlign: TextAlign.right,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ],
                  ),
                  if (hint != null) ...[
                    const SizedBox(height: 3),
                    Text(
                      hint!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 11.5,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(width: 6),
            if (onTap != null)
              Icon(
                Icons.chevron_right,
                size: 18,
                color: theme.brightness == Brightness.dark
                    ? AppColors.textMutedDark
                    : AppColors.textMuted,
              )
            else
              Icon(Icons.lock_outline, size: 15, color: muted),
          ],
        ),
      ),
    );
  }
}
