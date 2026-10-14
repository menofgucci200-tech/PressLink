import '../../core/theme/app_colors.dart';

/// Montant en FCFA avec espaces de milliers : 12500 → « 12 500 ».
String formatFcfa(int value) {
  final s = value.toString();
  final buffer = StringBuffer();
  for (var i = 0; i < s.length; i++) {
    if (i != 0 && (s.length - i) % 3 == 0) buffer.write(' ');
    buffer.write(s[i]);
  }
  return buffer.toString();
}

/// Modèle d'affichage minimal pour une commande dans les listes.
/// Sera remplacé par le modèle issu de l'API en Phase 3.
class OrderSummary {
  const OrderSummary({
    required this.orderNumber,
    required this.pressingName,
    required this.items,
    required this.status,
    required this.totalFcfa,
    this.balanceDueFcfa,
  });

  final String orderNumber;
  final String pressingName;
  final String items;
  final OrderStatus status;
  final int totalFcfa;

  /// Reste à payer ; `null` quand l'info n'est pas connue (pas d'affichage).
  final int? balanceDueFcfa;

  String get formattedTotal => '${formatFcfa(totalFcfa)} FCFA';
}
