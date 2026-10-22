import 'dart:async';

import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../../../core/theme/app_colors.dart';
import '../domain/order_repository.dart';

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(ref.watch(apiClientProvider));
});

/// Recharge discrètement les données tant qu'elles sont affichées : un
/// changement de statut fait au comptoir apparaît sans que le client ait à
/// recharger l'application. Rien n'est demandé quand l'appli est en
/// arrière-plan ; le retour au premier plan recharge déjà tout (MainShell).
void _rafraichirToutesLes(Ref ref, Duration delai, {bool Function()? tantQue}) {
  late Timer timer;
  void planifier() {
    timer = Timer(delai, () {
      if (tantQue != null && !tantQue()) return;
      final etat = WidgetsBinding.instance.lifecycleState;
      if (etat == null || etat == AppLifecycleState.resumed) {
        ref.invalidateSelf();
      } else {
        planifier();
      }
    });
  }

  planifier();
  ref.onDispose(() => timer.cancel());
}

final ordersProvider = FutureProvider.autoDispose<List<OrderModel>>((ref) {
  _rafraichirToutesLes(ref, const Duration(seconds: 30));
  return ref.watch(orderRepositoryProvider).list();
});

final orderDetailProvider = FutureProvider.autoDispose.family<OrderModel, int>((
  ref,
  id,
) {
  // Une commande récupérée ou annulée ne bouge plus : inutile d'y revenir.
  _rafraichirToutesLes(
    ref,
    const Duration(seconds: 20),
    tantQue: () {
      final statut = ref.state.valueOrNull?.status;
      return statut != OrderStatus.recuperee && statut != OrderStatus.annulee;
    },
  );
  return ref.watch(orderRepositoryProvider).show(id);
});

/// Signalements de la commande pas encore envoyés (faits hors connexion).
/// Relu à chaque rechargement du détail : ils disparaissent une fois partis.
final pendingIssuesProvider = FutureProvider.autoDispose
    .family<List<PendingIssue>, int>((ref, id) {
      ref.watch(orderDetailProvider(id));
      return ref.watch(orderRepositoryProvider).pendingIssues(id);
    });
