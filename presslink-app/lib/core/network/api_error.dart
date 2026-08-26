import 'package:dio/dio.dart';

/// Message d'erreur unique pour toute réponse API PressLink — utilisé par
/// tous les repositories pour rester cohérent à l'écran.
///
/// Distingue les problèmes de connectivité (pas de réseau, backend
/// injoignable) des erreurs métier renvoyées par l'API, pour éviter
/// d'afficher un message trompeur comme si l'utilisateur s'était trompé.
String apiErrorMessage(Object error) {
  if (error is DioException) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.connectionError:
        return 'Vérifiez votre connexion internet et réessayez.';
      default:
        break;
    }

    final data = error.response?.data;
    if (data is Map) {
      if (data['errors'] is Map && (data['errors'] as Map).isNotEmpty) {
        final firstError = (data['errors'] as Map).values.first;
        if (firstError is List && firstError.isNotEmpty) {
          return firstError.first as String;
        }
      }
      if (data['message'] is String) {
        return data['message'] as String;
      }
    }

    if (error.response?.statusCode == null) {
      return 'Vérifiez votre connexion internet et réessayez.';
    }
  }

  return 'Une erreur est survenue. Réessayez.';
}
