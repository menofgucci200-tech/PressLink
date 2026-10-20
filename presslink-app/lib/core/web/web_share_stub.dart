/// Pas de partage natif hors du navigateur.
bool canShareNatively() => false;

Future<bool> shareNatively({
  required String title,
  required String text,
  required String url,
}) async => false;
