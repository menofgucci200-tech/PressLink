// Section 7 du plan de charge : fuzzing multi-tenant SOUS CHARGE.
//
// Pendant que le reste du trafic (stages/ramp.js) sature le serveur, ce
// script tente, avec de vrais identifiants d'un pressing, d'accéder aux
// ressources d'un AUTRE pressing (client, commande, service) — et vérifie
// que 100% de ces tentatives sont rejetées (403), même quand le serveur
// est sous forte charge (un bug de cache ou une race condition
// d'autorisation serait justement plus probable à révéler sous charge
// qu'au repos).
//
// setup() tourne UNE FOIS, avec des sessions admin légitimes sur chaque
// pressing, pour collecter de vrais IDs "ground truth" appartenant à
// chaque pressing (ce n'est pas du black-box : le test connaît déjà quels
// IDs appartiennent à qui, pour pouvoir vérifier le rejet avec certitude).

import { check, sleep } from 'k6';
import { Counter, Rate } from 'k6/metrics';
import { BASE_URL, LOADTEST_PASSWORD, PRESSING_COUNT, adminEmailForPressing, customerPhoneForVu } from '../lib/config.js';
import { livewireVisit, loginAsStaff } from '../lib/livewire.js';
import http from 'k6/http';

export const isolationViolations = new Counter('tenant_isolation_violations');
export const isolationBlockRate = new Rate('tenant_isolation_block_rate');

export const options = {
  noCookiesReset: true,
  vus: parseInt(__ENV.VUS || '10', 10),
  duration: __ENV.DURATION || '60s',
  thresholds: {
    // Le seuil qui compte vraiment pour ce script : ZÉRO violation
    // d'isolation, quel que soit le volume de tentatives.
    tenant_isolation_violations: ['count==0'],
    tenant_isolation_block_rate: ['rate==1'],
  },
};

function extractIds(html, pattern) {
  const ids = [];
  const re = new RegExp(pattern, 'g');
  let m;
  while ((m = re.exec(html)) !== null) {
    ids.push(parseInt(m[1], 10));
  }

  return [...new Set(ids)];
}

export function setup() {
  const perPressing = [];

  for (let p = 1; p <= PRESSING_COUNT; p++) {
    const email = adminEmailForPressing(p);
    const login = loginAsStaff(BASE_URL, email, LOADTEST_PASSWORD);

    if (!login.success) {
      continue;
    }

    const clients = livewireVisit(BASE_URL, '/clients');
    const orders = livewireVisit(BASE_URL, '/commandes');
    const services = livewireVisit(BASE_URL, '/tarifs');

    perPressing.push({
      pressingIndex: p,
      customerIds: extractIds(clients.html, '\\/clients\\/(\\d+)'),
      orderIds: extractIds(orders.html, '\\/commandes\\/(\\d+)'),
      serviceIds: extractIds(services.html, '\\/tarifs\\/(\\d+)\\/variantes'),
    });
  }

  return { perPressing };
}

function foreignDataset(data, myPressingIndex) {
  const candidates = data.perPressing.filter((p) => p.pressingIndex !== myPressingIndex);

  if (candidates.length === 0) {
    return null;
  }

  return candidates[Math.floor(Math.random() * candidates.length)];
}

let sessionReady = false;
let myPressingIndex = null;

export default function (data) {
  myPressingIndex = ((__VU - 1) % PRESSING_COUNT) + 1;

  if (!sessionReady) {
    const login = loginAsStaff(BASE_URL, adminEmailForPressing(myPressingIndex), LOADTEST_PASSWORD);
    sessionReady = login.success;

    if (!sessionReady) {
      return;
    }
  }

  const foreign = foreignDataset(data, myPressingIndex);

  if (!foreign) {
    sleep(1);

    return;
  }

  // --- Admin A → détail client B (pressing étranger) ---
  if (foreign.customerIds.length > 0) {
    const targetId = foreign.customerIds[Math.floor(Math.random() * foreign.customerIds.length)];
    const res = http.get(`${BASE_URL}/clients/${targetId}`, { tags: { name: 'fuzz_cross_tenant_customer' } });
    const blocked = res.status === 403;
    isolationBlockRate.add(blocked);
    if (!blocked) {
      isolationViolations.add(1);
    }
    check(res, { 'client étranger rejeté (403)': () => blocked });
  }

  // --- Admin A → détail commande B (pressing étranger) ---
  if (foreign.orderIds.length > 0) {
    const targetId = foreign.orderIds[Math.floor(Math.random() * foreign.orderIds.length)];
    const res = http.get(`${BASE_URL}/commandes/${targetId}`, { tags: { name: 'fuzz_cross_tenant_order' } });
    const blocked = res.status === 403;
    isolationBlockRate.add(blocked);
    if (!blocked) {
      isolationViolations.add(1);
    }
    check(res, { 'commande étrangère rejetée (403)': () => blocked });
  }

  // --- Admin A → variantes du service B (pressing étranger) ---
  if (foreign.serviceIds.length > 0) {
    const targetId = foreign.serviceIds[Math.floor(Math.random() * foreign.serviceIds.length)];
    const res = http.get(`${BASE_URL}/tarifs/${targetId}/variantes`, { tags: { name: 'fuzz_cross_tenant_service' } });
    const blocked = res.status === 403;
    isolationBlockRate.add(blocked);
    if (!blocked) {
      isolationViolations.add(1);
    }
    check(res, { 'service étranger rejeté (403)': () => blocked });
  }

  sleep(0.5);
}

// Pas de handleSummary() ici : le résumé texte par défaut de k6 suffit
// (seuils tenant_isolation_violations count==0 et block_rate rate==1
// apparaissent avec ✓/✗ dedans) — un handleSummary personnalisé
// remplacerait entièrement ce résumé standard.
