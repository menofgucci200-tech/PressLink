import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/widgets/app_back_button.dart';
import '../../../core/widgets/app_text_field.dart';
import '../domain/order_repository.dart';
import 'orders_controller.dart';

/// Signaler un problème sur une commande — ex. article manquant,
/// article qui n'appartient pas au client.
class ReportIssueScreen extends ConsumerStatefulWidget {
  const ReportIssueScreen({required this.orderId, required this.orderNumber, super.key});

  final int orderId;
  final String orderNumber;

  @override
  ConsumerState<ReportIssueScreen> createState() => _ReportIssueScreenState();
}

class _ReportIssueScreenState extends ConsumerState<ReportIssueScreen> {
  final _descriptionController = TextEditingController();
  IssueCategory? _category;
  bool _loading = false;
  String? _error;

  Future<void> _submit() async {
    if (_category == null) {
      setState(() => _error = 'Merci de sélectionner le type de problème.');
      return;
    }

    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      await ref.read(orderRepositoryProvider).reportIssue(
            orderId: widget.orderId,
            category: _category!,
            description: _descriptionController.text.trim().isEmpty ? null : _descriptionController.text.trim(),
          );

      if (!mounted) return;
      ref.invalidate(orderDetailProvider(widget.orderId));
      Navigator.of(context).pop(true);
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = OrderRepository.errorMessage(e);
      });
    }
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
              Row(
                children: [
                  AppBackButton(onPressed: () => Navigator.of(context).pop()),
                  const SizedBox(width: AppSpacing.sm + 2),
                  Text('Signaler un problème', style: theme.textTheme.bodyLarge?.copyWith(fontWeight: FontWeight.w600, fontSize: 16)),
                ],
              ),
              const SizedBox(height: AppSpacing.lg),
              Text('Commande ${widget.orderNumber}', style: theme.textTheme.headlineSmall?.copyWith(fontSize: 18)),
              const SizedBox(height: 6),
              Text('Quel est le problème avec cette commande ?', style: theme.textTheme.bodyMedium),
              const SizedBox(height: AppSpacing.lg),
              for (final category in IssueCategory.values) ...[
                _CategoryOption(
                  category: category,
                  selected: _category == category,
                  onTap: () => setState(() => _category = category),
                ),
                const SizedBox(height: AppSpacing.sm),
              ],
              const SizedBox(height: AppSpacing.sm),
              AppTextField(
                controller: _descriptionController,
                label: 'Description (facultatif)',
                hintText: 'Précisez ici les détails du problème…',
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
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white))
                      : const Text('Envoyer le signalement'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CategoryOption extends StatelessWidget {
  const _CategoryOption({required this.category, required this.selected, required this.onTap});

  final IssueCategory category;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadius.md),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.symmetric(horizontal: AppSpacing.md, vertical: AppSpacing.sm + 6),
        decoration: BoxDecoration(
          color: selected ? AppColors.primaryTint : Colors.white,
          border: Border.all(color: selected ? AppColors.primary : AppColors.border),
          borderRadius: BorderRadius.circular(AppRadius.md),
        ),
        child: Row(
          children: [
            Icon(
              selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
              size: 20,
              color: selected ? AppColors.primary : AppColors.textMuted,
            ),
            const SizedBox(width: AppSpacing.sm + 2),
            Expanded(
              child: Text(
                category.label,
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                  color: selected ? AppColors.primary : AppColors.textPrimary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
