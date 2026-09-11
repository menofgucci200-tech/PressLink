import '../../../core/network/api_client.dart';
import '../../../core/network/api_error.dart';

class ServiceVariantModel {
  const ServiceVariantModel({
    required this.id,
    required this.name,
    required this.priceFcfa,
  });

  final int id;
  final String name;
  final int priceFcfa;

  factory ServiceVariantModel.fromJson(Map<String, dynamic> json) =>
      ServiceVariantModel(
        id: json['id'] as int,
        name: json['name'] as String,
        priceFcfa: json['price_fcfa'] as int,
      );
}

class ServiceModel {
  const ServiceModel({
    required this.id,
    required this.name,
    required this.priceFcfa,
    this.variants = const [],
  });

  final int id;
  final String name;
  final int priceFcfa;
  final List<ServiceVariantModel> variants;

  factory ServiceModel.fromJson(Map<String, dynamic> json) => ServiceModel(
    id: json['id'] as int,
    name: json['name'] as String,
    priceFcfa: json['price_fcfa'] as int,
    variants: (json['variants'] as List<dynamic>? ?? [])
        .map((e) => ServiceVariantModel.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}

/// Horaires d'un jour de la semaine — clé du jour en français (ex. 'lundi').
class OpeningHoursDay {
  const OpeningHoursDay({
    required this.closed,
    required this.open,
    required this.close,
  });

  final bool closed;
  final String open;
  final String close;

  factory OpeningHoursDay.fromJson(Map<String, dynamic> json) =>
      OpeningHoursDay(
        closed: json['closed'] as bool? ?? false,
        open: json['open'] as String? ?? '',
        close: json['close'] as String? ?? '',
      );
}

class PressingModel {
  const PressingModel({
    required this.id,
    required this.name,
    required this.city,
    required this.code,
    required this.phone,
    required this.address,
    this.ordersCount = 0,
    this.email,
    this.logoUrl,
    this.description,
    this.openingHours,
    this.services,
  });

  final int id;
  final String name;
  final String? city;
  final String code;
  final String? phone;
  final String? address;
  final int ordersCount;
  final String? email;
  final String? logoUrl;
  final String? description;
  final Map<String, OpeningHoursDay>? openingHours;
  final List<ServiceModel>? services;

  String get initials {
    final words = name
        .replaceFirst(RegExp(r'^Pressing\s+', caseSensitive: false), '')
        .trim()
        .split(' ');
    if (words.isEmpty || words.first.isEmpty)
      return name.substring(0, 1).toUpperCase();
    return words.length > 1
        ? (words[0][0] + words[1][0]).toUpperCase()
        : words[0].substring(0, words[0].length >= 2 ? 2 : 1).toUpperCase();
  }

  factory PressingModel.fromJson(Map<String, dynamic> json) => PressingModel(
    id: json['id'] as int,
    name: json['name'] as String,
    city: json['city'] as String?,
    code: json['code'] as String? ?? '',
    phone: json['phone'] as String?,
    address: json['address'] as String?,
    ordersCount: json['orders_count'] as int? ?? 0,
    email: json['email'] as String?,
    logoUrl: json['logo_url'] as String?,
    description: json['description'] as String?,
    openingHours: (json['opening_hours'] as Map<String, dynamic>?)?.map(
      (day, hours) => MapEntry(
        day,
        OpeningHoursDay.fromJson(hours as Map<String, dynamic>),
      ),
    ),
    services: (json['services'] as List<dynamic>?)
        ?.map((e) => ServiceModel.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}

/// Pressings rejoints par le client — Cahier §11.
class PressingRepository {
  PressingRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<PressingModel>> mine() async {
    final response = await _apiClient.dio.get('/pressings/mine');
    return (response.data as List)
        .map((e) => PressingModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<PressingModel> join(String code) async {
    final response = await _apiClient.dio.post(
      '/pressings/join',
      data: {'code': code},
    );
    return PressingModel.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> leave(int pressingId) async {
    await _apiClient.dio.delete('/pressings/$pressingId/leave');
  }

  static String errorMessage(Object error) => apiErrorMessage(error);
}
