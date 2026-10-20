// Partage natif du navigateur (navigator.share) : feuille de partage du
// téléphone (WhatsApp, SMS, e-mail…). Hors web, ou si le navigateur ne la
// propose pas, [shareNatively] renvoie false et l'appelant prend le relais.
export 'web_share_stub.dart' if (dart.library.js_interop) 'web_share_web.dart';
