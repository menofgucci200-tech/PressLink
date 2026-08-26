import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_text_field.dart';
import 'auth_controller.dart';

/// Écran mot de passe — affiché quand le numéro correspond à un compte existant.
class LoginPasswordScreen extends ConsumerStatefulWidget {
  const LoginPasswordScreen({required this.phone, super.key});

  final String phone;

  @override
  ConsumerState<LoginPasswordScreen> createState() => _LoginPasswordScreenState();
}

class _LoginPasswordScreenState extends ConsumerState<LoginPasswordScreen> {
  final _passwordController = TextEditingController();
  bool _loading = false;
  bool _obscure = true;
  String? _error;

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).login(
          phone: widget.phone,
          password: _passwordController.text,
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
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AppBackButton(onPressed: () => Navigator.of(context).pop()),
              const SizedBox(height: AppSpacing.lg),
              Text('Content de vous revoir', style: theme.textTheme.headlineSmall),
              const SizedBox(height: 6),
              Text(widget.phone, style: theme.textTheme.bodyMedium?.copyWith(fontFeatures: const [FontFeature.tabularFigures()])),
              const SizedBox(height: AppSpacing.lg),
              AppTextField(
                controller: _passwordController,
                label: 'Mot de passe',
                obscureText: _obscure,
                autofocus: true,
                onSubmitted: (_) => _submit(),
                suffixIcon: IconButton(
                  icon: Icon(_obscure ? Icons.visibility_outlined : Icons.visibility_off_outlined),
                  onPressed: () => setState(() => _obscure = !_obscure),
                ),
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
                      : const Text('Se connecter'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
