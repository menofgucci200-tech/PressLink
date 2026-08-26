import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../domain/pressing_repository.dart';

final pressingRepositoryProvider = Provider<PressingRepository>((ref) {
  return PressingRepository(ref.watch(apiClientProvider));
});

final myPressingsProvider = FutureProvider.autoDispose<List<PressingModel>>((ref) {
  return ref.watch(pressingRepositoryProvider).mine();
});
