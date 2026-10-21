'use strict';
// PressLink (espace pressing) — appli installable sur PC.
//
// Ne touche qu'aux ouvertures de page : en ligne, tout vient du serveur
// comme d'habitude ; sans connexion, une page « Vous êtes hors connexion »
// s'affiche au lieu de l'erreur du navigateur. Les requêtes du tableau de
// bord (Livewire, fichiers) ne sont jamais interceptées.

const CACHE = 'presslink-espace-v1';
const OFFLINE_PAGE = '/hors-connexion.html';

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE)
      .then((cache) => cache.add(new Request(OFFLINE_PAGE, { cache: 'no-cache' })))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((key) => key.startsWith('presslink-espace-') && key !== CACHE).map((key) => caches.delete(key))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.mode !== 'navigate' || request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  // L'appli client (/app/) a son propre fonctionnement hors connexion.
  if (url.origin !== self.location.origin || url.pathname.startsWith('/app/')) {
    return;
  }
  event.respondWith(
    fetch(request).catch(async () => (await caches.match(OFFLINE_PAGE)) || Response.error())
  );
});
