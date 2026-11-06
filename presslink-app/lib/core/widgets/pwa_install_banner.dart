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
/// Toujours un bouton « Installer » :
/// - Android avec invite du navigateur : l'installation se lance directement ;
/// - sinon (iPhone, navigateur sans invite) : les gestes à faire, selon le
///   navigateur ; navigateur intégré (Facebook…) : « Ouvrir dans Chrome ».
/// - Android, appli déjà installée : on invite à l'ouvrir.
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
    PwaInstall.checkInstalled();
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

  Future<void> _showSteps() => showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Theme.of(context).cardTheme.color,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (context) => SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.md,
          AppSpacing.lg,
          AppSpacing.md,
          AppSpacing.md,
        ),
        child: PwaInstallSteps(showReloginNote: widget.showReloginNote),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) {
    final mode = PwaInstall.mode;
    if (_dismissed || mode == PwaInstallMode.none) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);
    final installed = mode == PwaInstallMode.alreadyInstalled;
    // Navigateur intégré (Facebook, Instagram…) : l'installation se fait dans Chrome.
    final openInChrome =
        mode == PwaInstallMode.androidInstructions &&
        PwaInstall.browser == PwaBrowser.inapp;

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
                // L'icône que l'on retrouvera sur l'écran d'accueil.
                ClipRRect(
                  borderRadius: BorderRadius.circular(10),
                  child: Image.asset(
                    'assets/branding/icone_app.png',
                    width: 36,
                    height: 36,
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
                          installed
                              ? 'PressLink est déjà installée'
                              : 'Installer PressLink',
                          style: theme.textTheme.bodyLarge?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 14,
                            color: context.textPrimary,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          installed
                              ? 'Ouvrez-la depuis l\'écran d\'accueil de votre téléphone.'
                              : 'Sur votre écran d\'accueil, comme une application : vos commandes en un toucher.',
                          style: theme.textTheme.bodyMedium?.copyWith(
                            fontSize: 12.5,
                            color: context.textSecondary,
                          ),
                        ),
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
            if (!installed) ...[
              const SizedBox(height: AppSpacing.sm + 4),
              if (widget.alwaysVisible &&
                  mode != PwaInstallMode.nativePrompt &&
                  !openInChrome)
                PwaInstallSteps(
                  showReloginNote: widget.showReloginNote,
                  showTitle: false,
                )
              else
                Padding(
                  padding: EdgeInsets.only(
                    right: widget.alwaysVisible ? 0 : AppSpacing.sm,
                  ),
                  child: SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton.icon(
                      onPressed: switch (mode) {
                        PwaInstallMode.nativePrompt => _install,
                        _ when openInChrome => PwaInstall.openInChrome,
                        _ => _showSteps,
                      },
                      icon: Icon(
                        openInChrome
                            ? Icons.open_in_browser_rounded
                            : Icons.download_rounded,
                        size: 20,
                      ),
                      label: Text(
                        openInChrome ? 'Ouvrir dans Chrome' : 'Installer',
                      ),
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

/// Les gestes pour installer PressLink quand le navigateur n'a pas d'invite :
/// iPhone (Safari, Chrome), Android (Chrome, Samsung Internet, autres).
class PwaInstallSteps extends StatelessWidget {
  const PwaInstallSteps({
    super.key,
    this.showReloginNote = false,
    this.showTitle = true,
  });

  final bool showReloginNote;
  final bool showTitle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final ios = PwaInstall.mode == PwaInstallMode.iosInstructions;
    final browser = PwaInstall.browser;

    final List<List<InlineSpan>> steps;
    String? intro;
    var offerChrome = false;

    if (ios) {
      switch (browser) {
        case PwaBrowser.safari:
          steps = [
            [
              const TextSpan(text: 'Touchez le bouton Partager '),
              _icon(Icons.ios_share),
              const TextSpan(
                text:
                    ' en bas de l\'écran (sur les iPhone récents, il est dans le menu « ••• »).',
              ),
            ],
            [
              const TextSpan(
                text:
                    'Faites défiler, puis touchez « Sur l\'écran d\'accueil » ',
              ),
              _icon(Icons.add_box_outlined),
              const TextSpan(text: '.'),
            ],
            [const TextSpan(text: 'Touchez « Ajouter » en haut à droite.')],
          ];
        case PwaBrowser.chrome:
          steps = [
            [
              const TextSpan(text: 'Touchez Partager '),
              _icon(Icons.ios_share),
              const TextSpan(text: ' dans la barre d\'adresse, en haut.'),
            ],
            [const TextSpan(text: 'Touchez « Sur l\'écran d\'accueil ».')],
            [const TextSpan(text: 'Touchez « Ajouter ».')],
          ];
        default:
          intro =
              'Ce navigateur ne permet pas d\'installer l\'application : ouvrez la page dans Safari.';
          steps = [
            [
              const TextSpan(text: 'Touchez le menu « ••• » ou '),
              _icon(Icons.ios_share),
              const TextSpan(text: ', puis « Ouvrir dans Safari ».'),
            ],
            [
              const TextSpan(text: 'Dans Safari : Partager '),
              _icon(Icons.ios_share),
              const TextSpan(text: ', puis « Sur l\'écran d\'accueil ».'),
            ],
          ];
      }
    } else {
      switch (browser) {
        case PwaBrowser.chrome:
          steps = [
            [
              const TextSpan(text: 'Touchez le menu '),
              _icon(Icons.more_vert),
              const TextSpan(text: ' en haut à droite de Chrome.'),
            ],
            [
              const TextSpan(
                text:
                    'Touchez « Installer l\'application » (ou « Ajouter à l\'écran d\'accueil »).',
              ),
            ],
            [const TextSpan(text: 'Confirmez avec « Installer ».')],
          ];
        case PwaBrowser.samsung:
          offerChrome = true;
          steps = [
            [
              const TextSpan(text: 'Touchez le menu '),
              _icon(Icons.menu),
              const TextSpan(text: ' en bas à droite.'),
            ],
            [
              const TextSpan(
                text:
                    'Touchez « Ajouter la page à », puis « Écran d\'accueil ».',
              ),
            ],
            [const TextSpan(text: 'Confirmez avec « Ajouter ».')],
          ];
        default:
          offerChrome = true;
          intro =
              'Ce navigateur ne permet pas toujours l\'installation. Dans Chrome, elle se fait en un toucher.';
          steps = [
            [
              const TextSpan(
                text:
                    'Ou cherchez « Installer » / « Ajouter à l\'écran d\'accueil » dans le menu de votre navigateur.',
              ),
            ],
          ];
      }
    }

    final textStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 13.5,
      color: context.textPrimary,
      height: 1.4,
    );

    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (showTitle) ...[
          Text(
            'Installer PressLink',
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
        ],
        if (intro != null) ...[
          Text(intro, style: textStyle?.copyWith(color: context.textSecondary)),
          const SizedBox(height: AppSpacing.sm + 4),
        ],
        if (offerChrome) ...[
          SizedBox(
            width: double.infinity,
            height: 50,
            child: ElevatedButton.icon(
              onPressed: PwaInstall.openInChrome,
              icon: const Icon(Icons.open_in_browser_rounded, size: 20),
              label: const Text('Ouvrir dans Chrome'),
            ),
          ),
          const SizedBox(height: AppSpacing.sm + 4),
          if (steps.length > 1) ...[
            Text(
              'Ou, dans ce navigateur :',
              style: textStyle?.copyWith(color: context.textSecondary),
            ),
            const SizedBox(height: AppSpacing.sm),
          ],
        ],
        for (var i = 0; i < steps.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm + 2),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (steps.length > 1)
                  Container(
                    width: 24,
                    height: 24,
                    margin: const EdgeInsets.only(right: AppSpacing.sm + 2),
                    alignment: Alignment.center,
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                    child: Text(
                      '${i + 1}',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                Expanded(
                  child: Text.rich(
                    TextSpan(style: textStyle, children: steps[i]),
                  ),
                ),
              ],
            ),
          ),
        if (ios && showReloginNote)
          Text(
            'Vous devrez vous reconnecter dans l\'application installée.',
            style: theme.textTheme.labelSmall?.copyWith(
              color: context.textSecondary,
            ),
          ),
      ],
    );
  }

  static WidgetSpan _icon(IconData icon) => WidgetSpan(
    alignment: PlaceholderAlignment.middle,
    child: Icon(icon, size: 17, color: AppColors.primary),
  );
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
