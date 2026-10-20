import '../../../core/network/api_client.dart';
import '../../../core/network/api_error.dart';
import '../../../core/theme/app_colors.dart';

class OrderItemModel {
  const OrderItemModel({
    required this.name,
    required this.quantity,
    required this.subtotalFcfa,
    this.color,
  });

  final String name;
  final int quantity;
  final int subtotalFcfa;

  /// Couleur de l'article, ex. "Bleu" — déjà incluse dans [name] pour
  /// l'affichage, exposée séparément pour un usage futur (filtre, badge…).
  final String? color;

  factory OrderItemModel.fromJson(Map<String, dynamic> json) => OrderItemModel(
    name: json['name'] as String,
    quantity: json['quantity'] as int,
    subtotalFcfa: json['subtotal_fcfa'] as int,
    color: json['color'] as String?,
  );
}

/// État du règlement, calculé par l'API (payée, acompte versé, à payer).
enum PaymentState { paid, partial, unpaid }

PaymentState _paymentStateFromApi(String? value) => switch (value) {
  'payee' => PaymentState.paid,
  'acompte' => PaymentState.partial,
  _ => PaymentState.unpaid,
};

/// Un paiement enregistré par le pressing (au dépôt, acompte, solde).
class OrderPaymentModel {
  const OrderPaymentModel({
    required this.amountFcfa,
    required this.methodLabel,
    required this.paidAt,
  });

  final int amountFcfa;
  final String methodLabel;
  final DateTime paidAt;

  factory OrderPaymentModel.fromJson(Map<String, dynamic> json) =>
      OrderPaymentModel(
        amountFcfa: json['amount_fcfa'] as int,
        methodLabel: json['method_label'] as String? ?? '',
        paidAt: DateTime.parse(json['paid_at'] as String),
      );
}

class OrderStatusEvent {
  const OrderStatusEvent({required this.status, required this.at});

  final OrderStatus status;
  final DateTime at;

  factory OrderStatusEvent.fromJson(Map<String, dynamic> json) =>
      OrderStatusEvent(
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
  const OrderIssueModel({
    required this.id,
    required this.category,
    required this.description,
    required this.isResolved,
    required this.createdAt,
  });

  final int id;
  final IssueCategory category;
  final String? description;
  final bool isResolved;
  final DateTime createdAt;

  factory OrderIssueModel.fromJson(Map<String, dynamic> json) =>
      OrderIssueModel(
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
    required this.pressingId,
    required this.pressingName,
    required this.status,
    required this.totalFcfa,
    required this.droppedOffAt,
    required this.items,
    this.expectedAt,
    this.history = const [],
    this.issues = const [],
    this.amountPaidFcfa = 0,
    this.balanceDueFcfa = 0,
    this.paymentState = PaymentState.unpaid,
    this.payments = const [],
    this.cancellationReason,
    this.receipt,
    this.storageFeeAmount,
    this.storageFeeDays,
    this.storageFeeClause,
  });

  final int id;
  final String orderNumber;
  final int? pressingId;
  final String pressingName;
  final OrderStatus status;
  final int totalFcfa;
  final DateTime droppedOffAt;
  final DateTime? expectedAt;
  final List<OrderItemModel> items;
  final List<OrderStatusEvent> history;
  final List<OrderIssueModel> issues;
  final int amountPaidFcfa;

  /// Reste à payer au pressing (au retrait).
  final int balanceDueFcfa;
  final PaymentState paymentState;
  final List<OrderPaymentModel> payments;

  /// Motif d'annulation (« Erreur de saisie », « Le client a renoncé »…).
  final String? cancellationReason;

  /// Reçu de dépôt (PDF) à ouvrir, télécharger ou partager.
  final OrderReceipt? receipt;

  /// Droit de magasinage dû à ce jour (commande prête non retirée à temps).
  final int? storageFeeAmount;
  final int? storageFeeDays;
  final String? storageFeeClause;

  String get itemsLabel =>
      items.map((i) => '${i.quantity} ${i.name}').join(' · ');

  factory OrderModel.fromJson(Map<String, dynamic> json) => OrderModel(
    id: json['id'] as int,
    orderNumber: json['order_number'] as String,
    pressingId: (json['pressing'] as Map<String, dynamic>?)?['id'] as int?,
    pressingName:
        (json['pressing'] as Map<String, dynamic>?)?['name'] as String? ?? '',
    status: _statusFromApi(json['status'] as String),
    totalFcfa: json['total_fcfa'] as int,
    droppedOffAt: DateTime.parse(json['dropped_off_at'] as String),
    expectedAt: json['expected_at'] != null
        ? DateTime.parse(json['expected_at'] as String)
        : null,
    items: (json['items'] as List? ?? [])
        .map((e) => OrderItemModel.fromJson(e as Map<String, dynamic>))
        .toList(),
    history: (json['status_history'] as List? ?? [])
        .map((e) => OrderStatusEvent.fromJson(e as Map<String, dynamic>))
        .toList(),
    issues: (json['issues'] as List? ?? [])
        .map((e) => OrderIssueModel.fromJson(e as Map<String, dynamic>))
        .toList(),
    amountPaidFcfa: json['amount_paid_fcfa'] as int? ?? 0,
    balanceDueFcfa:
        json['balance_due_fcfa'] as int? ?? json['total_fcfa'] as int,
    paymentState: _paymentStateFromApi(json['payment_status'] as String?),
    payments: (json['payments'] as List? ?? [])
        .map((e) => OrderPaymentModel.fromJson(e as Map<String, dynamic>))
        .toList(),
    cancellationReason: json['cancellation_reason_label'] as String?,
    receipt: json['receipt'] is Map<String, dynamic>
        ? OrderReceipt.fromJson(json['receipt'] as Map<String, dynamic>)
        : null,
    storageFeeAmount:
        (json['storage_fee'] as Map<String, dynamic>?)?['amount'] as int?,
    storageFeeDays:
        (json['storage_fee'] as Map<String, dynamic>?)?['days'] as int?,
    storageFeeClause: json['storage_fee_clause'] as String?,
  );
}

/// Liens du reçu de dépôt : valables quelques semaines, ils s'ouvrent sans
/// connexion (on peut donc les envoyer à quelqu'un d'autre).
class OrderReceipt {
  const OrderReceipt({
    required this.url,
    required this.downloadUrl,
    required this.fileName,
    required this.shareText,
  });

  final String url;
  final String downloadUrl;
  final String fileName;
  final String shareText;

  factory OrderReceipt.fromJson(Map<String, dynamic> json) => OrderReceipt(
    url: json['url'] as String,
    downloadUrl: json['download_url'] as String,
    fileName: json['file_name'] as String? ?? 'recu.pdf',
    shareText: json['share_text'] as String? ?? 'Reçu PressLink',
  );
}

/// Commandes du client — Cahier §10.2/10.3.
class OrderRepository {
  OrderRepository(this._apiClient);

  final ApiClient _apiClient;

  Future<List<OrderModel>> list() async {
    final response = await _apiClient.dio.get('/orders');
    final data = (response.data as Map<String, dynamic>)['data'] as List;
    return data
        .map((e) => OrderModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<OrderModel> show(int id) async {
    final response = await _apiClient.dio.get('/orders/$id');
    return OrderModel.fromJson(response.data as Map<String, dynamic>);
  }

  Future<void> reportIssue({
    required int orderId,
    required IssueCategory category,
    String? description,
  }) async {
    await _apiClient.dio.post(
      '/orders/$orderId/issues',
      data: {'category': category.value, 'description': description},
    );
  }

  static String errorMessage(Object error) => apiErrorMessage(error);
}
