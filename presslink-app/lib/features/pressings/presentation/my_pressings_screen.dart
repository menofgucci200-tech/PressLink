import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/network/api_error.dart';
import '../../../core/widgets/error_state_view.dart';
import '../domain/pressing_repository.dart';
import 'join_pressing_screen.dart';
import 'pressings_controller.dart';

/// Mes pressings — Cahier §11 (rejoindre par code, consulter, quitter).
class MyPressingsScreen extends ConsumerWidget {
  const MyPressingsScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final theme = Theme.of(context);
    final pressingsAsync = ref.watch(myPressingsProvider);

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
                  Text('Mes pressings', style: theme.textTheme.headlineSmall?.copyWith(fontSize: 18)),
                ],
              ),
              const SizedBox(height: AppSpacing.md),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: () async {
                    final joined = await Navigator.of(context).push<bool>(
                      MaterialPageRoute(builder: (_) => const JoinPressingScreen()),
                    );
                    if (joined == true) ref.invalidate(myPressingsProvider);
                  },
                  icon: const Icon(Icons.add, size: 20, color: Colors.white),
                  label: const Text('Ajouter un pressing'),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Expanded(
                child: pressingsAsync.when(
                  loading: () => const Center(child: CircularProgressIndicator()),
                  error: (e, _) => ErrorStateView(
                    message: apiErrorMessage(e),
                    onRetry: () => ref.invalidate(myPressingsProvider),
                  ),
                  data: (pressings) {
                    if (pressings.isEmpty) {
                      return Center(
                        child: Padding(
                          padding: const EdgeInsets.all(AppSpacing.md),
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(Icons.store_outlined, size: 40, color: theme.textTheme.bodyMedium?.color),
                              const SizedBox(height: AppSpacing.sm),
                              const Text('Vous n\'avez rejoint aucun pressing.'),
                              const SizedBox(height: AppSpacing.sm),
                              TextButton(
                                onPressed: () async {
                                  final joined = await Navigator.of(context).push<bool>(
                                    MaterialPageRoute(builder: (_) => const JoinPressingScreen()),
                                  );
                                  if (joined == true) ref.invalidate(myPressingsProvider);
                                },
                                child: const Text('Ajouter un pressing'),
                              ),
                            ],
                          ),
                        ),
                      );
                    }

                    return RefreshIndicator(
                      onRefresh: () async {
                        ref.invalidate(myPressingsProvider);
                        await ref.read(myPressingsProvider.future);
                      },
                      child: ListView.separated(
                        itemCount: pressings.length,
                        separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.sm),
                        itemBuilder: (context, index) {
                          final pressing = pressings[index];
                          return _PressingTile(
                            pressing: pressing,
                            onLeave: () async {
                              final confirmed = await showDialog<bool>(
                                context: context,
                                builder: (context) => AlertDialog(
                                  title: const Text('Quitter ce pressing ?'),
                                  content: Text('Vous ne verrez plus les commandes de ${pressing.name} associées à ce pressing.'),
                                  actions: [
                                    TextButton(onPressed: () => Navigator.of(context).pop(false), child: const Text('Annuler')),
                                    TextButton(
                                      onPressed: () => Navigator.of(context).pop(true),
                                      child: const Text('Quitter', style: TextStyle(color: AppColors.error)),
                                    ),
                                  ],
                                ),
                              );
                              if (confirmed != true) return;

                              try {
                                await ref.read(pressingRepositoryProvider).leave(pressing.id);
                                ref.invalidate(myPressingsProvider);
                              } catch (e) {
                                if (!context.mounted) return;
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(PressingRepository.errorMessage(e))),
                                );
                              }
                            },
                          );
                        },
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PressingTile extends StatelessWidget {
  const _PressingTile({required this.pressing, required this.onLeave});

  final PressingModel pressing;
  final VoidCallback onLeave;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.sm + 6),
      decoration: BoxDecoration(
        color: theme.cardTheme.color,
        border: Border.all(color: theme.dividerTheme.color!),
        borderRadius: BorderRadius.circular(AppRadius.lg),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: AppColors.primaryTint, borderRadius: BorderRadius.circular(11)),
            child: Center(
              child: Text(pressing.initials, style: const TextStyle(color: AppColors.primary, fontWeight: FontWeight.w700, fontSize: 14)),
            ),
          ),
          const SizedBox(width: AppSpacing.sm + 5),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(pressing.name, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 15)),
                const SizedBox(height: 2),
                Text(
                  [if (pressing.city != null) pressing.city!, '${pressing.ordersCount} commande(s)'].join(' · '),
                  style: theme.textTheme.labelSmall,
                ),
                if (pressing.phone != null) ...[
                  const SizedBox(height: 4),
                  Text(pressing.phone!, style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12.5)),
                ],
                const SizedBox(height: 6),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(6)),
                  child: Text('Code ${pressing.code}', style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: 'Quitter ce pressing',
            onPressed: onLeave,
            icon: const Icon(Icons.logout, size: 18, color: AppColors.textMuted),
          ),
        ],
      ),
    );
  }
}
