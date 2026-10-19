import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/config/app_config.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/presslink_top_bar.dart';

/// Aide & support — appel direct ou WhatsApp (même numéro). Accessible sans
/// être connecté (depuis l'écran de connexion) : le nom du client n'est
/// ajouté au message WhatsApp que s'il est connu.
class SupportScreen extends ConsumerWidget {
  const SupportScreen({super.key});

  static const _whatsAppGreen = Color(0xFF25D366);

  Uri get _callUri => Uri(scheme: 'tel', path: AppConfig.supportPhoneE164);

  Uri _whatsAppUri(String? customerName) {
    final who = (customerName != null && customerName.isNotEmpty)
        ? 'Bonjour, je suis $customerName et j\'ai besoin d\'aide avec PressLink.'
        : 'Bonjour, j\'ai besoin d\'aide avec PressLink.';
    return whatsAppUri(who);
  }

  /// Discussion WhatsApp avec le support, message déjà rédigé.
  static Uri whatsAppUri(String message) => Uri.https(
    'wa.me',
    AppConfig.supportPhoneE164.replaceFirst('+', ''),
    {'text': message},
  );

  /// Ouvre WhatsApp (ou l'appel) ; un message s'affiche si c'est impossible.
  static Future<void> open(BuildContext context, Uri uri) async {
    var opened = false;
    try {
      // Dans un navigateur (PWA), `externalApplication` n'est pas supporté
      // par url_launcher_web et fait échouer l'ouverture : on y laisse le
      // mode par défaut (nouvel onglet pour WhatsApp, même fenêtre pour tel:).
      opened = await launchUrl(
        uri,
        mode: kIsWeb
            ? LaunchMode.platformDefault
            : LaunchMode.externalApplication,
        webOnlyWindowName: uri.scheme == 'https' ? '_blank' : '_self',
      );
    } catch (_) {
      opened = false;
    }
    if (!opened && context.mounted) {
      showAppToast(
        context,
        'Impossible d\'ouvrir l\'application. Appelez le ${AppConfig.supportPhoneDisplay}.',
        type: AppToastType.error,
      );
    }
  }

  Future<void> _copyNumber(BuildContext context) async {
    await Clipboard.setData(
      const ClipboardData(text: AppConfig.supportPhoneE164),
    );
    if (!context.mounted) return;
    showAppToast(context, 'Numéro copié.');
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final customer = ref.watch(authControllerProvider).customer;

    return Scaffold(
      appBar: const PressLinkTopBar(title: 'Aide & support'),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Container(
              padding: const EdgeInsets.all(AppSpacing.lg),
              decoration: BoxDecoration(
                color: isDark
                    ? AppColors.primary.withValues(alpha: 0.16)
                    : AppColors.primaryTint,
                border: Border.all(
                  color: AppColors.primary.withValues(
                    alpha: isDark ? 0.35 : 0.2,
                  ),
                ),
                borderRadius: BorderRadius.circular(AppRadius.xl),
              ),
              child: Column(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: const BoxDecoration(
                      color: AppColors.primary,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.support_agent,
                      size: 28,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    'Une question ? Un problème ?',
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontSize: 20,
                      fontWeight: FontWeight.w700,
                      color: isDark
                          ? AppColors.textPrimaryDark
                          : AppColors.textPrimary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  Text(
                    'Contactez l\'équipe PressLink : par appel, sur WhatsApp ou par email.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: isDark
                          ? AppColors.textSecondaryDark
                          : AppColors.textSecondary,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: AppSpacing.md),
                  InkWell(
                    onTap: () => _copyNumber(context),
                    borderRadius: BorderRadius.circular(AppRadius.md),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: AppSpacing.sm,
                        vertical: AppSpacing.xs,
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            AppConfig.supportPhoneDisplay,
                            style: theme.textTheme.headlineSmall?.copyWith(
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.5,
                              color: isDark
                                  ? const Color(0xFF93C5FD)
                                  : AppColors.primaryDark,
                            ),
                          ),
                          const SizedBox(width: AppSpacing.sm),
                          Icon(
                            Icons.copy,
                            size: 16,
                            color: isDark
                                ? AppColors.textSecondaryDark
                                : AppColors.textSecondary,
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                onPressed: () => open(context, _callUri),
                icon: const _ContactIcon('assets/icons/phone.png'),
                label: const Text('Appeler le support'),
              ),
            ),
            const SizedBox(height: AppSpacing.sm + 2),
            SizedBox(
              height: 52,
              child: ElevatedButton.icon(
                onPressed: () =>
                    open(context, _whatsAppUri(customer?.fullName)),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _whatsAppGreen,
                  foregroundColor: Colors.white,
                ),
                icon: const _ContactIcon('assets/icons/whatsapp.png'),
                label: const Text('Écrire sur WhatsApp'),
              ),
            ),
            const SizedBox(height: AppSpacing.sm + 2),
            SizedBox(
              height: 52,
              child: OutlinedButton.icon(
                onPressed: () => open(
                  context,
                  Uri(
                    scheme: 'mailto',
                    path: AppConfig.supportEmail,
                    query:
                        'subject=${Uri.encodeComponent('Support PressLink')}',
                  ),
                ),
                icon: const Icon(Icons.mail_outline, size: 22),
                label: const Text(AppConfig.supportEmail),
              ),
            ),
            const SizedBox(height: AppSpacing.lg),
            Container(
              padding: const EdgeInsets.all(AppSpacing.md),
              decoration: BoxDecoration(
                color: theme.cardTheme.color,
                border: Border.all(color: theme.dividerTheme.color!),
                borderRadius: BorderRadius.circular(AppRadius.lg),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.lightbulb_outline,
                    size: 18,
                    color: AppColors.warning,
                  ),
                  const SizedBox(width: AppSpacing.sm + 2),
                  Expanded(
                    child: Text(
                      'Pour une réponse plus rapide, indiquez votre numéro de commande (ex. PL-123456) et le pressing concerné.',
                      style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pastille ronde (logo WhatsApp ou combiné téléphonique) des boutons de contact.
class _ContactIcon extends StatelessWidget {
  const _ContactIcon(this.asset);

  final String asset;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(1.5),
      decoration: const BoxDecoration(
        color: Colors.white,
        shape: BoxShape.circle,
      ),
      child: Image.asset(asset, width: 26, height: 26),
    );
  }
}
