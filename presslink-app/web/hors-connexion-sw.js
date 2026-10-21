'use strict';
// PressLink (appli client) — ouverture hors connexion.
//
// Garde dans le téléphone ce qu'il faut pour rouvrir l'appli sans Internet :
// la page, le code, les ressources de la version en cours et le moteur
// d'affichage Flutter (servi par gstatic.com). Les données (commandes,
// pressings…) sont gardées par l'appli elle-même (lib/core/network).
//
// En ligne, la page et le code viennent toujours du serveur (une mise à jour
// est prise tout de suite) ; le cache ne sert qu'en cas d'échec du réseau.

const CACHE = 'presslink-app-hors-connexion-v1';
const SHELL = ['./', 'index.html', 'flutter_bootstrap.js', 'flutter.js', 'main.dart.js', 'manifest.json', 'favicon.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => Promise.all(SHELL.map((path) => cache.add(new Request(path, { cache: 'no-cache' })).catch(() => null))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith('presslink-app-') && key !== CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

const scopePath = new URL(self.registration.scope).pathname;

function isVersionedAsset(url) {
  return /\/v[0-9a-f]{10}\//.test(url.pathname) || /\/(icons|splash)\//.test(url.pathname);
}

function isFlutterEngine(url) {
  return url.hostname === 'www.gstatic.com' && url.pathname.startsWith('/flutter-canvaskit/');
}

async function networkFirst(request, fallbackPath) {
  const cache = await caches.open(CACHE);
  try {
    const response = await fetch(request);
    if (response.ok) {
      cache.put(request, response.clone());
    }
    return response;
  } catch (error) {
    const cached = (await cache.match(request, { ignoreSearch: true }))
      || (fallbackPath ? await cache.match(new URL(fallbackPath, self.registration.scope).href) : undefined);
    if (cached) {
      return cached;
    }
    throw error;
  }
}

async function cacheFirst(request) {
  const cache = await caches.open(CACHE);
  const cached = await cache.match(request);
  if (cached) {
    return cached;
  }
  const response = await fetch(request);
  if (response.ok || response.type === 'opaque') {
    cache.put(request, response.clone());
  }
  return response;
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);

  if (url.origin === self.location.origin) {
    // Hors de l'appli (site, API) et notifications push : jamais touchés.
    if (!url.pathname.startsWith(scopePath) || url.pathname.startsWith(scopePath + 'push/')) {
      return;
    }
    if (request.mode === 'navigate') {
      event.respondWith(networkFirst(request, 'index.html'));
    } else if (isVersionedAsset(url)) {
      event.respondWith(cacheFirst(request));
    } else {
      event.respondWith(networkFirst(request));
    }
    return;
  }

  if (isFlutterEngine(url)) {
    event.respondWith(cacheFirst(request));
  }
});

// Premier passage : la page envoie la liste de ce qu'elle vient de charger,
// pour que tout soit déjà gardé avant même la prochaine ouverture.
self.addEventListener('message', (event) => {
  const data = event.data || {};
  if (data.type !== 'cache-urls' || !Array.isArray(data.urls)) {
    return;
  }
  event.waitUntil(
    caches.open(CACHE).then((cache) => Promise.all(data.urls.map(async (href) => {
      try {
        const url = new URL(href);
        const sameApp = url.origin === self.location.origin && url.pathname.startsWith(scopePath) && !url.pathname.startsWith(scopePath + 'push/');
        if (!sameApp && !isFlutterEngine(url)) {
          return;
        }
        if (await cache.match(href)) {
          return;
        }
        const response = await fetch(href, isFlutterEngine(url) ? { mode: 'cors' } : {});
        if (response.ok) {
          await cache.put(href, response);
        }
      } catch (error) {
        // Ressource introuvable : elle sera gardée à son prochain chargement.
      }
    })))
  );
});
