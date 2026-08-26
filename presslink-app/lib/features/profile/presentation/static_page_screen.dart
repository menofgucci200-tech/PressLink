import 'package:flutter/material.dart';

import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';

/// Écran générique pour les pages de contenu statique
/// (Aide, Conditions d'utilisation, Politique de confidentialité).
class StaticPageScreen extends StatelessWidget {
  const StaticPageScreen({required this.title, required this.sections, super.key});

  final String title;
  final List<(String heading, String body)> sections;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.md),
          children: [
            Row(
              children: [
                AppBackButton(onPressed: () => Navigator.of(context).pop()),
                const SizedBox(width: AppSpacing.sm + 2),
                Expanded(
                  child: Text(title, style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600, fontSize: 16)),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.lg),
            for (final (heading, body) in sections) ...[
              Text(heading, style: theme.textTheme.headlineSmall?.copyWith(fontSize: 15)),
              const SizedBox(height: 8),
              Text(body, style: theme.textTheme.bodyMedium?.copyWith(height: 1.5)),
              const SizedBox(height: AppSpacing.lg),
            ],
          ],
        ),
      ),
    );
  }
}

/// Contenu — Cahier §Profil (aide, CGU, confidentialité). Textes provisoires
/// pour le pilote, à faire relire juridiquement avant lancement public.
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
      'Contactez directement votre pressing : ses coordonnées sont visibles depuis l\'onglet Accueil, section "Mes pressings".',
    ),
  ];

  static const terms = [
    (
      'Objet',
      'PressLink met en relation les pressings et leurs clients pour le suivi digital des commandes de pressing. L\'application est fournie gratuitement aux clients.',
    ),
    (
      'Compte utilisateur',
      'Vous êtes responsable de la confidentialité de votre mot de passe. Un numéro de téléphone ne peut être associé qu\'à un seul compte.',
    ),
    (
      'Utilisation du service',
      'PressLink ne réalise pas lui-même les prestations de pressing : celles-ci sont assurées par l\'établissement partenaire que vous avez rejoint.',
    ),
  ];

  static const privacy = [
    (
      'Données collectées',
      'Nom, prénom, numéro de téléphone, genre et email (facultatif) sont collectés pour créer et sécuriser votre compte.',
    ),
    (
      'Utilisation des données',
      'Vos données sont utilisées uniquement pour le fonctionnement du service : suivi de vos commandes et notifications liées.',
    ),
    (
      'Partage avec les pressings',
      'Les pressings que vous rejoignez ont accès à votre nom et numéro de téléphone afin de gérer vos commandes.',
    ),
  ];
}
