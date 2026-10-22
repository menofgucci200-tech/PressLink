import 'dart:async';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/providers.dart';
import '../domain/auth_repository.dart';

final authRepositoryProvider = Provider<AuthRepository>((ref) {
  return AuthRepository(
    ref.watch(apiClientProvider),
    ref.watch(tokenStorageProvider),
  );
});

enum AuthStatus { checking, loggedOut, loggedIn }

class AuthState {
  const AuthState({
    required this.status,
    this.customer,
    this.error,
    this.notice,
  });

  final AuthStatus status;
  final Customer? customer;
  final String? error;

  /// Pourquoi l'appli est revenue à l'écran de connexion (session expirée,
  /// compte désactivé) ; null après une déconnexion volontaire.
  final String? notice;

  AuthState copyWith({AuthStatus? status, Customer? customer, String? error}) =>
      AuthState(
        status: status ?? this.status,
        customer: customer ?? this.customer,
        error: error,
      );
}

class AuthController extends StateNotifier<AuthState> {
  AuthController(this._repository)
    : super(const AuthState(status: AuthStatus.checking)) {
    _listenToSessionEnd();
    _restoreSession();
  }

  /// Constructeur de test : évite l'appel réseau/stockage sécurisé de
  /// [_restoreSession] et démarre directement dans l'état fourni.
  AuthController.withState(this._repository, AuthState initialState)
    : super(initialState) {
    _listenToSessionEnd();
  }

  final AuthRepository _repository;
  StreamSubscription<String>? _sessionEnd;

  /// Jeton refusé par le serveur sur n'importe quel écran : retour à
  /// l'écran de connexion, avec la raison (jeton et copie locale déjà
  /// effacés par ApiClient).
  void _listenToSessionEnd() {
    _sessionEnd = _repository.sessionEnded.listen((message) {
      if (!mounted || state.status == AuthStatus.loggedOut) return;
      state = AuthState(status: AuthStatus.loggedOut, notice: message);
    });
  }

  @override
  void dispose() {
    _sessionEnd?.cancel();
    super.dispose();
  }

  Future<void> _restoreSession() async {
    final check = await _repository.hasValidSession();
    if (!mounted || state.status != AuthStatus.checking) return;
    state = AuthState(
      status: check.valid ? AuthStatus.loggedIn : AuthStatus.loggedOut,
      customer: check.customer,
    );
  }

  /// Retourne true si un compte existe déjà pour ce numéro, null en cas d'erreur réseau.
  Future<PhoneCheck?> checkPhone(String phone) async {
    try {
      return await _repository.checkPhone(phone);
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return null;
    }
  }

  Future<bool> login({required String phone, required String password}) async {
    try {
      final customer = await _repository.login(
        phone: phone,
        password: password,
      );
      state = AuthState(status: AuthStatus.loggedIn, customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> register({
    required String phone,
    required String firstName,
    required String lastName,
    required Gender gender,
    required String password,
    String? email,
  }) async {
    try {
      final customer = await _repository.register(
        phone: phone,
        firstName: firstName,
        lastName: lastName,
        gender: gender,
        password: password,
        email: email,
      );
      state = AuthState(status: AuthStatus.loggedIn, customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> updateProfile({
    required String firstName,
    required String lastName,
    required Gender gender,
    String? email,
    bool? emailNotifications,
  }) async {
    try {
      final customer = await _repository.updateProfile(
        firstName: firstName,
        lastName: lastName,
        gender: gender,
        email: email,
        emailNotifications: emailNotifications,
      );
      state = state.copyWith(customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> updateEmailNotifications(bool enabled) async {
    try {
      final customer = await _repository.updateEmailNotifications(enabled);
      state = state.copyWith(customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> uploadPhoto(XFile file) async {
    try {
      final customer = await _repository.uploadPhoto(file);
      state = state.copyWith(customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> deletePhoto() async {
    try {
      final customer = await _repository.deletePhoto();
      state = state.copyWith(customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> updatePassword({
    required String currentPassword,
    required String password,
  }) async {
    try {
      await _repository.updatePassword(
        currentPassword: currentPassword,
        password: password,
      );
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<bool> updatePhone({
    required String phone,
    required String currentPassword,
  }) async {
    try {
      final customer = await _repository.updatePhone(
        phone: phone,
        currentPassword: currentPassword,
      );
      state = state.copyWith(customer: customer);
      return true;
    } catch (e) {
      state = state.copyWith(error: AuthRepository.errorMessage(e));
      return false;
    }
  }

  Future<void> logout({String? playerId}) async {
    await _repository.logout(playerId: playerId);
    state = const AuthState(status: AuthStatus.loggedOut);
  }
}

final authControllerProvider = StateNotifierProvider<AuthController, AuthState>(
  (ref) {
    return AuthController(ref.watch(authRepositoryProvider));
  },
);
