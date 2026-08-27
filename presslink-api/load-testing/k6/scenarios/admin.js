// Profil ADMIN (pressing) — pages Livewire (session web).
// Parcours : login, dashboard, clients, commandes, services, équipe,
// abonnement. Toutes ces vues sont des GET (pas d'action d'écriture
// demandée pour ce profil) — on mesure donc le coût serveur du rendu
// Livewire + requêtes DB sous charge.

import { sleep, check } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, LOADTEST_PASSWORD, pressingIndexForVu, adminEmailForPressing } from '../lib/config.js';
import { livewireVisit, loginAsStaff } from '../lib/livewire.js';

export const adminErrors = new Counter('admin_profile_errors');

let sessionReady = false;

function ensureLoggedIn(vuId) {
  if (sessionReady) {
    return true;
  }

  const email = adminEmailForPressing(pressingIndexForVu(vuId));
  const { success } = loginAsStaff(BASE_URL, email, LOADTEST_PASSWORD);

  if (!success) {
    adminErrors.add(1);
    return false;
  }

  sessionReady = true;

  return true;
}

function visit(path, tagName) {
  const page = livewireVisit(BASE_URL, path, { tags: { name: tagName } });
  const ok = check(page.res, { [`admin ${tagName} 200`]: (r) => r.status === 200 });

  if (!ok) {
    adminErrors.add(1);
  }

  return page;
}

export function runAdminProfile(vuId) {
  if (!ensureLoggedIn(vuId)) {
    return;
  }

  visit('/', 'admin_dashboard');
  sleep(0.3);

  visit('/clients', 'admin_clients');
  sleep(0.3);

  visit('/commandes', 'admin_orders');
  sleep(0.3);

  visit('/tarifs', 'admin_services');
  sleep(0.3);

  visit('/equipe', 'admin_team');
  sleep(0.3);

  visit('/abonnement', 'admin_subscription');
}

export const options = {
  vus: parseInt(__ENV.VUS || '10', 10),
  duration: __ENV.DURATION || '30s',
  noCookiesReset: true, // sinon la session Laravel saute à chaque itération
};

export default function () {
  runAdminProfile(__VU);
  sleep(1);
}
