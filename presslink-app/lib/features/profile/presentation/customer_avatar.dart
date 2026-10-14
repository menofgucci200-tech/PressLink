import 'package:flutter/material.dart';

import '../../../core/theme/app_colors.dart';
import '../../auth/domain/auth_repository.dart';

/// Photo de profil du client, ou ses initiales tant qu'il n'en a pas
/// (ou si l'image ne se charge pas) — accueil, menu et profil.
class CustomerAvatar extends StatelessWidget {
  const CustomerAvatar({super.key, required this.customer, this.radius = 20});

  final Customer? customer;
  final double radius;

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final photoUrl = customer?.photoUrl;

    return CircleAvatar(
      radius: radius,
      backgroundColor: isDark
          ? AppColors.primary.withValues(alpha: 0.22)
          : AppColors.primaryTint,
      foregroundImage: photoUrl != null ? NetworkImage(photoUrl) : null,
      child: Text(
        customer?.initials ?? '?',
        style: TextStyle(
          color: isDark ? const Color(0xFF93C5FD) : AppColors.primary,
          fontWeight: FontWeight.w700,
          fontSize: radius * 0.62,
        ),
      ),
    );
  }
}
