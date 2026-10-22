/// Notifications push web : sans objet hors navigateur (APK/IPA utilisent
/// le SDK OneSignal natif).
class WebPush {
  const WebPush._();

  static bool get isSupported => false;
  static String get permission => 'unsupported';
  static Future<void> init(String appId) async {}
  static Future<String> requestPermission() async => 'unsupported';
  static Future<String?> subscriptionId() async => null;
  static void onSubscriptionChange(void Function(String id) callback) {}
  static void onClick(void Function(int? orderId) callback) {}
  static void onForeground(void Function() callback) {}
  static Future<String> setOptedIn(bool enabled) async => 'unsupported';
  static Future<void> logout() async {}
}
