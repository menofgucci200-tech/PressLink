import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/data/countries.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/country_code_picker.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../../auth/presentation/auth_controller.dart';
import 'profile_screen.dart';

/// Changer de numéro de téléphone. Le numéro sert à se connecter : le mot
/// de passe actuel est demandé et le nouveau numéro confirmé avant l'envoi.
class ChangePhoneScreen extends ConsumerStatefulWidget {
  const ChangePhoneScreen({super.key});

  @override
  ConsumerState<ChangePhoneScreen> createState() => _ChangePhoneScreenState();
}

class _ChangePhoneScreenState extends ConsumerState<ChangePhoneScreen> {
  late Country _country;
  final _phoneController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscure = true;
  bool _loading = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    final current = ref.read(authControllerProvider).customer?.phone ?? '';
    // Indicatif du numéro actuel par défaut (le plus long qui correspond,
    // ex. +225 plutôt que +2).
    final matching =
        kCountries.where((c) => current.startsWith(c.dialCode)).toList()
          ..sort((a, b) => b.dialCode.length.compareTo(a.dialCode.length));
    _country = matching.isNotEmpty ? matching.first : kCountries.first;
  }

  @override
  void dispose() {
    _phoneController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  String get _fullPhone =>
      '${_country.dialCode}${_phoneController.text.replaceAll(RegExp(r'[^0-9]'), '')}';

  Future<void> _submit() async {
    final phone = _fullPhone;
    final current = ref.read(authControllerProvider).customer?.phone;

    String? error;
    if (_phoneController.text.trim().isEmpty) {
      error = 'Indiquez votre nouveau numéro.';
    } else if (!RegExp(r'^\+[1-9]\d{6,14}$').hasMatch(phone)) {
      error = 'Ce numéro ne semble pas valide. Vérifiez-le.';
    } else if (phone == current) {
      error = 'C\'est déjà votre numéro actuel.';
    } else if (_passwordController.text.isEmpty) {
      error = 'Indiquez votre mot de passe actuel.';
    }
    if (error != null) {
      setState(() => _error = error);
      return;
    }

    final confirmed = await showAppConfirm(
      context,
      title: 'Changer de numéro',
      message:
          'Vous vous connecterez désormais avec le ${formatPhoneForDisplay(phone)}. Vérifiez qu\'il est correct.',
      confirmLabel: 'Confirmer',
      icon: Icons.phone_iphone,
    );
    if (!confirmed || !mounted) return;

    setState(() {
      _loading = true;
      _error = null;
    });

    final ok = await ref
        .read(authControllerProvider.notifier)
        .updatePhone(phone: phone, currentPassword: _passwordController.text);

    if (!mounted) return;
    setState(() {
      _loading = false;
      if (!ok) _error = ref.read(authControllerProvider).error;
    });
    if (ok) {
      showAppToast(
        context,
        'Numéro mis à jour. Utilisez-le désormais pour vous connecter.',
      );
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final customer = ref.watch(authControllerProvider).customer;
    final labelStyle = theme.textTheme.bodyMedium?.copyWith(
      fontSize: 12,
      fontWeight: FontWeight.w500,
    );

    return Scaffold(
      appBar: const PressLinkTopBar(),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text(
                    'Changer de numéro',
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.md),
                decoration: BoxDecoration(
                  color: theme.cardTheme.color,
                  border: Border.all(color: theme.dividerTheme.color!),
                  borderRadius: BorderRadius.circular(AppRadius.lg),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: isDark
                            ? AppColors.primary.withValues(alpha: 0.18)
                            : AppColors.primaryTint,
                        borderRadius: BorderRadius.circular(AppRadius.md),
                      ),
                      child: Icon(
                        Icons.phone_outlined,
                        size: 20,
                        color: isDark
                            ? const Color(0xFF93C5FD)
                            : AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm + 4),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Numéro actuel', style: labelStyle),
                          const SizedBox(height: 2),
                          Text(
                            formatPhoneForDisplay(customer?.phone ?? ''),
                            style: const TextStyle(
                              fontSize: 15,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text('Nouveau numéro', style: labelStyle),
              const SizedBox(height: 6),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  CountryCodePicker(
                    selected: _country,
                    onChanged: (country) => setState(() => _country = country),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: AppTextField(
                      controller: _phoneController,
                      keyboardType: TextInputType.phone,
                      autofocus: true,
                      hintText: '07 XX XX XX XX',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _passwordController,
                label: 'Mot de passe actuel',
                obscureText: _obscure,
                suffixIcon: IconButton(
                  tooltip: _obscure
                      ? 'Afficher le mot de passe'
                      : 'Masquer le mot de passe',
                  icon: Icon(
                    _obscure
                        ? Icons.visibility_outlined
                        : Icons.visibility_off_outlined,
                  ),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: AppSpacing.sm + 4),
              Text(
                'Après le changement, connectez-vous avec ce nouveau numéro. '
                'Vos commandes et vos pressings restent liés à votre compte.',
                style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5),
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm + 4),
                Text(
                  _error!,
                  style: TextStyle(
                    color: theme.colorScheme.error,
                    fontSize: 13,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Text('Changer de numéro'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
