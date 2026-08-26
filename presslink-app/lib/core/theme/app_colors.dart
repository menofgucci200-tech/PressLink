import 'package:flutter/material.dart';

/// PressLink design tokens — couleurs.
/// Source : Design System PressLink §2-4 (palette officielle).
abstract final class AppColors {
  // Marque
  static const primary = Color(0xFF2563EB);
  static const primaryDark = Color(0xFF1D4ED8);
  static const primaryTint = Color(0xFFEFF4FE);
  static const secondary = Color(0xFF06B6D4);

  // Sémantique
  static const success = Color(0xFF10B981);
  static const successTint = Color(0xFFECFDF5);
  static const successText = Color(0xFF059669);

  static const warning = Color(0xFFF59E0B);
  static const warningTint = Color(0xFFFFFBEB);
  static const warningText = Color(0xFFB45309);

  static const error = Color(0xFFEF4444);
  static const errorTint = Color(0xFFFEF2F2);

  static const info = Color(0xFF3B82F6);
  static const infoTint = Color(0xFFEFF6FF);

  // Neutres
  static const background = Color(0xFFF8FAFC);
  static const surface = Color(0xFFFFFFFF);
  static const border = Color(0xFFE2E8F0);
  static const textPrimary = Color(0xFF0F172A);
  static const textSecondary = Color(0xFF475569);
  static const textMuted = Color(0xFF94A3B8);

  // Dark mode (déclinaison cohérente, mêmes rôles)
  static const backgroundDark = Color(0xFF0B1220);
  static const surfaceDark = Color(0xFF121B2E);
  static const borderDark = Color(0xFF223049);
  static const textPrimaryDark = Color(0xFFF1F5F9);
  static const textSecondaryDark = Color(0xFFA9B7CC);
  static const textMutedDark = Color(0xFF6B7A93);

  const AppColors._();
}

/// Couleurs des statuts de commande — cf. Design System §4 / Cahier §8.
/// Le libellé doit toujours accompagner la couleur (jamais la couleur seule).
enum OrderStatus { recue, traitement, prete, recuperee, attente, annulee }

extension OrderStatusStyle on OrderStatus {
  String get label => switch (this) {
        OrderStatus.recue => 'Reçue',
        OrderStatus.traitement => 'En traitement',
        OrderStatus.prete => 'Prête',
        OrderStatus.recuperee => 'Récupérée',
        OrderStatus.attente => 'En attente',
        OrderStatus.annulee => 'Annulée',
      };

  Color get color => switch (this) {
        OrderStatus.recue => AppColors.info,
        OrderStatus.traitement => AppColors.secondary,
        OrderStatus.prete => AppColors.successText,
        OrderStatus.recuperee => AppColors.textSecondary,
        OrderStatus.attente => AppColors.warningText,
        OrderStatus.annulee => AppColors.error,
      };

  Color get tint => switch (this) {
        OrderStatus.recue => AppColors.infoTint,
        OrderStatus.traitement => AppColors.secondary.withValues(alpha: 0.14),
        OrderStatus.prete => AppColors.successTint,
        OrderStatus.recuperee => AppColors.border,
        OrderStatus.attente => AppColors.warningTint,
        OrderStatus.annulee => AppColors.errorTint,
      };
}
