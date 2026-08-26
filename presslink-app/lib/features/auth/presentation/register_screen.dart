import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../domain/auth_repository.dart';
import 'auth_controller.dart';

/// Formulaire d'inscription — affiché quand le numéro ne correspond à
/// aucun compte existant. Cahier §3.1 (revu le 26/08).
class RegisterScreen extends ConsumerStatefulWidget {
  const RegisterScreen({required this.phone, super.key});

  final String phone;

  @override
  ConsumerState<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends ConsumerState<RegisterScreen> {
  final _firstNameController = TextEditingController();
  final _lastNameController = TextEditingController();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();

  Gender? _gender;
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  Future<void> _submit() async {
    if (_gender == null) {
      setState(() => _error = 'Merci de sélectionner votre genre.');
      return;
    }
    if (_passwordController.text.length < 4) {
      setState(() => _error = 'Le mot de passe doit contenir au moins 4 caractères.');
      return;
    }
    if (_passwordController.text != _confirmController.text) {
      setState(() => _error = 'La confirmation du mot de passe ne correspond pas.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).register(
          phone: widget.phone,
          firstName: _firstNameController.text.trim(),
          lastName: _lastNameController.text.trim(),
          gender: _gender!,
          password: _passwordController.text,
          email: _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
        );

    if (!mounted) return;
    setState(() => _loading = false);

    if (!ok) {
      setState(() => _error = ref.read(authControllerProvider).error);
      return;
    }

    // L'AuthGate (main.dart) bascule vers MainShell, mais cet écran a été
    // empilé par-dessus via Navigator.push : il faut le dépiler pour que
    // le nouveau contenu redevienne visible.
    Navigator.of(context).popUntil((route) => route.isFirst);
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
              AppBackButton(onPressed: () => Navigator.of(context).pop()),
              const SizedBox(height: AppSpacing.lg),
              Text('Créer votre compte', style: theme.textTheme.headlineSmall),
              const SizedBox(height: 6),
              Text(widget.phone, style: theme.textTheme.bodyMedium?.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
              const SizedBox(height: AppSpacing.lg),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: AppTextField(
                      controller: _firstNameController,
                      label: 'Prénom',
                      textCapitalization: TextCapitalization.words,
                      autofocus: true,
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: AppTextField(
                      controller: _lastNameController,
                      label: 'Nom',
                      textCapitalization: TextCapitalization.words,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              Text('Genre', style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12, fontWeight: FontWeight.w500)),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(child: _GenderButton(gender: Gender.homme, selected: _gender, onTap: (g) => setState(() => _gender = g))),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: _GenderButton(gender: Gender.femme, selected: _gender, onTap: (g) => setState(() => _gender = g))),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _emailController,
                label: 'Email (facultatif)',
                keyboardType: TextInputType.emailAddress,
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _passwordController,
                label: 'Mot de passe (4 caractères min.)',
                obscureText: _obscure,
                suffixIcon: IconButton(
                  icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _confirmController,
                label: 'Confirmer le mot de passe',
                obscureText: _obscure,
                onSubmitted: (_) => _submit(),
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
                      : const Text('Créer mon compte'),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
            ],
          ),
        ),
      ),
    );
  }
}

class _GenderButton extends StatelessWidget {
  const _GenderButton({required this.gender, required this.selected, required this.onTap});

  final Gender gender;
  final Gender? selected;
  final ValueChanged<Gender> onTap;

  @override
  Widget build(BuildContext context) {
    final isSelected = selected == gender;
    return SizedBox(
      height: 44,
      child: OutlinedButton(
        onPressed: () => onTap(gender),
        style: OutlinedButton.styleFrom(
          backgroundColor: isSelected ? AppColors.primaryTint : Colors.white,
          side: BorderSide(color: isSelected ? AppColors.primary : AppColors.border),
          foregroundColor: isSelected ? AppColors.primary : AppColors.textSecondary,
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: Text(gender.label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
      ),
    );
  }
}
