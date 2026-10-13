import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../pwa/pwa_install.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';

/// Propose d'installer PressLink sur l'écran d'accueil quand l'app tourne
/// dans un navigateur (PWA). N'affiche rien dans l'APK, une fois la PWA
/// installée, ou si l'utilisateur l'a fermée il y a moins de 7 jours (sauf
/// [alwaysVisible], utilisé par l'entrée « Installer l'application » du menu).
///
/// - Android avec invite Chrome disponible : bouton « Installer ».
/// - Android sans invite (Chrome ne la propose qu'après un temps
///   d'utilisation, et plus après un refus) : passage par le menu ⋮.
/// - iOS/Safari : aucune invite n'existe, on explique « Partager ».
class PwaInstallBanner extends StatefulWidget {
  const PwaInstallBanner({
    super.key,
    this.showReloginNote = false,
    this.alwaysVisible = false,
  });

  /// Sur iOS, la PWA installée a son propre stockage : un utilisateur déjà
  /// connecté dans Safari devra se reconnecter après l'installation.
  final bool showReloginNote;

  /// Ignore la fermeture précédente et masque la croix (affichage à la demande).
  final bool alwaysVisible;

  /// L'app peut-elle encore être installée (navigateur, pas déjà installée) ?
  static bool get isInstallable => PwaInstall.mode != PwaInstallMode.none;

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
    if (widget.alwaysVisible) {
      _dismissed = false;
    } else {
      _loadDismissed();
    }
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
    if (mounted) setState(() {});
    if (accepted && widget.alwaysVisible && mounted) {
      Navigator.of(context).maybePop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final mode = PwaInstall.mode;
    if (_dismissed || mode == PwaInstallMode.none) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final textStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 12.5,
      color: context.textSecondary,
    );

    Widget instructions;
    switch (mode) {
      case PwaInstallMode.iosInstructions:
        instructions = Text.rich(
          TextSpan(
            style: textStyle,
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
              TextSpan(text: ' puis « Sur l\'écran d\'accueil »'),
            ],
          ),
        );
      case PwaInstallMode.androidInstructions:
        instructions = Text.rich(
          TextSpan(
            style: textStyle,
            children: const [
              TextSpan(text: 'Ouvrez le menu '),
              WidgetSpan(
                alignment: PlaceholderAlignment.middle,
                child: Icon(
                  Icons.more_vert,
                  size: 15,
                  color: AppColors.primary,
                ),
              ),
              TextSpan(
                text: ' du navigateur puis « Installer l\'application ».',
              ),
            ],
          ),
        );
      case PwaInstallMode.nativePrompt:
      case PwaInstallMode.none:
        instructions = Text(
          'Ajoutez-la à votre écran d\'accueil.',
          style: textStyle,
        );
    }

    return Padding(
      padding: EdgeInsets.only(
        bottom: widget.alwaysVisible ? 0 : AppSpacing.md,
      ),
      child: Container(
        padding: EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.sm + 4,
          widget.alwaysVisible ? AppSpacing.md : AppSpacing.sm,
          AppSpacing.md,
        ),
        decoration: BoxDecoration(
          color: context.primaryTint,
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
                            color: context.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        instructions,
                        if (mode == PwaInstallMode.iosInstructions &&
                            widget.showReloginNote) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Vous devrez vous reconnecter dans l\'application installée.',
                            style: theme.textTheme.labelSmall?.copyWith(
                              color: context.textSecondary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ),
                if (!widget.alwaysVisible)
                  IconButton(
                    tooltip: 'Plus tard',
                    onPressed: _dismiss,
                    visualDensity: VisualDensity.compact,
                    icon: Icon(
                      Icons.close,
                      size: 18,
                      color: context.textSecondary,
                    ),
                  ),
              ],
            ),
            if (mode == PwaInstallMode.nativePrompt) ...[
              const SizedBox(height: AppSpacing.sm + 4),
              Padding(
                padding: EdgeInsets.only(
                  right: widget.alwaysVisible ? 0 : AppSpacing.sm,
                ),
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

/// Ouvre la carte d'installation à la demande (entrée de menu), même si la
/// bannière a été fermée auparavant.
Future<void> showPwaInstallSheet(BuildContext context) {
  return showModalBottomSheet(
    context: context,
    backgroundColor: Theme.of(context).cardTheme.color,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (context) => const SafeArea(
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.md,
          AppSpacing.md,
        ),
        child: PwaInstallBanner(alwaysVisible: true, showReloginNote: true),
      ),
    ),
  );
}
