import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../pwa/pwa_install.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Propose d'installer PressLink sur l'écran d'accueil quand l'app tourne
/// dans un navigateur (PWA). N'affiche rien dans l'APK, une fois la PWA
/// installée, ou si l'utilisateur l'a fermée il y a moins de 7 jours.
///
/// iOS : Safari n'a aucune invite d'installation — la bannière explique le
/// geste. Android/Chrome : un bouton déclenche l'invite native.
class PwaInstallBanner extends StatefulWidget {
  const PwaInstallBanner({super.key, this.showReloginNote = false});

  /// Sur iOS, la PWA installée a son propre stockage : un utilisateur déjà
  /// connecté dans Safari devra se reconnecter après l'installation.
  final bool showReloginNote;

  @override
  State<PwaInstallBanner> createState() => _PwaInstallBannerState();
}

class _PwaInstallBannerState extends State<PwaInstallBanner> {
  static const _dismissedKey = 'pwa_install_dismissed_at';
  static const _snooze = Duration(days: 7);

  bool _dismissed = true;

  @override
  void initState() {
    super.initState();
    PwaInstall.onChange(() {
      if (mounted) setState(() {});
    });
    _loadDismissed();
  }

  Future<void> _loadDismissed() async {
    final prefs = await SharedPreferences.getInstance();
    final at = prefs.getInt(_dismissedKey);
    final stillSnoozed =
        at != null &&
        DateTime.now().difference(DateTime.fromMillisecondsSinceEpoch(at)) <
            _snooze;
    if (mounted) setState(() => _dismissed = stillSnoozed);
  }

  Future<void> _dismiss() async {
    setState(() => _dismissed = true);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_dismissedKey, DateTime.now().millisecondsSinceEpoch);
  }

  Future<void> _install() async {
    final accepted = await PwaInstall.promptInstall();
    if (accepted && mounted) setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    final mode = PwaInstall.mode;
    if (_dismissed || mode == PwaInstallMode.none) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final isIos = mode == PwaInstallMode.iosInstructions;

    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.md),
      child: Container(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.sm + 4,
          AppSpacing.sm,
          AppSpacing.md,
        ),
        decoration: BoxDecoration(
          color: AppColors.primaryTint,
          border: Border.all(color: AppColors.primary.withValues(alpha: 0.25)),
          borderRadius: BorderRadius.circular(AppRadius.lg),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 36,
                  height: 36,
                  decoration: BoxDecoration(
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: const Icon(
                    Icons.install_mobile,
                    size: 20,
                    color: Colors.white,
                  ),
                ),
                const SizedBox(width: AppSpacing.sm + 4),
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Installer PressLink',
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: AppColors.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        if (isIos)
                          Text.rich(
                            TextSpan(
                              style: theme.textTheme.bodyMedium?.copyWith(
                                fontSize: 12.5,
                                color: AppColors.textSecondary,
                              ),
                              children: const [
                                TextSpan(text: 'Touchez '),
                                WidgetSpan(
                                  alignment: PlaceholderAlignment.middle,
                                  child: Icon(
                                    Icons.ios_share,
                                    size: 15,
                                    color: AppColors.primary,
                                  ),
                                ),
                                TextSpan(
                                  text: ' puis « Sur l\'écran d\'accueil »',
                                ),
                              ],
                            ),
                          )
                        else
                          Text(
                            'Ajoutez-la à votre écran d\'accueil.',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 12.5,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        if (isIos && widget.showReloginNote) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Vous devrez vous reconnecter dans l\'application installée.',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                IconButton(
                  tooltip: 'Plus tard',
                  onPressed: _dismiss,
                  visualDensity: VisualDensity.compact,
                  icon: const Icon(
                    Icons.close,
                    size: 18,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
            if (!isIos) ...[
              const SizedBox(height: AppSpacing.sm + 4),
              Padding(
                padding: const EdgeInsets.only(right: AppSpacing.sm),
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _install,
                    child: const Text('Installer'),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
