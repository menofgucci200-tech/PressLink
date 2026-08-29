import 'package:dio/dio.dart';

import '../config/app_config.dart';
import '../storage/token_storage.dart';

class ApiClient {
  ApiClient(this._tokenStorage) : dio = Dio(BaseOptions(baseUrl: AppConfig.apiBaseUrl)) {
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
