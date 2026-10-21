import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:intl/intl.dart';

import '../../features/notifications/presentation/notifications_controller.dart';
import '../../features/orders/presentation/orders_controller.dart';
import '../../features/pressings/presentation/pressings_controller.dart';
import '../network/providers.dart';
import '../theme/app_colors.dart';

/// Pastille « Hors connexion » posée en bas de l'écran tant que l'appli
/// affiche sa copie locale. Elle réessaie toute seule régulièrement et
/// disparaît dès que le serveur répond.
class OfflineBanner extends ConsumerStatefulWidget {
  const OfflineBanner({super.key});

  @override
  ConsumerState<OfflineBanner> createState() => _OfflineBannerState();
}

class _OfflineBannerState extends ConsumerState<OfflineBanner> {
  Timer? _retry;

  @override
  void dispose() {
    _retry?.cancel();
    super.dispose();
  }

  void _reload() {
    ref.invalidate(ordersProvider);
    ref.invalidate(orderDetailProvider);
    ref.invalidate(myPressingsProvider);
    ref.invalidate(notificationsProvider);
    ref.invalidate(unreadNotificationsCountProvider);
  }

  void _follow(DateTime? offlineFrom) {
    if (offlineFrom == null) {
      _retry?.cancel();
      _retry = null;
    } else {
      _retry ??= Timer.periodic(const Duration(seconds: 20), (_) => _reload());
    }
  }

  static String _since(DateTime at) {
    final now = DateTime.now();
    final sameDay =
        at.year == now.year && at.month == now.month && at.day == now.day;
    final time = DateFormat("H 'h' mm", 'fr_FR').format(at);
    return sameDay
        ? 'de $time'
        : 'du ${DateFormat('d/MM', 'fr_FR').format(at)}, $time';
  }

  @override
  Widget build(BuildContext context) {
    final offlineFrom = ref.watch(apiClientProvider).offlineDataFrom;

    return ValueListenableBuilder<DateTime?>(
      valueListenable: offlineFrom,
      builder: (context, at, _) {
        _follow(at);
        if (at == null) return const SizedBox.shrink();

        return Align(
          alignment: Alignment.bottomCenter,
          child: SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 100),
              child: Material(
                color: AppColors.textPrimary,
                elevation: 6,
                borderRadius: BorderRadius.circular(999),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 4, 4, 4),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.cloud_off_rounded,
                        size: 18,
                        color: Colors.white,
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Text(
                          'Hors connexion · données ${_since(at)}',
                          maxLines: 1,
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 13,
                            fontWeight: FontWeight.w500,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      IconButton(
                        onPressed: _reload,
                        tooltip: 'Réessayer',
                        icon: const Icon(Icons.refresh_rounded, size: 20),
                        color: const Color(0xFF93C5FD),
                        visualDensity: VisualDensity.compact,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
