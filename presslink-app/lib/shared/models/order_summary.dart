import '../../core/theme/app_colors.dart';

/// Modèle d'affichage minimal pour une commande dans les listes.
/// Sera remplacé par le modèle issu de l'API en Phase 3.
class OrderSummary {
  const OrderSummary({
    required this.orderNumber,
    required this.pressingName,
    required this.items,
    required this.status,
    required this.totalFcfa,
  });

  final String orderNumber;
  final String pressingName;
  final String items;
  final OrderStatus status;
  final int totalFcfa;

  String get formattedTotal {
    final s = totalFcfa.toString();
    final buffer = StringBuffer();
    for (var i = 0; i < s.length; i++) {
      if (i != 0 && (s.length - i) % 3 == 0) buffer.write(' ');
      buffer.write(s[i]);
    }
    return '${buffer.toString()} FCFA';
  }
}
