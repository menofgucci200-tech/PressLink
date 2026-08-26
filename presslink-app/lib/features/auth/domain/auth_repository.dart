import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_error.dart';
import '../../../core/storage/token_storage.dart';

enum Gender { homme, femme }

extension GenderLabel on Gender {
  String get label => this == Gender.homme ? 'Homme' : 'Femme';

  String get value => this == Gender.homme ? 'homme' : 'femme';
}

class Customer {
  const Customer({
    required this.id,
    required this.firstName,
    required this.lastName,
    required this.phone,
    this.email,
    this.gender,
    this.photoUrl,
  });

  final int id;
  final String firstName;
  final String lastName;
  final String phone;
  final String? email;
  final String? gender;
  final String? photoUrl;

  String get fullName => '$firstName $lastName'.trim();

  factory Customer.fromJson(Map<String, dynamic> json) => Customer(
        id: json['id'] as int,
        firstName: json['first_name'] as String? ?? '',
        lastName: json['last_name'] as String? ?? '',
        phone: json['phone'] as String,
        email: json['email'] as String?,
        gender: json['gender'] as String?,
        photoUrl: json['photo_url'] as String?,
      );
}

/// Authentification client — Cahier §3.1 (revu le 26/08) :
/// téléphone d'abord, puis mot de passe (compte existant) ou inscription.
class AuthRepository {
  AuthRepository(this._apiClient, this._tokenStorage);

  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  Future<bool> checkPhoneExists(String phone) async {
    final response = await _apiClient.dio.post('/auth/customer/check-phone', data: {'phone': phone});
    return (response.data as Map<String, dynamic>)['exists'] as bool;
  }

  Future<Customer> login({required String phone, required String password}) async {
    final response = await _apiClient.dio.post('/auth/customer/login', data: {
      'phone': phone,
      'password': password,
    });

    return _saveSessionAndReturnCustomer(response);
  }

  Future<Customer> register({
    required String phone,
    required String firstName,
    required String lastName,
    required Gender gender,
    required String password,
    String? email,
  }) async {
    final response = await _apiClient.dio.post('/auth/customer/register', data: {
      'phone': phone,
      'first_name': firstName,
      'last_name': lastName,
      'gender': gender.value,
      'email': email,
      'password': password,
      'password_confirmation': password,
    });

    return _saveSessionAndReturnCustomer(response);
  }

  Future<Customer> _saveSessionAndReturnCustomer(Response response) async {
    final data = response.data as Map<String, dynamic>;
    await _tokenStorage.saveToken(data['token'] as String);
    return Customer.fromJson(data['customer'] as Map<String, dynamic>);
  }

  Future<bool> hasValidSession() async {
    final token = await _tokenStorage.readToken();
    if (token == null) return false;

    try {
      await _apiClient.dio.get('/auth/customer/me');
      return true;
    } on DioException {
      await _tokenStorage.clearToken();
      return false;
    }
  }

  Future<Customer> updateProfile({
    required String firstName,
    required String lastName,
    required Gender gender,
    String? email,
  }) async {
    final response = await _apiClient.dio.put('/customer/profile', data: {
      'first_name': firstName,
      'last_name': lastName,
      'gender': gender.value,
      'email': email,
    });

    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<Customer> uploadPhoto(String filePath) async {
    final form = FormData.fromMap({
      'photo': await MultipartFile.fromFile(filePath),
    });
    final response = await _apiClient.dio.post('/customer/photo', data: form);
    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<Customer> deletePhoto() async {
    final response = await _apiClient.dio.delete('/customer/photo');
    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> updatePassword({required String currentPassword, required String password}) async {
    await _apiClient.dio.put('/customer/password', data: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': password,
    });
  }

  Future<void> updateFcmToken(String fcmToken) async {
    await _apiClient.dio.put('/customer/fcm-token', data: {'fcm_token': fcmToken});
  }

  Future<void> logout() async {
    try {
      await _apiClient.dio.post('/auth/customer/logout');
    } on DioException {
      // Le token est probablement déjà invalide côté serveur — on nettoie localement.
    }
    await _tokenStorage.clearToken();
  }

  /// Extrait un message d'erreur lisible depuis une réponse API PressLink.
  static String errorMessage(Object error) => apiErrorMessage(error);
}
