import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../core/network/providers.dart';
import '../domain/order_repository.dart';

final orderRepositoryProvider = Provider<OrderRepository>((ref) {
  return OrderRepository(ref.watch(apiClientProvider));
});

final ordersProvider = FutureProvider.autoDispose<List<OrderModel>>((ref) {
  return ref.watch(orderRepositoryProvider).list();
});

final orderDetailProvider = FutureProvider.autoDispose.family<OrderModel, int>((ref, id) {
  return ref.watch(orderRepositoryProvider).show(id);
});
