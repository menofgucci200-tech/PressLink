import 'dart:js_interop';
import 'dart:js_interop_unsafe';

@JS('navigator')
external JSObject get _navigator;

/// Le navigateur propose-t-il sa feuille de partage ?
bool canShareNatively() => _navigator.has('share');

/// Ouvre la feuille de partage. À appeler directement depuis le geste de
/// l'utilisateur (onPressed), sans attente avant : sinon le navigateur refuse.
/// Renvoie true si la feuille a pu s'ouvrir (même si l'utilisateur l'a fermée).
Future<bool> shareNatively({
  required String title,
  required String text,
  required String url,
}) async {
  if (!canShareNatively()) return false;
  final data = JSObject()
    ..['title'] = title.toJS
    ..['text'] = text.toJS
    ..['url'] = url.toJS;
  try {
    await _navigator.callMethod<JSPromise>('share'.toJS, data).toDart;
  } catch (_) {
    // Partage annulé par l'utilisateur : rien à signaler.
  }
  return true;
}
