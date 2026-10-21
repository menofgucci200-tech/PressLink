'use strict';
// PressLink (espace pressing) — appli installable sur PC et comptoir hors
// connexion.
//
// En ligne, tout vient du serveur comme d'habitude. Sans connexion, ouvrir
// une page de l'espace pressing affiche le comptoir hors connexion (gardé
// ici à chaque passage en ligne sur le tableau de bord, voir
// resources/js/synchro-hors-ligne.js) ; à défaut, une page « Vous êtes hors
// connexion ». Les requêtes du tableau de bord (Livewire) ne sont jamais
// interceptées.

const CACHE = 'presslink-espace-v2';
const OFFLINE_PAGE = '/hors-connexion.html';
const COUNTER_PAGE = '/comptoir-hors-ligne';

// Pages du site public : sans connexion, simple page « hors connexion ».
const PUBLIC_PAGES = /^\/($|pour-les-|offres|formules|comment-ca-marche|installer|questions-frequentes|contact|mentions-legales|conditions-generales|confidentialite|sitemap)/;

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

async function offlineFallback(url) {
  const cache = await caches.open(CACHE);
  if (!PUBLIC_PAGES.test(url.pathname)) {
    const counter = await cache.match(COUNTER_PAGE);
    if (counter) {
      return counter;
    }
  }
  return (await cache.match(OFFLINE_PAGE)) || Response.error();
}

self.addEventListener('fetch', (event) => {
  const request = event.request;
  if (request.method !== 'GET') {
    return;
  }
  const url = new URL(request.url);
  // L'appli client (/app/) a son propre fonctionnement hors connexion.
  if (url.origin !== self.location.origin || url.pathname.startsWith('/app/')) {
    return;
  }

  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => offlineFallback(url)));
    return;
  }

  // Fichiers du comptoir (CSS, JS, polices, script Livewire) : réseau
  // d'abord, copie gardée seulement si le réseau ne répond pas.
  if (url.pathname.startsWith('/build/') || /^\/livewire[^/]*\/livewire(\.min)?\.js$/.test(url.pathname)) {
    event.respondWith(
      fetch(request).catch(async () => (await caches.match(request, { ignoreSearch: url.pathname.startsWith('/livewire') })) || Response.error())
    );
  }
});

// Garde la page du comptoir et tout ce qu'elle charge (styles, scripts,
// polices), pour pouvoir l'ouvrir à la prochaine coupure.
async function prepareCounter() {
  const cache = await caches.open(CACHE);
  const response = await fetch(COUNTER_PAGE, { credentials: 'same-origin', cache: 'no-store' });
  // Pas d'accès (formule, session) : redirection vers une autre page.
  if (!response.ok || response.redirected) {
    await cache.delete(COUNTER_PAGE);
    return;
  }
  const html = await response.clone().text();
  await cache.put(COUNTER_PAGE, response);

  const assets = new Set();
  for (const [, href] of html.matchAll(/(?:src|href)="([^"]+)"/g)) {
    const url = new URL(href, self.location.origin);
    if (url.origin === self.location.origin && (url.pathname.startsWith('/build/') || url.pathname.includes('/livewire'))) {
      assets.add(url.href);
    }
  }

  await Promise.all([...assets].map(async (href) => {
    try {
      if (await cache.match(href)) {
        return;
      }
      const asset = await fetch(href, { credentials: 'same-origin' });
      if (!asset.ok) {
        return;
      }
      await cache.put(href, asset.clone());
      // Polices et images appelées par la feuille de style.
      if (href.endsWith('.css')) {
        const css = await asset.text();
        await Promise.all([...css.matchAll(/url\(([^)]+)\)/g)].map(async ([, raw]) => {
          const ref = new URL(raw.replace(/["']/g, ''), href);
          if (ref.origin === self.location.origin && !(await cache.match(ref.href))) {
            const file = await fetch(ref.href).catch(() => null);
            if (file?.ok) {
              await cache.put(ref.href, file);
            }
          }
        }));
      }
    } catch (error) {
      // Fichier injoignable : il sera repris au prochain passage.
    }
  }));
}

self.addEventListener('message', (event) => {
  const type = (event.data || {}).type;
  if (type === 'preparer-comptoir') {
    event.waitUntil(prepareCounter().catch(() => {}));
  } else if (type === 'oublier-comptoir') {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.delete(COUNTER_PAGE)));
  }
});
