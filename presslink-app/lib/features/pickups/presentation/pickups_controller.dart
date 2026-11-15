import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../domain/pickup_repository.dart';

final pickupRepositoryProvider = Provider<PickupRepository>((ref) {
  return PickupRepository(ref.watch(apiClientProvider));
});

/// Demandes de ramassage du client, les plus récentes d'abord.
final pickupRequestsProvider = FutureProvider.autoDispose<List<PickupRequest>>(
  (ref) => ref.watch(pickupRepositoryProvider).list(),
);

final pickupRequestProvider = FutureProvider.autoDispose
    .family<PickupRequest, int>(
      (ref, id) => ref.watch(pickupRepositoryProvider).get(id),
    );

/// Pressings qui passent à une adresse (par id d'adresse).
final pickupOptionsProvider = FutureProvider.autoDispose
    .family<PickupOptions, int>(
      (ref, addressId) =>
          ref.watch(pickupRepositoryProvider).options(addressId),
    );
