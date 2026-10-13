// Service worker des notifications push (OneSignal), isolé dans /app/push/ :
// le service worker de Flutter (/app/) se désinscrit lui-même et emporterait
// l'abonnement s'ils partageaient la même portée.
importScripts('https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.sw.js');
