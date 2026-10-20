import 'package:flutter_test/flutter_test.dart';
import 'package:presslink_app/core/utils/ivorian_phone.dart';

void main() {
  group('IvorianPhone', () {
    test('garde 10 chiffres au plus, même collés avec l\'indicatif', () {
      expect(IvorianPhone.digitsOf('07 08 12 44 00'), '0708124400');
      expect(IvorianPhone.digitsOf('+225 07 08 12 44 00'), '0708124400');
      expect(IvorianPhone.digitsOf('0708124400999'), '0708124400');
    });

    test(
      'refuse moins de 10 chiffres et les préfixes qui ne sont pas des mobiles',
      () {
        expect(IvorianPhone.validate(''), isNotNull);
        expect(IvorianPhone.validate('070812440'), contains('10 chiffres'));
        expect(IvorianPhone.validate('0308124400'), contains('01, 05 ou 07'));
        expect(IvorianPhone.validate('0708124400'), isNull);
        expect(IvorianPhone.validate('0500000000'), isNull);
      },
    );

    test('envoie le format stocké et affiche par paires', () {
      expect(IvorianPhone.toStored('0708124400'), '+2250708124400');
      expect(IvorianPhone.format('+2250708124400'), '+225 07 08 12 44 00');
    });

    test('la saisie est bloquée à 10 chiffres et groupée par deux', () {
      final formatter = IvorianPhoneInputFormatter();
      final value = formatter.formatEditUpdate(
        TextEditingValue.empty,
        const TextEditingValue(text: '07081244001'),
      );
      expect(value.text, '07 08 12 44 00');
    });
  });
}
