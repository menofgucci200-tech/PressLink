import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/widgets/app_page_route.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/utils/ivorian_phone.dart';
import '../../../core/widgets/ivorian_phone_field.dart';
import '../../../core/widgets/pwa_install_banner.dart';
import '../../../core/widgets/staggered_fade_in.dart';
import '../../support/presentation/support_screen.dart';
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
  final _phoneController = TextEditingController();
  bool _loading = false;
  String? _error;

  /// Les 10 chiffres du numéro national, sans les espaces d'affichage.
  String get _digits => IvorianPhone.digitsOf(_phoneController.text);

  String get _fullPhone => IvorianPhone.toStored(_digits);

  @override
  void dispose() {
    _phoneController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    // Moins de 10 chiffres (ou un préfixe qui n'est pas un mobile) : on
    // n'avance pas, on demande un numéro valide.
    final invalid = IvorianPhone.validate(_digits);
    if (invalid != null) {
      setState(() => _error = invalid);
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    final check = await ref
        .read(authControllerProvider.notifier)
        .checkPhone(_fullPhone);

    if (!mounted) return;
    setState(() => _loading = false);

    if (check == null) {
      setState(() => _error = ref.read(authControllerProvider).error);
      return;
    }

    Navigator.of(context).push(
      AppPageRoute(
        builder: (_) => check.exists
            ? LoginPasswordScreen(phone: _fullPhone)
            : RegisterScreen(
                phone: _fullPhone,
                knownBy: check.pending ? check : null,
              ),
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
              _Entrance(
                index: 0,
                child: RichText(
                  text: TextSpan(
                    style: theme.textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                    children: [
                      const TextSpan(text: 'Press'),
                      TextSpan(
                        text: 'Link',
                        style: TextStyle(color: AppColors.primary),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              _Entrance(
                index: 1,
                child: Text(
                  'Votre numéro de téléphone',
                  style: theme.textTheme.headlineSmall,
                ),
              ),
              const SizedBox(height: 6),
              _Entrance(
                index: 2,
                child: Text(
                  'Nous nous en servirons pour retrouver ou créer votre compte.',
                  style: theme.textTheme.bodyMedium,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              _Entrance(
                index: 3,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Téléphone',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        fontSize: 12,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 6),
                    IvorianPhoneField(
                      controller: _phoneController,
                      autofocus: true,
                      onChanged: (_) {
                        if (_error != null) setState(() => _error = null);
                      },
                      onSubmitted: (_) => _loading ? null : _submit(),
                    ),
                  ],
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  _error!,
                  style: TextStyle(
                    color: theme.colorScheme.error,
                    fontSize: 13,
                  ),
                ),
              ],
              const SizedBox(height: AppSpacing.lg),
              _Entrance(
                index: 4,
                child: SizedBox(
                  width: double.infinity,
                  height: 52,
                  child: ElevatedButton(
                    onPressed: _loading ? null : _submit,
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 180),
                      child: _loading
                          ? const SizedBox(
                              key: ValueKey('loading'),
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Continuer', key: ValueKey('label')),
                    ),
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              const _Entrance(index: 5, child: PwaInstallBanner()),
              _Entrance(
                index: 6,
                child: Center(
                  child: TextButton.icon(
                    onPressed: () => Navigator.of(
                      context,
                    ).push(AppPageRoute(builder: (_) => const SupportScreen())),
                    icon: Image.asset(
                      'assets/icons/whatsapp.png',
                      width: 24,
                      height: 24,
                    ),
                    label: const Text(
                      'Contacter le support',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Entrée en cascade des éléments de l'écran de connexion, juste après la
/// sortie de l'écran de démarrage (transition gérée par AuthGate).
class _Entrance extends StatelessWidget {
  const _Entrance({required this.index, required this.child});

  final int index;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return StaggeredFadeIn(
      index: index,
      stepMs: 25,
      durationMs: 220,
      slide: 0.06,
      child: child,
    );
  }
}
