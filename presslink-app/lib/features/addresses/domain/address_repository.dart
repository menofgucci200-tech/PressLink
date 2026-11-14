import '../../../core/network/api_client.dart';
import '../../../core/network/api_error.dart';

/// Adresse de ramassage et de livraison : la commune décide des pressings
/// disponibles ; le quartier et un repère guident le livreur.
class CustomerAddress {
  const CustomerAddress({
    required this.id,
    required this.label,
    required this.commune,
    required this.landmark,
    this.neighborhood,
    this.isDefault = false,
  });

  final int id;
  final String label;
  final String commune;
  final String? neighborhood;
  final String landmark;
  final bool isDefault;

  /// « Cocody, Riviera 3 »
  String get place =>
      [commune, if ((neighborhood ?? '').isNotEmpty) neighborhood!].join(', ');

  factory CustomerAddress.fromJson(Map<String, dynamic> json) =>
      CustomerAddress(
        id: json['id'] as int,
        label: json['label'] as String,
        commune: json['commune'] as String,
        neighborhood: json['neighborhood'] as String?,
        landmark: json['landmark'] as String? ?? '',
        isDefault: json['is_default'] as bool? ?? false,
      );
}

/// Communes proposées, groupées (« Abidjan », « Autres villes »).
class CommuneGroup {
  const CommuneGroup({required this.name, required this.communes});

  final String name;
  final List<String> communes;

  factory CommuneGroup.fromJson(Map<String, dynamic> json) => CommuneGroup(
    name: json['group'] as String,
    communes: (json['communes'] as List<dynamic>).cast<String>(),
  );
}

/// Profil > Mes adresses. Chaque écriture renvoie la liste à jour.
class AddressRepository {
  AddressRepository(this._apiClient);

  /// Même limite que l'API.
  static const int maxAddresses = 5;

  final ApiClient _apiClient;

  Future<List<CustomerAddress>> list() async {
    final response = await _apiClient.dio.get('/customer/addresses');
    return _parse(response.data);
  }

  Future<List<CommuneGroup>> communes() async {
    final response = await _apiClient.dio.get('/communes');
    return (response.data as List)
        .map((e) => CommuneGroup.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<CustomerAddress>> save({
    int? id,
    required String label,
    required String commune,
    required String neighborhood,
    required String landmark,
    required bool isDefault,
  }) async {
    final data = {
      'label': label,
      'commune': commune,
      'neighborhood': neighborhood.isEmpty ? null : neighborhood,
      'landmark': landmark,
      'is_default': isDefault,
    };
    final response = id == null
        ? await _apiClient.dio.post('/customer/addresses', data: data)
        : await _apiClient.dio.put('/customer/addresses/$id', data: data);
    return _parse(response.data);
  }

  Future<List<CustomerAddress>> delete(int id) async {
    final response = await _apiClient.dio.delete('/customer/addresses/$id');
    return _parse(response.data);
  }

  static List<CustomerAddress> _parse(Object? body) => (body as List)
      .map((e) => CustomerAddress.fromJson(e as Map<String, dynamic>))
      .toList();

  static String errorMessage(Object error) => apiErrorMessage(error);
}
