import 'package:flutter/material.dart';

import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/presslink_top_bar.dart';

/// Écran générique pour les pages de contenu statique
/// (Aide). Les CGU et la politique de confidentialité sont sur le site.
class StaticPageScreen extends StatelessWidget {
  const StaticPageScreen({
    required this.title,
    required this.sections,
    super.key,
  });

  final String title;
  final List<(String heading, String body)> sections;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: const PressLinkTopBar(),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      fontSize: 16,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            for (final (heading, body) in sections) ...[
              Text(
                heading,
                style: theme.textTheme.headlineSmall?.copyWith(fontSize: 15),
              ),
              const SizedBox(height: 8),
              Text(
                body,
                style: theme.textTheme.bodyMedium?.copyWith(height: 1.5),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ],
        ),
      ),
    );
  }
}

/// Contenu de l'aide — Cahier §Profil.
class StaticPages {
  static const help = [
    (
      'Comment suivre ma commande ?',
      'Ouvrez l\'onglet Commandes pour voir toutes vos commandes en cours. Appuyez sur une commande pour voir le détail et l\'avancement.',
    ),
    (
      'Comment rejoindre un pressing ?',
      'Depuis l\'accueil, appuyez sur "Ajouter un pressing" et entrez le code fourni par votre pressing (ex. PE-4821).',
    ),
    (
      'Je ne reçois pas de notifications',
      'Vérifiez que les notifications sont activées dans votre profil, ainsi que dans les réglages de votre téléphone pour l\'application PressLink.',
    ),
    (
      'Besoin d\'aide supplémentaire ?',
      'Contactez le support PressLink au 07 18 00 22 93 (appel ou WhatsApp) depuis Menu > Aide & support. Pour une question sur une commande, vous pouvez aussi joindre directement votre pressing : ses coordonnées sont visibles depuis l\'onglet Accueil, section "Mes pressings".',
    ),
  ];
}
