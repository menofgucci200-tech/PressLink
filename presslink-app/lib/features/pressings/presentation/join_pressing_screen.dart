import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/presslink_top_bar.dart';
import '../domain/pressing_repository.dart';
import 'pressings_controller.dart';

/// Rejoindre un pressing par code — User Flows §3 / prototype "cIsJoin".
class JoinPressingScreen extends ConsumerStatefulWidget {
  const JoinPressingScreen({super.key});

  @override
  ConsumerState<JoinPressingScreen> createState() => _JoinPressingScreenState();
}

class _JoinPressingScreenState extends ConsumerState<JoinPressingScreen> {
  final _codeController = TextEditingController();
  bool _loading = false;
  String? _error;
  PressingModel? _found;

  Future<void> _submit() async {
    if (_found != null) {
      Navigator.of(context).pop(true);
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final pressing = await ref
          .read(pressingRepositoryProvider)
          .join(_codeController.text.trim());
      if (!mounted) return;
      setState(() {
        _found = pressing;
        _loading = false;
      });
      ref.invalidate(myPressingsProvider);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = PressingRepository.errorMessage(e);
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Scaffold(
      appBar: PressLinkTopBar(
        title: 'Ajouter un pressing',
        onBack: () => Navigator.of(context).pop(_found != null),
      ),
      body: SafeArea(
        // Défilement : avec le clavier ouvert sur un petit téléphone, le
        // bouton reste atteignable.
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.md),
          keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Rejoignez votre pressing',
                style: theme.textTheme.headlineSmall?.copyWith(fontSize: 20),
              ),
              const SizedBox(height: 8),
              Text(
                'Entrez le code que votre pressing vous a communiqué.',
                style: theme.textTheme.bodyMedium,
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Code du pressing',
                style: theme.textTheme.bodyMedium?.copyWith(
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 6),
              SizedBox(
                height: 52,
                child: TextField(
                  controller: _codeController,
                  textCapitalization: TextCapitalization.characters,
                  enabled: _found == null,
                  textInputAction: TextInputAction.go,
                  onSubmitted: (_) => _loading ? null : _submit(),
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 17,
                    letterSpacing: 1.2,
                  ),
                  // Exemple en gris clair et non gras, précédé de « Ex. » :
                  // on ne doit pas croire que le champ est déjà rempli.
                  decoration: InputDecoration(
                    hintText: 'Ex. PE-4821',
                    hintStyle: TextStyle(
                      fontWeight: FontWeight.w400,
                      letterSpacing: 0.4,
                      color:
                          (theme.brightness == Brightness.dark
                                  ? AppColors.textMutedDark
                                  : AppColors.textMuted)
                              .withValues(alpha: 0.75),
                    ),
                  ),
                ),
              ),
              if (_found != null) ...[
                const SizedBox(height: AppSpacing.md),
                Container(
                  padding: const EdgeInsets.all(AppSpacing.md),
                  decoration: BoxDecoration(
                    color: context.successTint,
                    border: Border.all(
                      color: AppColors.success.withValues(alpha: 0.4),
                    ),
                    borderRadius: BorderRadius.circular(AppRadius.lg),
                  ),
                  child: Column(
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.check_circle,
                            size: 15,
                            color: AppColors.successText,
                          ),
                          const SizedBox(width: 6),
                          Text(
                            'Pressing ajouté',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: AppColors.successText,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: AppSpacing.sm + 6),
                      Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(
                            color: AppColors.success.withValues(alpha: 0.4),
                          ),
                          borderRadius: BorderRadius.circular(AppRadius.lg),
                        ),
                        child: Center(
                          child: Text(
                            _found!.initials,
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: AppColors.successText,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm + 4),
                      Text(
                        _found!.name,
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (_found!.city != null) ...[
                        const SizedBox(height: 3),
                        Text(_found!.city!, style: theme.textTheme.bodyMedium),
                      ],
                    ],
                  ),
                ),
              ],
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
                      : Text(_found != null ? 'Terminer' : 'Rejoindre'),
                ),
              ),
              if (_found == null) ...[
                const SizedBox(height: AppSpacing.md),
                SizedBox(
                  width: double.infinity,
                  child: Text(
                    'Vous ne connaissez pas votre code ?\nDemandez-le à votre pressing.',
                    textAlign: TextAlign.center,
                    style: theme.textTheme.labelSmall,
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
