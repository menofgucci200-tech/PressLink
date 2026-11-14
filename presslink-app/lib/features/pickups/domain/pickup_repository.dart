import 'package:dio/dio.dart';
import 'package:image_picker/image_picker.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_error.dart';

/// Statut d'une demande de ramassage (mêmes valeurs que l'API).
enum PickupStatus {
  pending,
  accepted,
  refused,
  cancelled,
  expired,

  /// Linge ramassé : la demande est devenue une commande ([PickupRequest.orderId]).
  pickedUp,

  /// Le coursier est passé, personne n'était là.
  missed;

  static PickupStatus fromValue(String? value) => switch (value) {
    'picked_up' => PickupStatus.pickedUp,
    _ => PickupStatus.values.firstWhere(
      (s) => s.name == value,
      orElse: () => PickupStatus.pending,
    ),
  };

  bool get isOpen => this == pending || this == accepted;
}

/// Créneau de ramassage (« 08:00 – 12:00 »).
class PickupSlot {
  const PickupSlot({required this.from, required this.to});

  final String from;
  final String to;

  String get label => '$from – $to';

  factory PickupSlot.fromJson(Map<String, dynamic> json) =>
      PickupSlot(from: json['from'] as String, to: json['to'] as String);

  @override
  bool operator ==(Object other) =>
      other is PickupSlot && other.from == from && other.to == to;

  @override
  int get hashCode => Object.hash(from, to);
}

/// Jour de ramassage proposé, avec ses créneaux encore libres.
class PickupDay {
  const PickupDay({
    required this.date,
    required this.label,
    required this.slots,
  });

  final String date;
  final String label;
  final List<PickupSlot> slots;

  factory PickupDay.fromJson(Map<String, dynamic> json) => PickupDay(
    date: json['date'] as String,
    label: json['label'] as String,
    slots: (json['slots'] as List<dynamic>)
        .map((e) => PickupSlot.fromJson(e as Map<String, dynamic>))
        .toList(),
  );
}

class PickupPhoto {
  const PickupPhoto({
    required this.id,
    required this.url,
    required this.thumbUrl,
  });

  final int id;
  final String url;
  final String thumbUrl;

  factory PickupPhoto.fromJson(Map<String, dynamic> json) => PickupPhoto(
    id: json['id'] as int,
    url: json['url'] as String,
    thumbUrl: json['thumb_url'] as String,
  );
}

/// Demande de ramassage envoyée à l'un des pressings du client.
class PickupRequest {
  const PickupRequest({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.pressingId,
    required this.pressingName,
    required this.addressLabel,
    required this.commune,
    required this.landmark,
    required this.scheduleLabel,
    required this.rescheduled,
    required this.pickupFeeFcfa,
    required this.canCancel,
    required this.canAddPhotos,
    required this.createdAt,
    this.pressingPhone,
    this.neighborhood,
    this.itemsEstimate,
    this.note,
    this.refusalReason,
    this.photos = const [],
    this.orderId,
  });

  final int id;
  final PickupStatus status;
  final String statusLabel;
  final int pressingId;
  final String pressingName;
  final String? pressingPhone;
  final String addressLabel;
  final String commune;
  final String? neighborhood;
  final String landmark;

  /// « demain, 08:00 – 12:00 »
  final String scheduleLabel;
  final bool rescheduled;
  final int? itemsEstimate;
  final String? note;
  final int pickupFeeFcfa;
  final String? refusalReason;
  final List<PickupPhoto> photos;
  final bool canCancel;
  final bool canAddPhotos;
  final DateTime createdAt;

  /// Commande créée au ramassage.
  final int? orderId;

  String get place =>
      [commune, if ((neighborhood ?? '').isNotEmpty) neighborhood!].join(', ');

  factory PickupRequest.fromJson(Map<String, dynamic> json) {
    final pressing = json['pressing'] as Map<String, dynamic>;
    final address = json['address'] as Map<String, dynamic>;
    return PickupRequest(
      id: json['id'] as int,
      status: PickupStatus.fromValue(json['status'] as String?),
      statusLabel: json['status_label'] as String? ?? '',
      pressingId: pressing['id'] as int,
      pressingName: pressing['name'] as String,
      pressingPhone: pressing['phone'] as String?,
      addressLabel: address['label'] as String? ?? '',
      commune: address['commune'] as String? ?? '',
      neighborhood: address['neighborhood'] as String?,
      landmark: address['landmark'] as String? ?? '',
      scheduleLabel: json['schedule_label'] as String? ?? '',
      rescheduled: json['rescheduled'] as bool? ?? false,
      itemsEstimate: json['items_estimate'] as int?,
      note: json['note'] as String?,
      pickupFeeFcfa: json['pickup_fee_fcfa'] as int? ?? 0,
      refusalReason: json['refusal_reason'] as String?,
      photos: (json['photos'] as List<dynamic>? ?? [])
          .map((e) => PickupPhoto.fromJson(e as Map<String, dynamic>))
          .toList(),
      canCancel: json['can_cancel'] as bool? ?? false,
      canAddPhotos: json['can_add_photos'] as bool? ?? false,
      createdAt: DateTime.parse(json['created_at'] as String),
      orderId: json['order_id'] as int?,
    );
  }
}

/// Demandes de ramassage (mode « Mon pressing »).
class PickupRepository {
  PickupRepository(this._apiClient);

  /// Même limite que l'API.
  static const int maxPhotos = 6;

  final ApiClient _apiClient;

  Future<List<PickupDay>> availability(int pressingId) async {
    final response = await _apiClient.dio.get(
      '/pressings/$pressingId/pickup-availability',
    );
    return ((response.data as Map<String, dynamic>)['days'] as List)
        .map((e) => PickupDay.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<PickupRequest>> list() async {
    final body = await _apiClient.getKeepingCopy('/pickup-requests');
    return (body as List)
        .map((e) => PickupRequest.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<PickupRequest> get(int id) async {
    final response = await _apiClient.dio.get('/pickup-requests/$id');
    return PickupRequest.fromJson(response.data as Map<String, dynamic>);
  }

  Future<PickupRequest> create({
    required int pressingId,
    required int addressId,
    required String date,
    required PickupSlot slot,
    int? itemsEstimate,
    String? note,
  }) async {
    final response = await _apiClient.dio.post(
      '/pickup-requests',
      data: {
        'pressing_id': pressingId,
        'address_id': addressId,
        'pickup_date': date,
        'slot_from': slot.from,
        'slot_to': slot.to,
        'items_estimate': itemsEstimate,
        'note': (note ?? '').trim().isEmpty ? null : note!.trim(),
      },
    );
    return PickupRequest.fromJson(response.data as Map<String, dynamic>);
  }

  /// Envoie les octets (pas de `dart:io` dans la PWA).
  Future<PickupRequest> addPhoto(int id, XFile file) async {
    final form = FormData.fromMap({
      'photo': MultipartFile.fromBytes(
        await file.readAsBytes(),
        filename: file.name.isNotEmpty ? file.name : 'photo.jpg',
        contentType: DioMediaType.parse(file.mimeType ?? 'image/jpeg'),
      ),
    });
    final response = await _apiClient.dio.post(
      '/pickup-requests/$id/photos',
      data: form,
    );
    return PickupRequest.fromJson(response.data as Map<String, dynamic>);
  }

  Future<PickupRequest> cancel(int id) async {
    final response = await _apiClient.dio.post('/pickup-requests/$id/cancel');
    return PickupRequest.fromJson(response.data as Map<String, dynamic>);
  }

  static String errorMessage(Object error) => apiErrorMessage(error);
}
