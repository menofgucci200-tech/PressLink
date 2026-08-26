import '../../../core/network/api_client.dart';

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    required this.orderId,
    required this.createdAt,
    required this.isRead,
  });

  final String id;
  final String title;
  final String body;
  final int? orderId;
  final DateTime createdAt;
  final bool isRead;

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    final data = json['data'] as Map<String, dynamic>;
    return AppNotification(
      id: json['id'] as String,
      title: data['title'] as String? ?? '',
      body: data['body'] as String? ?? '',
      orderId: data['order_id'] as int?,
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
    final response = await _apiClient.dio.get('/notifications');
    final data = (response.data as Map<String, dynamic>)['data'] as List;
    return data.map((e) => AppNotification.fromJson(e as Map<String, dynamic>)).toList();
  }

  Future<int> unreadCount() async {
    final response = await _apiClient.dio.get('/notifications/unread-count');
    return (response.data as Map<String, dynamic>)['count'] as int;
  }

  Future<void> markAsRead(String id) async {
    await _apiClient.dio.post('/notifications/$id/read');
  }
}
