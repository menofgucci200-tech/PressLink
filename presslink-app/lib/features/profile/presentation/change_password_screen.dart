import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../auth/presentation/auth_controller.dart';

/// Changer de mot de passe — Profil §Personnalisation (26/08).
class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final _currentController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  String? _error;
  String? _success;

  Future<void> _submit() async {
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
      _success = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).updatePassword(
          currentPassword: _currentController.text,
          password: _passwordController.text,
        );

    if (!mounted) return;
    setState(() {
      _loading = false;
      if (ok) {
        _success = 'Mot de passe mis à jour.';
        _currentController.clear();
        _passwordController.clear();
        _confirmController.clear();
      } else {
        _error = ref.read(authControllerProvider).error;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  AppBackButton(onPressed: () => Navigator.of(context).pop()),
                  const SizedBox(width: AppSpacing.sm + 2),
                  Text('Changer de mot de passe', style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600, fontSize: 16)),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              AppTextField(
                controller: _currentController,
                label: 'Mot de passe actuel',
                obscureText: _obscure,
                autofocus: true,
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _passwordController,
                label: 'Nouveau mot de passe (4 caractères min.)',
                obscureText: _obscure,
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _confirmController,
                label: 'Confirmer le nouveau mot de passe',
                obscureText: _obscure,
                suffixIcon: IconButton(
                  icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
                onSubmitted: (_) => _submit(),
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(_error!, style: TextStyle(color: theme.colorScheme.error, fontSize: 13)),
              ],
              if (_success != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(_success!, style: const TextStyle(color: AppColors.successText, fontSize: 13)),
              ],
              const SizedBox(height: AppSpacing.lg),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: _loading ? null : _submit,
                  child: _loading
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Mettre à jour'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
