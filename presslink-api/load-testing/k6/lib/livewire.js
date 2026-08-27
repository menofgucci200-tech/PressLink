// Petit client Livewire "maison" pour k6.
//
// Les pages EMPLOYÉ/ADMIN/SUPER ADMIN de PressLink sont des composants
// Livewire (pas de routes POST classiques) : la connexion elle-même passe
// par Livewire (App\Livewire\Auth\Login::authenticate()), tout comme la
// création de commande et le changement de statut. Pour charger ces
// parcours avec de vraies requêtes HTTP (et non du Dusk/navigateur, jugé
// trop fragile pour du load-testing), ce module reproduit le protocole AJAX
// que le JS de Livewire utilise réellement :
//
//   1. GET la page → on y trouve le `wire:snapshot` du composant racine et
//      le préfixe d'URL de mise à jour (ex. /livewire-abcd1234/update).
//   2. POST ce préfixe avec { components: [{ snapshot, updates, calls }] },
//      authentifié par le cookie de session + le header X-XSRF-TOKEN (double
//      submit cookie de Laravel), et X-Livewire: true.
//   3. La réponse contient un nouveau `snapshot` (état réactualisé) et des
//      `effects` (ex. { redirect: '/' }) à rejouer soi-même si besoin.
//
// Protocole vérifié manuellement par curl contre ce même backend avant
// d'être encodé ici (voir load-testing/README.md).

import http from 'k6/http';

function htmlUnescape(text) {
  return text
    .replace(/&quot;/g, '"')
    .replace(/&#039;/g, "'")
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&amp;/g, '&');
}

export function extractUpdatePath(html) {
  const match = html.match(/livewire-([0-9a-f]+)\//);
  return match ? `/livewire-${match[1]}/update` : '/livewire/update';
}

/**
 * Extrait le wire:snapshot du composant racine d'une page. Les pages de ce
 * projet n'imbriquent qu'un seul composant Livewire par route, donc le
 * premier match correspond toujours au composant de la page.
 */
export function extractSnapshot(html) {
  const match = html.match(/wire:snapshot="([^"]*)"/);
  return match ? htmlUnescape(match[1]) : null;
}

export function csrfTokenFor(baseUrl) {
  const jar = http.cookieJar();
  const cookies = jar.cookiesForURL(baseUrl);
  const raw = cookies['XSRF-TOKEN'] && cookies['XSRF-TOKEN'][0];

  return raw ? decodeURIComponent(raw) : null;
}

/**
 * Charge une page Livewire (GET) et renvoie de quoi enchaîner des appels.
 */
export function livewireVisit(baseUrl, path, params) {
  const res = http.get(`${baseUrl}${path}`, params);

  return {
    res,
    html: res.body,
    updatePath: extractUpdatePath(res.body),
    snapshot: extractSnapshot(res.body),
  };
}

/**
 * Envoie une mise à jour/appel de méthode à un composant Livewire déjà
 * chargé (snapshot + updatePath viennent de livewireVisit, ou du résultat
 * précédent de livewireCall pour enchaîner plusieurs actions).
 *
 * @param {string} baseUrl
 * @param {string} updatePath
 * @param {string} snapshot
 * @param {{updates?: object, calls?: Array<{path?: string, method: string, params?: Array}>}} action
 */
export function livewireCall(baseUrl, updatePath, snapshot, action, params) {
  const csrf = csrfTokenFor(baseUrl);
  const payload = JSON.stringify({
    _token: '',
    components: [
      {
        snapshot,
        updates: (action && action.updates) || {},
        calls: (action && action.calls) || [],
      },
    ],
  });

  const res = http.post(`${baseUrl}${updatePath}`, payload, Object.assign({}, params, {
    headers: Object.assign(
      {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Livewire': 'true',
        'X-XSRF-TOKEN': csrf || '',
      },
      params && params.headers
    ),
  }));

  let body = null;
  try {
    body = res.json();
  } catch (e) {
    body = null;
  }

  const component = body && body.components && body.components[0];

  return {
    res,
    body,
    snapshot: component ? component.snapshot : null,
    effects: component ? component.effects : null,
  };
}

/**
 * Connexion staff (admin/employé/super admin) via le composant Livewire de
 * login. Retourne true si l'authentification a réussi (redirect renvoyé).
 */
export function loginAsStaff(baseUrl, login, password) {
  const page = livewireVisit(baseUrl, '/login');

  if (!page.snapshot) {
    return { success: false, page };
  }

  const result = livewireCall(baseUrl, page.updatePath, page.snapshot, {
    updates: { login, password },
    calls: [{ path: '', method: 'authenticate', params: [] }],
  });

  const success = !!(result.effects && result.effects.redirect);

  return { success, result };
}
