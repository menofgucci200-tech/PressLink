import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/theme/app_colors.dart';

class OrderItemModel {
  const OrderItemModel({required this.name, required this.quantity, required this.subtotalFcfa});

  final String name;
  final int quantity;
  final int subtotalFcfa;

  factory OrderItemModel.fromJson(Map<String, dynamic> json) => OrderItemModel(
        name: json['name'] as String,
        quantity: json['quantity'] as int,
        subtotalFcfa: json['subtotal_fcfa'] as int,
      );
}

class OrderStatusEvent {
  const OrderStatusEvent({required this.status, required this.at});

  final OrderStatus status;
  final DateTime at;

  factory OrderStatusEvent.fromJson(Map<String, dynamic> json) => OrderStatusEvent(
        status: _statusFromApi(json['status'] as String),
        at: DateTime.parse(json['created_at'] as String),
      );
}

/// Type de problème signalé — Profil §Signalement (26/08).
enum IssueCategory { missingItem, wrongItem, damagedItem, other }

extension IssueCategoryLabel on IssueCategory {
  String get value => switch (this) {
        IssueCategory.missingItem => 'missing_item',
        IssueCategory.wrongItem => 'wrong_item',
        IssueCategory.damagedItem => 'damaged_item',
        IssueCategory.other => 'other',
      };

  String get label => switch (this) {
        IssueCategory.missingItem => 'Il manque un article',
        IssueCategory.wrongItem => 'Un article ne m\'appartient pas',
        IssueCategory.damagedItem => 'Un article est abîmé',
        IssueCategory.other => 'Autre problème',
      };
}

IssueCategory _categoryFromApi(String value) => switch (value) {
      'missing_item' => IssueCategory.missingItem,
      'wrong_item' => IssueCategory.wrongItem,
      'damaged_item' => IssueCategory.damagedItem,
      _ => IssueCategory.other,
    };

class OrderIssueModel {
  const OrderIssueModel({required this.id, required this.category, required this.description, required this.isResolved, required this.createdAt});

  final int id;
  final IssueCategory category;
  final String? description;
  final bool isResolved;
  final DateTime createdAt;

  factory OrderIssueModel.fromJson(Map<String, dynamic> json) => OrderIssueModel(
        id: json['id'] as int,
        category: _categoryFromApi(json['category'] as String),
        description: json['description'] as String?,
        isResolved: json['status'] == 'resolved',
        createdAt: DateTime.parse(json['created_at'] as String),
      );
}

OrderStatus _statusFromApi(String value) => OrderStatus.values.firstWhere(
      (s) => s.name == value,
      orElse: () => OrderStatus.recue,
    );

class OrderModel {
  const OrderModel({
    required this.id,
    required this.orderNumber,
    required this.pressingName,
    required this.status,
    required this.totalFcfa,
    required this.droppedOffAt,
    required this.items,
    this.expectedAt,
    this.history = const [],
    this.issues = const [],
  });

  final int id;
  final String orderNumber;
  final String pressingName;
  final OrderStatus status;
  final int totalFcfa;
  final DateTime droppedOffAt;
  final DateTime? expectedAt;
  final List<OrderItemModel> items;
  final List<OrderStatusEvent> history;
  final List<OrderIssueModel> issues;

  String get itemsLabel => items.map((i) => '${i.quantity} ${i.name}').join(' · ');

  factory OrderModel.fromJson(Map<String, dynamic> json) => OrderModel(
        id: json['id'] as int,
        orderNumber: json['order_number'] as String,
        pressingName: (json['pressing'] as Map<String, dynamic>?)?['name'] as String? ?? '',
        status: _statusFromApi(json['status'] as String),
        totalFcfa: json['total_fcfa'] as int,
        droppedOffAt: DateTime.parse(json['dropped_off_at'] as String),
        expectedAt: json['expected_at'] != null ? DateTime.parse(json['expected_at'] as String) : null,
        items: (json['items'] as List? ?? [])
            .map((e) => OrderItemModel.fromJson(e as Map<String, dynamic>))
            .toList(),
        history: (json['status_history'] as List? ?? [])
            .map((e) => OrderStatusEvent.fromJson(e as Map<String, dynamic>))
            .toList(),
        issues: (json['issues'] as List? ?? [])
            .map((e) => OrderIssueModel.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

/// Commandes du client — Cahier §10.2/10.3.
class OrderRepository {
  OrderRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<OrderModel>> list() async {
    final response = await _apiClient.dio.get('/orders');
    final data = (response.data as Map<String, dynamic>)['data'] as List;
    return data.map((e) => OrderModel.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<OrderModel> show(int id) async {
    final response = await _apiClient.dio.get('/orders/$id');
    return OrderModel.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> reportIssue({required int orderId, required IssueCategory category, String? description}) async {
    await _apiClient.dio.post('/orders/$orderId/issues', data: {
      'category': category.value,
      'description': description,
    });
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
