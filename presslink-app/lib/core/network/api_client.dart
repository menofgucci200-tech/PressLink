import 'package:dio/dio.dart';

import '../storage/token_storage.dart';

/// Base URL de l'API PressLink.
/// Surchargée au lancement avec `--dart-define=API_HOST=ip` :
/// - Chrome/desktop (dev local) : 127.0.0.1 (par défaut)
/// - Émulateur Android : 10.0.2.2
/// - Appareil physique : IP LAN de la machine de dev
const String _apiHost = String.fromEnvironment('API_HOST', defaultValue: '127.0.0.1');
const String apiBaseUrl = 'http://$_apiHost:8000/api/v1';

class ApiClient {
  ApiClient(this._tokenStorage) : dio = Dio(BaseOptions(baseUrl: apiBaseUrl)) {
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _tokenStorage.readToken();
          if (token != null) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          options.headers['Accept'] = 'application/json';
          handler.next(options);
        },
      ),
    );
  }

  final Dio dio;
  final TokenStorage _tokenStorage;
}
