import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../domain/address_repository.dart';

final addressRepositoryProvider = Provider<AddressRepository>((ref) {
  return AddressRepository(ref.watch(apiClientProvider));
});

/// Adresses du client ; les écritures remplacent la liste par celle
/// renvoyée par l'API (adresse par défaut déjà recalculée).
class AddressesController
    extends AutoDisposeAsyncNotifier<List<CustomerAddress>> {
  @override
  Future<List<CustomerAddress>> build() =>
      ref.watch(addressRepositoryProvider).list();

  /// Renvoie un message d'erreur, ou null si tout s'est bien passé.
  Future<String?> save({
    int? id,
    required String label,
    required String commune,
    required String neighborhood,
    required String landmark,
    required bool isDefault,
  }) => _write(
    () => ref
        .read(addressRepositoryProvider)
        .save(
          id: id,
          label: label,
          commune: commune,
          neighborhood: neighborhood,
          landmark: landmark,
          isDefault: isDefault,
        ),
  );

  Future<String?> delete(int id) =>
      _write(() => ref.read(addressRepositoryProvider).delete(id));

  Future<String?> _write(Future<List<CustomerAddress>> Function() call) async {
    try {
      state = AsyncData(await call());
      return null;
    } catch (error) {
      return AddressRepository.errorMessage(error);
    }
  }
}

final addressesControllerProvider =
    AsyncNotifierProvider.autoDispose<
      AddressesController,
      List<CustomerAddress>
    >(AddressesController.new);

final communeGroupsProvider = FutureProvider.autoDispose<List<CommuneGroup>>(
  (ref) => ref.watch(addressRepositoryProvider).communes(),
);
