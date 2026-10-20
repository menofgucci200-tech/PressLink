import 'package:flutter_test/flutter_test.dart';
import 'package:presslink_app/features/auth/domain/auth_repository.dart';

void main() {
  test('un numéro enregistré par un pressing pré-remplit l\'inscription', () {
    final check = PhoneCheck.fromJson({
      'exists': false,
      'pending': true,
      'prefill': {'first_name': 'Awa', 'last_name': 'Konan', 'gender': 'femme'},
      'orders_count': 3,
    });

    expect(check.exists, isFalse);
    expect(check.pending, isTrue);
    expect(check.firstName, 'Awa');
    expect(check.lastName, 'Konan');
    expect(check.gender, Gender.femme);
    expect(check.ordersCount, 3);
  });

  test('un compte déjà créé mène à la connexion', () {
    final check = PhoneCheck.fromJson({'exists': true, 'pending': false});

    expect(check.exists, isTrue);
    expect(check.pending, isFalse);
    expect(check.gender, isNull);
  });
}
