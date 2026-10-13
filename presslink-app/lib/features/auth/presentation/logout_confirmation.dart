import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/app_feedback.dart';
import 'auth_controller.dart';

/// Déconnexion après confirmation — même boîte depuis l'accueil et le profil.
Future<void> confirmAndLogout(BuildContext context, WidgetRef ref) async {
  final confirmed = await showAppConfirm(
    context,
    title: 'Se déconnecter ?',
    message:
        'Vous devrez saisir à nouveau votre numéro et votre mot de passe pour suivre vos commandes.',
    confirmLabel: 'Se déconnecter',
    tone: AppConfirmTone.danger,
    icon: Icons.logout_rounded,
  );
  if (confirmed) {
    await ref.read(authControllerProvider.notifier).logout();
  }
}
