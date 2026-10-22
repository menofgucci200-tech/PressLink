import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/notifications/push_notification_service.dart';
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
    // Téléphone partagé : il ne doit plus recevoir les notifications de ce
    // compte. Le serveur oublie son identifiant push, OneSignal le détache.
    final push = ref.read(pushNotificationServiceProvider);
    final playerId = await push.currentPlayerId();
    await ref.read(authControllerProvider.notifier).logout(playerId: playerId);
    await push.unlinkDevice();
  }
}
