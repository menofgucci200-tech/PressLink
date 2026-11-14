import '../../../core/network/api_client.dart';

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.orderId,
    this.pickupRequestId,
    required this.createdAt,
    required this.isRead,
  });

  final String id;
  final String title;
  final String body;
  final int? orderId;

  /// Notification d'une demande de ramassage (prévu, refusé, expiré).
  final int? pickupRequestId;
  final DateTime createdAt;
  final bool isRead;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return AppNotification(
      id: json['id'] as String,
      title: data['title'] as String? ?? '',
      body: data['body'] as String? ?? '',
      orderId: data['order_id'] as int?,
      pickupRequestId: data['pickup_request_id'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
      isRead: json['read_at'] != null,
    );
  }
}

/// Notifications du client — Cahier §9 / App client §7.
class NotificationRepository {
  NotificationRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<AppNotification>> list() async {
    final body = await _apiClient.getKeepingCopy('/notifications');
    final data = (body as Map<String, dynamic>)['data'] as List;
    return data
        .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<int> unreadCount() async {
    final body = await _apiClient.getKeepingCopy('/notifications/unread-count');
    return (body as Map<String, dynamic>)['count'] as int;
  }

  Future<void> markAsRead(String id) async {
    await _apiClient.dio.post('/notifications/$id/read');
  }

  Future<void> markAllAsRead() async {
    await _apiClient.dio.post('/notifications/read-all');
  }
}
