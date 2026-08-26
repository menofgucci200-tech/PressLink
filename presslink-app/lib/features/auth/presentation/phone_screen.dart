import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/data/countries.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../../core/widgets/country_code_picker.dart';
import 'auth_controller.dart';
import 'login_password_screen.dart';
import 'register_screen.dart';

/// Première étape de l'auth client — Cahier §3.1 (revu le 26/08) :
/// on demande le numéro, puis on oriente vers connexion ou inscription.
class PhoneScreen extends ConsumerStatefulWidget {
  const PhoneScreen({super.key});

  @override
  ConsumerState<PhoneScreen> createState() => _PhoneScreenState();
}

class _PhoneScreenState extends ConsumerState<PhoneScreen> {
  Country _country = kCountries.first; // Tanzanie (+255) par défaut
  final _phoneController = TextEditingController();
  bool _loading = false;
  String? _error;

  String get _fullPhone => '${_country.dialCode}${_phoneController.text.replaceAll(' ', '')}';

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final exists = await ref.read(authControllerProvider.notifier).checkPhoneExists(_fullPhone);

    if (!mounted) return;
    setState(() => _loading = false);

    if (exists == null) {
      setState(() => _error = ref.read(authControllerProvider).error);
      return;
    }

    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => exists ? LoginPasswordScreen(phone: _fullPhone) : RegisterScreen(phone: _fullPhone),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: AppSpacing.xxxl),
              RichText(
                text: TextSpan(
                  style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
                  children: [
                    const TextSpan(text: 'Press'),
                    TextSpan(text: 'Link', style: TextStyle(color: AppColors.primary)),
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              Text('Votre numéro de téléphone', style: theme.textTheme.headlineSmall),
              const SizedBox(height: 6),
              Text(
                'Nous nous en servirons pour retrouver ou créer votre compte.',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Téléphone',
                style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12, fontWeight: FontWeight.w500),
              ),
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
                      hintText: '01 XX XX XX XX',
                    ),
                  ),
                ],
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(_error!, style: TextStyle(color: theme.colorScheme.error, fontSize: 13)),
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
                          child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                        )
                      : const Text('Continuer'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
