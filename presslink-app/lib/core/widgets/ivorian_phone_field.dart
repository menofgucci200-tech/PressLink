import 'package:flutter/material.dart';

import '../theme/app_colors.dart';
import '../theme/app_spacing.dart';
import '../utils/ivorian_phone.dart';
import 'app_text_field.dart';

/// Champ de numéro ivoirien : indicatif « +225 » fixe, puis 10 chiffres au
/// plus (la frappe est bloquée au-delà), groupés par deux.
class IvorianPhoneField extends StatelessWidget {
  const IvorianPhoneField({
    required this.controller,
    this.autofocus = false,
    this.onChanged,
    this.onSubmitted,
    super.key,
  });

  final TextEditingController controller;
  final bool autofocus;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          height: 52,
          padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm + 2),
          decoration: BoxDecoration(
            border: Border.all(
              color: theme.dividerTheme.color ?? AppColors.border,
            ),
            borderRadius: BorderRadius.circular(10),
          ),
          child: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IvoryCoastFlag(),
              SizedBox(width: 6),
              Text(
                IvorianPhone.prefix,
                style: TextStyle(fontWeight: FontWeight.w600, fontSize: 15),
              ),
            ],
          ),
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: AppTextField(
            controller: controller,
            keyboardType: TextInputType.phone,
            autofocus: autofocus,
            hintText: '07 08 12 44 00',
            inputFormatters: [IvorianPhoneInputFormatter()],
            onChanged: onChanged,
            onSubmitted: onSubmitted,
          ),
        ),
      ],
    );
  }
}

/// Drapeau ivoirien dessiné (orange, blanc, vert) plutôt que l'emoji du
/// drapeau : un emoji demande une police téléchargée, absente hors connexion.
class IvoryCoastFlag extends StatelessWidget {
  const IvoryCoastFlag({super.key});

  static const _orange = Color(0xFFF77F00);
  static const _green = Color(0xFF009E60);

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: "Côte d'Ivoire",
      child: Container(
        width: 22,
        height: 15,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(2),
          border: Border.all(color: const Color(0x33000000), width: 0.5),
        ),
        clipBehavior: Clip.antiAlias,
        child: const Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(child: ColoredBox(color: _orange)),
            Expanded(child: ColoredBox(color: Colors.white)),
            Expanded(child: ColoredBox(color: _green)),
          ],
        ),
      ),
    );
  }
}
