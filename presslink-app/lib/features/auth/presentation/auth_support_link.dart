import 'package:flutter/material.dart';

import '../../../core/widgets/app_page_route.dart';
import '../../support/presentation/support_screen.dart';

/// « Besoin d'aide ? Contacter le support PressLink » : présent sur tout le
/// parcours d'inscription et de connexion (numéro, mot de passe, création
/// du compte), pour qu'un client bloqué ne reste jamais sans solution.
class AuthSupportLink extends StatelessWidget {
  const AuthSupportLink({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text("Besoin d'aide ?", style: theme.textTheme.bodySmall),
          TextButton.icon(
            onPressed: () => Navigator.of(
              context,
            ).push(AppPageRoute(builder: (_) => const SupportScreen())),
            icon: Image.asset(
              'assets/icons/whatsapp.png',
              width: 24,
              height: 24,
            ),
            label: const Text(
              'Contacter le support PressLink',
              style: TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}
