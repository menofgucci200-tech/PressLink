import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../../features/pickups/presentation/new_pickup_request_screen.dart';
import '../../features/pressings/presentation/join_pressing_screen.dart';
import 'app_page_route.dart';

/// Bouton « + » de la barre du bas. Avec le ramassage lancé, il propose
/// « Faire ramasser mon linge » à tous les clients, puis « Rejoindre un
/// pressing » ; sinon il ouvre directement « Rejoindre un pressing ».
Future<void> openAddAction(BuildContext context) {
  final navigator = Navigator.of(context);
  void open(Widget screen) =>
      navigator.push(AppPageRoute(builder: (_) => screen));

  if (!AppConfig.deliveryEnabled) {
    open(const JoinPressingScreen());
    return Future.value();
  }

  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);

      Widget option({
        required IconData icon,
        required Color color,
        required String title,
        required String subtitle,
        required Widget screen,
      }) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: CircleAvatar(
          backgroundColor: color.withValues(alpha: 0.12),
          child: Icon(icon, color: color),
        ),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right),
        onTap: () {
          Navigator.of(sheetContext).pop();
          open(screen);
        },
      );

      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.md,
            0,
            AppSpacing.md,
            AppSpacing.md,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Que voulez-vous faire ?',
                style: theme.textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.sm),
              option(
                icon: Icons.local_shipping_outlined,
                color: AppColors.primary,
                title: 'Faire ramasser mon linge',
                subtitle:
                    'Un pressing qui passe dans votre commune vient le chercher.',
                screen: const NewPickupRequestScreen(),
              ),
              option(
                icon: Icons.storefront_outlined,
                color: AppColors.successText,
                title: 'Rejoindre un pressing',
                subtitle: 'Avec le code que vous donne le pressing.',
                screen: const JoinPressingScreen(),
              ),
            ],
          ),
        ),
      );
    },
  );
}
