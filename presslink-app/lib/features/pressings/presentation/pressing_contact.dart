import 'package:flutter/foundation.dart' show kIsWeb;
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/utils/ivorian_phone.dart';
import '../../../core/widgets/app_feedback.dart';
import '../domain/pressing_repository.dart';

const _whatsAppGreen = Color(0xFF25D366);

/// Les façons de joindre le pressing (appel, WhatsApp, e-mail), avec un
/// message déjà rédigé quand il s'agit d'une commande.
class _ContactAction {
  const _ContactAction(this.label, this.icon, this.color, this.uri);

  final String label;
  final IconData icon;
  final Color color;
  final Uri uri;
}

List<_ContactAction> _actions(
  PressingContact contact, {
  required String pressingName,
  String? orderNumber,
}) {
  final message = orderNumber != null
      ? 'Bonjour $pressingName, je vous contacte au sujet de ma commande $orderNumber.'
      : 'Bonjour $pressingName, ';
  String digits(String phone) => phone.replaceAll(RegExp(r'\D'), '');

  return [
    if (contact.phone != null)
      _ContactAction(
        'Appeler',
        Icons.call_rounded,
        AppColors.primary,
        Uri(scheme: 'tel', path: contact.phone),
      ),
    if (contact.whatsapp != null)
      _ContactAction(
        'WhatsApp',
        Icons.chat_rounded,
        _whatsAppGreen,
        Uri.https('wa.me', digits(contact.whatsapp!), {'text': message}),
      ),
    if (contact.email != null)
      _ContactAction(
        'E-mail',
        Icons.mail_outline_rounded,
        AppColors.primary,
        Uri(
          scheme: 'mailto',
          path: contact.email,
          query:
              'subject=${Uri.encodeComponent(orderNumber != null ? 'Commande $orderNumber' : 'Question')}',
        ),
      ),
  ];
}

Future<void> _open(BuildContext context, Uri uri) async {
  var opened = false;
  try {
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
      'Impossible d\'ouvrir cette application sur votre téléphone.',
      type: AppToastType.error,
    );
  }
}

/// Boutons « Appeler / WhatsApp / E-mail » de la fiche du pressing.
class PressingContactButtons extends StatelessWidget {
  const PressingContactButtons({
    super.key,
    required this.contact,
    required this.pressingName,
  });

  final PressingContact contact;
  final String pressingName;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final actions = _actions(contact, pressingName: pressingName);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Icône au-dessus du libellé : les trois boutons tiennent sur une
        // ligne même sur un petit téléphone, sans couper « WhatsApp ».
        Row(
          children: [
            for (final action in actions) ...[
              Expanded(
                child: SizedBox(
                  height: 64,
                  child: OutlinedButton(
                    onPressed: () => _open(context, action.uri),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 4),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(action.icon, size: 20, color: action.color),
                        const SizedBox(height: 4),
                        Text(
                          action.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelMedium,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              if (action != actions.last) const SizedBox(width: AppSpacing.sm),
            ],
          ],
        ),
        if (contact.hours != null) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(contact.hours!, style: theme.textTheme.bodySmall),
        ],
      ],
    );
  }
}

/// Feuille « Contacter le pressing » ouverte depuis une commande.
Future<void> showPressingContactSheet(
  BuildContext context, {
  required PressingContact contact,
  required String pressingName,
  String? orderNumber,
}) {
  final actions = _actions(
    contact,
    pressingName: pressingName,
    orderNumber: orderNumber,
  );

  return showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) {
      final theme = Theme.of(sheetContext);
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
                'Contacter $pressingName',
                style: theme.textTheme.titleMedium,
              ),
              if (contact.hours != null) ...[
                const SizedBox(height: 4),
                Text(contact.hours!, style: theme.textTheme.bodySmall),
              ],
              const SizedBox(height: AppSpacing.sm),
              for (final action in actions)
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: CircleAvatar(
                    backgroundColor: action.color.withValues(alpha: 0.12),
                    child: Icon(action.icon, color: action.color),
                  ),
                  title: Text(action.label),
                  subtitle: Text(switch (action.uri.scheme) {
                    'tel' => IvorianPhone.format(contact.phone!),
                    'mailto' => contact.email!,
                    _ => IvorianPhone.format(contact.whatsapp!),
                  }),
                  onTap: () {
                    Navigator.of(sheetContext).pop();
                    _open(context, action.uri);
                  },
                ),
            ],
          ),
        ),
      );
    },
  );
}
