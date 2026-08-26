import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../../auth/domain/auth_repository.dart';
import '../../auth/presentation/auth_controller.dart';

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
  bool _photoLoading = false;
  String? _error;
  String? _success;

  @override
  void initState() {
    super.initState();
    final customer = ref.read(authControllerProvider).customer;
    _firstNameController = TextEditingController(text: customer?.firstName ?? '');
    _lastNameController = TextEditingController(text: customer?.lastName ?? '');
    _emailController = TextEditingController(text: customer?.email ?? '');
    _gender = customer?.gender == 'femme'
        ? Gender.femme
        : customer?.gender == 'homme'
            ? Gender.homme
            : null;
  }

  Future<void> _pickPhoto(ImageSource source) async {
    final picked = await ImagePicker().pickImage(source: source, maxWidth: 1024, imageQuality: 85);
    if (picked == null) return;

    setState(() {
      _photoLoading = true;
      _error = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).uploadPhoto(picked.path);

    if (!mounted) return;
    setState(() {
      _photoLoading = false;
      if (!ok) _error = ref.read(authControllerProvider).error;
    });
  }

  Future<void> _removePhoto() async {
    setState(() {
      _photoLoading = true;
      _error = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).deletePhoto();

    if (!mounted) return;
    setState(() {
      _photoLoading = false;
      if (!ok) _error = ref.read(authControllerProvider).error;
    });
  }

  void _showPhotoOptions() {
    final hasPhoto = ref.read(authControllerProvider).customer?.photoUrl != null;
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Prendre une photo'),
              onTap: () {
                Navigator.of(context).pop();
                _pickPhoto(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choisir dans la galerie'),
              onTap: () {
                Navigator.of(context).pop();
                _pickPhoto(ImageSource.gallery);
              },
            ),
            if (hasPhoto)
              ListTile(
                leading: const Icon(Icons.delete_outline, color: AppColors.error),
                title: const Text('Supprimer la photo', style: TextStyle(color: AppColors.error)),
                onTap: () {
                  Navigator.of(context).pop();
                  _removePhoto();
                },
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (_gender == null) {
      setState(() => _error = 'Merci de sélectionner votre genre.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
      _success = null;
    });

    final ok = await ref.read(authControllerProvider.notifier).updateProfile(
          firstName: _firstNameController.text.trim(),
          lastName: _lastNameController.text.trim(),
          gender: _gender!,
          email: _emailController.text.trim().isEmpty ? null : _emailController.text.trim(),
        );

    if (!mounted) return;
    setState(() {
      _loading = false;
      if (ok) {
        _success = 'Profil mis à jour.';
      } else {
        _error = ref.read(authControllerProvider).error;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final customer = ref.watch(authControllerProvider).customer;

    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  AppBackButton(onPressed: () => Navigator.of(context).pop()),
                  const SizedBox(width: AppSpacing.sm + 2),
                  Text('Modifier mon profil', style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600, fontSize: 16)),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Center(
                child: GestureDetector(
                  onTap: _photoLoading ? null : _showPhotoOptions,
                  child: Stack(
                    children: [
                      CircleAvatar(
                        radius: 44,
                        backgroundColor: AppColors.primaryTint,
                        backgroundImage: customer?.photoUrl != null ? NetworkImage(customer!.photoUrl!) : null,
                        child: customer?.photoUrl == null
                            ? Text(
                                customer != null && customer.fullName.isNotEmpty
                                    ? customer.fullName.trim().split(' ').map((w) => w.isNotEmpty ? w[0] : '').take(2).join().toUpperCase()
                                    : '?',
                                style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 26),
                              )
                            : null,
                      ),
                      Positioned(
                        bottom: 0,
                        right: 0,
                        child: Container(
                          width: 30,
                          height: 30,
                          decoration: BoxDecoration(
                            color: AppColors.primary,
                            shape: BoxShape.circle,
                            border: Border.all(color: Colors.white, width: 2),
                          ),
                          child: _photoLoading
                              ? const Padding(
                                  padding: EdgeInsets.all(6),
                                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                                )
                              : const Icon(Icons.camera_alt, size: 14, color: Colors.white),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(child: AppTextField(controller: _firstNameController, label: 'Prénom', textCapitalization: TextCapitalization.words)),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(child: AppTextField(controller: _lastNameController, label: 'Nom', textCapitalization: TextCapitalization.words)),
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
              AppTextField(controller: _emailController, label: 'Email (facultatif)', keyboardType: TextInputType.emailAddress),
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
