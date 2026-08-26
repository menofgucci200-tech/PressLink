import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';

class PressingModel {
  const PressingModel({
    required this.id,
    required this.name,
    required this.city,
    required this.code,
    required this.phone,
    required this.address,
    this.ordersCount = 0,
  });

  final int id;
  final String name;
  final String? city;
  final String code;
  final String? phone;
  final String? address;
  final int ordersCount;

  String get initials {
    final words = name.replaceFirst(RegExp(r'^Pressing\s+', caseSensitive: false), '').trim().split(' ');
    if (words.isEmpty || words.first.isEmpty) return name.substring(0, 1).toUpperCase();
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
      );
}

/// Pressings rejoints par le client — Cahier §11.
class PressingRepository {
  PressingRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<PressingModel>> mine() async {
    final response = await _apiClient.dio.get('/pressings/mine');
    return (response.data as List).map((e) => PressingModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<PressingModel> join(String code) async {
    final response = await _apiClient.dio.post('/pressings/join', data: {'code': code});
    return PressingModel.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> leave(int pressingId) async {
    await _apiClient.dio.delete('/pressings/$pressingId/leave');
  }

  static String errorMessage(Object error) {
    if (error is DioException) {
      final data = error.response?.data;
      if (data is Map && data['message'] is String) {
        return data['message'] as String;
      }
    }
    return 'Une erreur est survenue. Réessayez.';
  }
}
