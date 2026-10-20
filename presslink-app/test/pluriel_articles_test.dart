import 'package:flutter_test/flutter_test.dart';
import 'package:presslink_app/core/utils/pluriel_articles.dart';

void main() {
  test('un seul mot passe au pluriel', () {
    expect(libelleArticle(2, 'Robe'), '2 Robes');
    expect(libelleArticle(3, 'Chemise'), '3 Chemises');
    expect(libelleArticle(2, 'Manteau'), '2 Manteaux');
    expect(libelleArticle(2, 'Pagnes'), '2 Pagnes');
  });

  test('une seule pièce reste au singulier', () {
    expect(libelleArticle(1, 'Drap'), '1 Drap');
  });

  test('le service reste tel quel après la pièce', () {
    expect(
      libelleArticle(2, 'Pantalon · Repassage'),
      '2 Pantalons · Repassage',
    );
  });

  test('un nom de plusieurs mots garde la forme « × »', () {
    expect(libelleArticle(2, 'Grand boubou brodé'), 'Grand boubou brodé × 2');
    expect(
      libelleArticle(2, 'Boubou / Grand boubou'),
      'Boubou / Grand boubou × 2',
    );
  });
}
