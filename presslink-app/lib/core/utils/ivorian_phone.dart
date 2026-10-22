import 'package:flutter/services.dart';

/// Numéros de téléphone ivoiriens : « +225 » suivi de 10 chiffres, le seul
/// format accepté par PressLink (même règle que le serveur, qui servira aux
/// SMS et aux messages WhatsApp). Les mobiles commencent par 01, 05 ou 07.
class IvorianPhone {
  IvorianPhone._();

  static const prefix = '+225';
  static const length = 10;
  static const mobilePrefixes = ['01', '05', '07'];

  /// Plage réservée aux comptes de démonstration (+225 00 00 00 00 xx).
  static final _demo = RegExp(r'^00000000\d{2}$');

  /// Garde les chiffres du numéro national (10 au plus) à partir de ce qui a
  /// été tapé ou collé : « +225 07 08 12 44 00 » donne « 0708124400 ».
  static String digitsOf(String text) {
    var digits = text.replaceAll(RegExp(r'\D'), '');
    if (digits.startsWith('00225')) {
      digits = digits.substring(5);
    } else if (digits.startsWith('225') && digits.length > length) {
      digits = digits.substring(3);
    }
    return digits.length > length ? digits.substring(0, length) : digits;
  }

  /// Format envoyé au serveur : « 0708124400 » → « +2250708124400 ».
  static String toStored(String digits) => '$prefix$digits';

  /// Message d'erreur, ou null si le numéro (10 chiffres) est valide.
  static String? validate(String digits) {
    if (digits.isEmpty) return 'Indiquez votre numéro de téléphone.';
    if (digits.length < length) {
      return 'Entrez un numéro ivoirien valide à 10 chiffres.';
    }
    if (_demo.hasMatch(digits)) return null;
    if (!mobilePrefixes.contains(digits.substring(0, 2))) {
      return 'Entrez un numéro de mobile ivoirien : il commence par 01, 05 ou 07.';
    }
    return null;
  }

  /// Chiffres groupés par deux : « 0708124400 » → « 07 08 12 44 00 ».
  static String pairs(String digits) =>
      digits.replaceAllMapped(RegExp(r'(\d{2})(?=\d)'), (m) => '${m[1]} ');

  /// « +2250708124400 » → « +225 07 08 12 44 00 » (affichage).
  static String format(String stored) {
    final match = RegExp(r'^\+225(\d{10})$').firstMatch(stored);
    return match == null ? stored : '$prefix ${pairs(match.group(1)!)}';
  }
}

/// Saisie limitée à 10 chiffres, affichés par paires au fil de la frappe.
///
/// Le curseur reste où le client tape (correction d'un chiffre au milieu) ;
/// un champ déjà plein refuse un chiffre de plus au lieu de faire sauter les
/// derniers.
class IvorianPhoneInputFormatter extends TextInputFormatter {
  static final _nonDigit = RegExp(r'\D');

  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    final raw = newValue.text.replaceAll(_nonDigit, '');
    var national = raw;
    if (national.startsWith('00225')) {
      national = national.substring(5);
    } else if (national.startsWith('225') &&
        national.length > IvorianPhone.length) {
      national = national.substring(3);
    }

    var cursorAtEnd = national.length != raw.length;
    if (national.length > IvorianPhone.length) {
      final oldDigits = oldValue.text.replaceAll(_nonDigit, '');
      if (oldDigits.length >= IvorianPhone.length) return oldValue;
      // Numéro collé trop long : on garde les 10 premiers chiffres.
      national = national.substring(0, IvorianPhone.length);
      cursorAtEnd = true;
    }

    final text = IvorianPhone.pairs(national);
    var offset = text.length;
    final selection = newValue.selection;
    if (!cursorAtEnd && selection.isValid) {
      final end = selection.extentOffset.clamp(0, newValue.text.length);
      final digitsBefore = newValue.text
          .substring(0, end)
          .replaceAll(_nonDigit, '')
          .length;
      offset = _offsetAfterDigits(text, digitsBefore);
    }
    return TextEditingValue(
      text: text,
      selection: TextSelection.collapsed(offset: offset),
    );
  }

  /// Position juste après le [count]-ième chiffre de [text].
  static int _offsetAfterDigits(String text, int count) {
    if (count <= 0) return 0;
    var seen = 0;
    for (var i = 0; i < text.length; i++) {
      if (_nonDigit.hasMatch(text[i])) continue;
      seen++;
      if (seen == count) return i + 1;
    }
    return text.length;
  }
}
