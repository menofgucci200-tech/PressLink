// Profil SUPER ADMIN (back-office plateforme) — pages Livewire sous /admin.
// Parcours : dashboard global, liste des pressings, détail d'un pressing
// (clients + commandes de ce pressing, seule vue "plateforme" exposée par
// l'app pour un pressing donné — il n'existe pas de listing global unique
// tous-pressings-confondus dans le MVP).

import { sleep, check } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, LOADTEST_PASSWORD, SUPER_ADMIN_EMAIL } from '../lib/config.js';
import { livewireVisit, loginAsStaff } from '../lib/livewire.js';

export const superAdminErrors = new Counter('super_admin_profile_errors');

let sessionReady = false;

function ensureLoggedIn() {
  if (sessionReady) {
    return true;
  }

  const { success } = loginAsStaff(BASE_URL, SUPER_ADMIN_EMAIL, LOADTEST_PASSWORD);

  if (!success) {
    superAdminErrors.add(1);
    return false;
  }

  sessionReady = true;

  return true;
}

export function runSuperAdminProfile() {
  if (!ensureLoggedIn()) {
    return;
  }

  const dashboard = livewireVisit(BASE_URL, '/admin', { tags: { name: 'super_admin_dashboard' } });
  check(dashboard.res, { 'super admin dashboard 200': (r) => r.status === 200 }) || superAdminErrors.add(1);
  sleep(0.3);

  const pressings = livewireVisit(BASE_URL, '/admin/pressings', { tags: { name: 'super_admin_pressings' } });
  const pressingsOk = check(pressings.res, { 'super admin pressings 200': (r) => r.status === 200 });

  if (!pressingsOk) {
    superAdminErrors.add(1);
    return;
  }

  sleep(0.3);

  const ids = [];
  const re = /\/admin\/pressings\/(\d+)/g;
  let match;
  while ((match = re.exec(pressings.html)) !== null) {
    ids.push(match[1]);
  }

  if (ids.length === 0) {
    return;
  }

  const pickedId = ids[Math.floor(Math.random() * ids.length)];
  const detail = livewireVisit(BASE_URL, `/admin/pressings/${pickedId}`, { tags: { name: 'super_admin_pressing_detail' } });
  check(detail.res, { 'super admin détail pressing 200': (r) => r.status === 200 }) || superAdminErrors.add(1);
}

export const options = {
  vus: parseInt(__ENV.VUS || '5', 10),
  duration: __ENV.DURATION || '30s',
  noCookiesReset: true, // sinon la session Laravel saute à chaque itération
};

export default function () {
  runSuperAdminProfile();
  sleep(1);
}
