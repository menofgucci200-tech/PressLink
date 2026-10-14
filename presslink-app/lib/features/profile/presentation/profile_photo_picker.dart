import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/widgets/app_feedback.dart';
import '../../auth/presentation/auth_controller.dart';
import 'customer_avatar.dart';

/// Envoi ou suppression de photo en cours — affiché sur l'avatar, que la
/// photo ait été changée depuis le profil ou depuis « Modifier mon profil ».
final profilePhotoBusyProvider = StateProvider<bool>((ref) => false);

/// Choix de la photo de profil : appareil photo, galerie ou suppression.
Future<void> showProfilePhotoOptions(BuildContext context, WidgetRef ref) {
  final hasPhoto = ref.read(authControllerProvider).customer?.photoUrl != null;

  return showModalBottomSheet(
    context: context,
    backgroundColor: Theme.of(context).cardTheme.color,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
    ),
    builder: (sheetContext) => SafeArea(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          ListTile(
            leading: const Icon(Icons.photo_camera_outlined),
            title: const Text('Prendre une photo'),
            onTap: () {
              Navigator.of(sheetContext).pop();
              _pickAndUpload(context, ref, ImageSource.camera);
            },
          ),
          ListTile(
            leading: const Icon(Icons.photo_library_outlined),
            title: const Text('Choisir dans la galerie'),
            onTap: () {
              Navigator.of(sheetContext).pop();
              _pickAndUpload(context, ref, ImageSource.gallery);
            },
          ),
          if (hasPhoto)
            ListTile(
              leading: const Icon(Icons.delete_outline, color: AppColors.error),
              title: const Text(
                'Supprimer la photo',
                style: TextStyle(color: AppColors.error),
              ),
              onTap: () {
                Navigator.of(sheetContext).pop();
                _remove(context, ref);
              },
            ),
        ],
      ),
    ),
  );
}

Future<void> _pickAndUpload(
  BuildContext context,
  WidgetRef ref,
  ImageSource source,
) async {
  final XFile? picked;
  try {
    picked = await ImagePicker().pickImage(
      source: source,
      maxWidth: 1024,
      imageQuality: 85,
    );
  } catch (e) {
    if (!context.mounted) return;
    showAppToast(
      context,
      source == ImageSource.camera
          ? 'Impossible d\'accéder à l\'appareil photo. Vérifiez les autorisations.'
          : 'Impossible d\'accéder à la galerie. Vérifiez les autorisations.',
      type: AppToastType.error,
    );
    return;
  }
  if (picked == null) return;

  ref.read(profilePhotoBusyProvider.notifier).state = true;
  final ok = await ref
      .read(authControllerProvider.notifier)
      .uploadPhoto(picked);
  ref.read(profilePhotoBusyProvider.notifier).state = false;

  if (!context.mounted) return;
  showAppToast(
    context,
    ok
        ? 'Photo de profil mise à jour.'
        : (ref.read(authControllerProvider).error ??
              'La photo n\'a pas pu être envoyée.'),
    type: ok ? AppToastType.success : AppToastType.error,
  );
}

Future<void> _remove(BuildContext context, WidgetRef ref) async {
  final confirmed = await showAppConfirm(
    context,
    title: 'Supprimer la photo',
    message: 'Vos initiales s\'afficheront à la place de votre photo.',
    confirmLabel: 'Supprimer',
    tone: AppConfirmTone.danger,
    icon: Icons.delete_outline,
  );
  if (!confirmed) return;

  ref.read(profilePhotoBusyProvider.notifier).state = true;
  final ok = await ref.read(authControllerProvider.notifier).deletePhoto();
  ref.read(profilePhotoBusyProvider.notifier).state = false;

  if (!context.mounted) return;
  showAppToast(
    context,
    ok
        ? 'Photo de profil supprimée.'
        : (ref.read(authControllerProvider).error ??
              'La photo n\'a pas pu être supprimée.'),
    type: ok ? AppToastType.success : AppToastType.error,
  );
}

/// Grand avatar touchable avec pastille appareil photo (et indicateur
/// d'envoi) qui ouvre le choix de photo.
class EditableCustomerAvatar extends ConsumerWidget {
  const EditableCustomerAvatar({super.key, this.radius = 44});

  final double radius;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final customer = ref.watch(authControllerProvider).customer;
    final busy = ref.watch(profilePhotoBusyProvider);
    final theme = Theme.of(context);
    final badge = radius * 0.68;

    return Semantics(
      button: true,
      label: customer?.photoUrl != null
          ? 'Changer la photo de profil'
          : 'Ajouter une photo de profil',
      child: GestureDetector(
        onTap: busy ? null : () => showProfilePhotoOptions(context, ref),
        child: Stack(
          children: [
            CustomerAvatar(customer: customer, radius: radius),
            Positioned(
              bottom: 0,
              right: 0,
              child: Container(
                width: badge,
                height: badge,
                decoration: BoxDecoration(
                  color: AppColors.primary,
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: theme.cardTheme.color ?? Colors.white,
                    width: 2,
                  ),
                ),
                child: busy
                    ? Padding(
                        padding: EdgeInsets.all(badge * 0.22),
                        child: const CircularProgressIndicator(
                          strokeWidth: 2,
                          color: Colors.white,
                        ),
                      )
                    : Icon(
                        Icons.camera_alt,
                        size: badge * 0.48,
                        color: Colors.white,
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
