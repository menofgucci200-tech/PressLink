import 'package:dio/dio.dart';
import 'package:flutter/widgets.dart';
import 'package:image_picker/image_picker.dart';

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
    this.emailNotifications = true,
    this.createdAt,
  });

  final int id;
  final String firstName;
  final String lastName;
  final String phone;
  final String? email;
  final String? gender;
  final String? photoUrl;

  /// Doublon par email des étapes clés (créée, prête) si un email est renseigné.
  final bool emailNotifications;

  /// Date d'inscription (« Client depuis… » sur le profil).
  final DateTime? createdAt;

  String get fullName => '$firstName $lastName'.trim();

  /// Initiales affichées à la place de la photo (« Aïcha Koné » → « AK »).
  String get initials {
    final letters = fullName
        .split(' ')
        .where((word) => word.isNotEmpty)
        .map((word) => word.characters.first)
        .take(2)
        .join()
        .toUpperCase();
    return letters.isEmpty ? '?' : letters;
  }

  factory Customer.fromJson(Map<String, dynamic> json) => Customer(
    id: json['id'] as int,
    firstName: json['first_name'] as String? ?? '',
    lastName: json['last_name'] as String? ?? '',
    phone: json['phone'] as String,
    email: json['email'] as String?,
    gender: json['gender'] as String?,
    photoUrl: json['photo_url'] as String?,
    emailNotifications: json['email_notifications'] as bool? ?? true,
    createdAt: DateTime.tryParse(
      json['created_at'] as String? ?? '',
    )?.toLocal(),
  );
}

class SessionCheck {
  const SessionCheck({required this.valid, this.customer});

  final bool valid;
  final Customer? customer;
}

/// Authentification client — Cahier §3.1 (revu le 26/08) :
/// téléphone d'abord, puis mot de passe (compte existant) ou inscription.
class AuthRepository {
  AuthRepository(this._apiClient, this._tokenStorage);

  final ApiClient _apiClient;
  final TokenStorage _tokenStorage;

  Future<bool> checkPhoneExists(String phone) async {
    final response = await _apiClient.dio.post(
      '/auth/customer/check-phone',
      data: {'phone': phone},
    );
    return (response.data as Map<String, dynamic>)['exists'] as bool;
  }

  Future<Customer> login({
    required String phone,
    required String password,
  }) async {
    final response = await _apiClient.dio.post(
      '/auth/customer/login',
      data: {'phone': phone, 'password': password},
    );

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
    final response = await _apiClient.dio.post(
      '/auth/customer/register',
      data: {
        'phone': phone,
        'first_name': firstName,
        'last_name': lastName,
        'gender': gender.value,
        'email': email,
        'password': password,
        'password_confirmation': password,
      },
    );

    return _saveSessionAndReturnCustomer(response);
  }

  Future<Customer> _saveSessionAndReturnCustomer(Response response) async {
    final data = response.data as Map<String, dynamic>;
    await _tokenStorage.saveToken(data['token'] as String);
    return Customer.fromJson(data['customer'] as Map<String, dynamic>);
  }

  /// Un token n'est effacé que si le serveur l'a explicitement rejeté
  /// (401/403). Toute autre erreur (pas de réseau, backend injoignable,
  /// 5xx) laisse la session locale intacte : on ne veut jamais déconnecter
  /// un client simplement parce qu'il est hors-ligne au démarrage de l'app.
  ///
  /// Retourne le [Customer] déjà chargé plutôt qu'un simple booléen, pour
  /// que l'écran profil ait ses données dès le premier affichage au lieu
  /// d'attendre une action ultérieure (login, mise à jour…) qui les
  /// remplirait après coup.
  Future<SessionCheck> hasValidSession() async {
    final token = await _tokenStorage.readToken();
    if (token == null) return const SessionCheck(valid: false);

    try {
      final response = await _apiClient.dio.get('/auth/customer/me');
      return SessionCheck(
        valid: true,
        customer: Customer.fromJson(response.data as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      final status = e.response?.statusCode;
      if (status == 401 || status == 403) {
        await _tokenStorage.clearToken();
        return const SessionCheck(valid: false);
      }
      return const SessionCheck(valid: true);
    }
  }

  Future<Customer> updateProfile({
    required String firstName,
    required String lastName,
    required Gender gender,
    String? email,
    bool? emailNotifications,
  }) async {
    final response = await _apiClient.dio.put(
      '/customer/profile',
      data: {
        'first_name': firstName,
        'last_name': lastName,
        'gender': gender.value,
        'email': email,
        'email_notifications': ?emailNotifications,
      },
    );

    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  /// Envoie les octets plutôt qu'un chemin de fichier : `dart:io` n'existe
  /// pas dans un navigateur (PWA), alors que `XFile.readAsBytes()` marche
  /// partout.
  Future<Customer> uploadPhoto(XFile file) async {
    final form = FormData.fromMap({
      'photo': MultipartFile.fromBytes(
        await file.readAsBytes(),
        filename: file.name.isNotEmpty ? file.name : 'photo.jpg',
        contentType: DioMediaType.parse(file.mimeType ?? 'image/jpeg'),
      ),
    });
    final response = await _apiClient.dio.post('/customer/photo', data: form);
    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<Customer> deletePhoto() async {
    final response = await _apiClient.dio.delete('/customer/photo');
    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String password,
  }) async {
    await _apiClient.dio.put(
      '/customer/password',
      data: {
        'current_password': currentPassword,
        'password': password,
        'password_confirmation': password,
      },
    );
  }

  /// Nouveau numéro (identifiant de connexion), confirmé par le mot de passe.
  Future<Customer> updatePhone({
    required String phone,
    required String currentPassword,
  }) async {
    final response = await _apiClient.dio.put(
      '/customer/phone',
      data: {'phone': phone, 'current_password': currentPassword},
    );
    return Customer.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> updateOnesignalPlayerId(String playerId) async {
    await _apiClient.dio.put(
      '/customer/onesignal-player-id',
      data: {'player_id': playerId},
    );
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
