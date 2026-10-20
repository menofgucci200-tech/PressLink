/// Libellé d'une ligne d'articles : « 3 Chemises », « 1 Costume · Repassage »,
/// « Grand boubou brodé × 2 ».
///
/// Le nom de la pièce (avant un éventuel « · service ») est mis au pluriel
/// quand c'est un seul mot ; un nom de plusieurs mots garde la forme
/// « nom × quantité », toujours juste, plutôt qu'un pluriel hasardeux.
String libelleArticle(int quantite, String nom) {
  final separateur = nom.indexOf(' · ');
  final piece = separateur == -1 ? nom : nom.substring(0, separateur);
  final reste = separateur == -1 ? '' : nom.substring(separateur);
  if (quantite <= 1) return '$quantite $nom';
  if (piece.trim().contains(' ') || piece.contains('/'))
    return '$nom × $quantite';
  return '$quantite ${pluriel(piece)}$reste';
}

/// Pluriel d'un nom de vêtement (règles courantes du français).
String pluriel(String mot) {
  final m = mot.toLowerCase();
  if (m.endsWith('s') || m.endsWith('x') || m.endsWith('z')) return mot;
  if (m.endsWith('eau') || m.endsWith('au') || m.endsWith('eu'))
    return '${mot}x';
  if (m.endsWith('al') && !const ['bal', 'carnaval', 'festival'].contains(m)) {
    return '${mot.substring(0, mot.length - 2)}aux';
  }
  return '${mot}s';
}
