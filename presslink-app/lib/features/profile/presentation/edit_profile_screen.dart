import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../auth/domain/auth_repository.dart';
import '../../auth/presentation/auth_controller.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import 'profile_photo_picker.dart';

/// Modifier mon profil — Profil §Personnalisation (26/08).
class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});

  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _emailController;
  Gender? _gender;
  bool _loading = false;
  String? _error;

  // Au lancement de l'app, la session est restaurée depuis le token stocké
  // et `customer` peut donc ne pas encore être chargé quand cet écran
  // s'ouvre (voir AuthController._restoreSession, appel réseau asynchrone).
  // On ne pré-remplit les champs qu'une seule fois, dès que les données
  // arrivent, pour ne pas écraser une saisie en cours de l'utilisateur à
  // chaque mise à jour ultérieure du provider (ex. changement de photo).
  bool _fieldsInitialized = false;

  @override
  void initState() {
    super.initState();
    _firstNameController = TextEditingController();
    _lastNameController = TextEditingController();
    _emailController = TextEditingController();
    final customer = ref.read(authControllerProvider).customer;
    if (customer != null) _populateFields(customer);
  }

  void _populateFields(Customer customer) {
    _firstNameController.text = customer.firstName;
    _lastNameController.text = customer.lastName;
    _emailController.text = customer.email ?? '';
    _gender = customer.gender == 'femme'
        ? Gender.femme
        : customer.gender == 'homme'
        ? Gender.homme
        : null;
    _fieldsInitialized = true;
  }

  Future<void> _submit() async {
    if (_gender == null) {
      setState(() => _error = 'Merci de sélectionner votre genre.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    final ok = await ref
        .read(authControllerProvider.notifier)
        .updateProfile(
          firstName: _firstNameController.text.trim(),
          lastName: _lastNameController.text.trim(),
          gender: _gender!,
          email: _emailController.text.trim().isEmpty
              ? null
              : _emailController.text.trim(),
        );

    if (!mounted) return;
    setState(() {
      _loading = false;
      if (!ok) _error = ref.read(authControllerProvider).error;
    });
    if (ok) showAppToast(context, 'Profil mis à jour.');
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final customer = ref.watch(authControllerProvider).customer;

    if (!_fieldsInitialized && customer != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (!mounted || _fieldsInitialized) return;
        setState(() => _populateFields(customer));
      });
    }

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
                    'Modifier mon profil',
                    style: theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      fontSize: 16,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              const Center(child: EditableCustomerAvatar()),
              const SizedBox(height: AppSpacing.sm),
              Center(
                child: Text(
                  'Touchez la photo pour la changer',
                  style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: AppTextField(
                      controller: _firstNameController,
                      label: 'Prénom',
                      textCapitalization: TextCapitalization.words,
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
              Text(
                'Genre',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 6),
              Row(
                children: [
                  Expanded(
                    child: _GenderButton(
                      gender: Gender.homme,
                      selected: _gender,
                      onTap: (g) => setState(() => _gender = g),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: _GenderButton(
                      gender: Gender.femme,
                      selected: _gender,
                      onTap: (g) => setState(() => _gender = g),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              AppTextField(
                controller: _emailController,
                label: 'Email (facultatif)',
                keyboardType: TextInputType.emailAddress,
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
                      : const Text('Enregistrer'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GenderButton extends StatelessWidget {
  const _GenderButton({
    required this.gender,
    required this.selected,
    required this.onTap,
  });

  final Gender gender;
  final Gender? selected;
  final ValueChanged<Gender> onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final isSelected = selected == gender;
    return SizedBox(
      height: 44,
      child: OutlinedButton(
        onPressed: () => onTap(gender),
        style: OutlinedButton.styleFrom(
          backgroundColor: isSelected
              ? (isDark
                    ? AppColors.primary.withValues(alpha: 0.18)
                    : AppColors.primaryTint)
              : theme.cardTheme.color,
          side: BorderSide(
            color: isSelected ? AppColors.primary : theme.dividerTheme.color!,
          ),
          foregroundColor: isSelected
              ? (isDark ? const Color(0xFF93C5FD) : AppColors.primary)
              : (isDark
                    ? AppColors.textSecondaryDark
                    : AppColors.textSecondary),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(10),
          ),
        ),
        child: Text(
          gender.label,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14),
        ),
      ),
    );
  }
}
