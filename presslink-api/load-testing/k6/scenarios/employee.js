// Profil EMPLOYÉ — pages Livewire (session web), pas d'API JSON dédiée.
// Parcours : login, dashboard, clients, commandes, recherche, création de
// commande, changement de statut.

import { sleep, check } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, LOADTEST_PASSWORD, employeeEmailForVu } from '../lib/config.js';
import { livewireVisit, livewireCall, loginAsStaff } from '../lib/livewire.js';

export const employeeErrors = new Counter('employee_profile_errors');

let sessionReady = false;

function ensureLoggedIn(vuId) {
  if (sessionReady) {
    return true;
  }

  const email = employeeEmailForVu(vuId);
  const { success } = loginAsStaff(BASE_URL, email, LOADTEST_PASSWORD);

  if (!success) {
    employeeErrors.add(1);
    return false;
  }

  sessionReady = true;

  return true;
}

/** Génère un numéro ivoirien valide et unique pour ce client "walk-in". */
function uniqueWalkInPhone(vuId, iter) {
  const vuPart = String(vuId % 1000).padStart(3, '0');
  const iterPart = String(iter % 1000000).padStart(6, '0');

  return `+2250${vuPart}${iterPart}`;
}

export function runEmployeeProfile(vuId, iter) {
  if (!ensureLoggedIn(vuId)) {
    return;
  }

  const dashboard = livewireVisit(BASE_URL, '/', { tags: { name: 'employee_dashboard' } });
  check(dashboard.res, { 'employé dashboard 200': (r) => r.status === 200 }) || employeeErrors.add(1);
  sleep(0.3);

  const clients = livewireVisit(BASE_URL, '/clients', { tags: { name: 'employee_clients' } });
  check(clients.res, { 'employé clients 200': (r) => r.status === 200 }) || employeeErrors.add(1);
  sleep(0.3);

  const orders = livewireVisit(BASE_URL, '/commandes', { tags: { name: 'employee_orders' } });
  const ordersOk = check(orders.res, { 'employé commandes 200': (r) => r.status === 200 });
  if (!ordersOk) {
    employeeErrors.add(1);
  }
  sleep(0.2);

  if (ordersOk && orders.snapshot) {
    const searchResult = livewireCall(BASE_URL, orders.updatePath, orders.snapshot, {
      updates: { search: 'PL-' },
    }, { tags: { name: 'employee_orders_search' } });
    check(searchResult.res, { 'employé recherche commandes 200': (r) => r.status === 200 }) || employeeErrors.add(1);
  }

  sleep(0.3);

  // --- Création de commande (wizard Livewire à 4 étapes) ---
  const createPage = livewireVisit(BASE_URL, '/commandes/nouvelle', { tags: { name: 'employee_order_create_page' } });
  const createPageOk = check(createPage.res, { 'employé page création 200': (r) => r.status === 200 });

  if (!createPageOk || !createPage.snapshot) {
    employeeErrors.add(1);
    return;
  }

  const phone = uniqueWalkInPhone(vuId, iter);

  const afterPickCustomer = livewireCall(BASE_URL, createPage.updatePath, createPage.snapshot, {
    updates: {
      newFirstName: 'Client',
      newLastName: `Charge${vuId}`,
      newPhone: phone,
    },
    calls: [{ path: '', method: 'createAndPickCustomer', params: [] }],
  }, { tags: { name: 'employee_order_pick_customer' } });

  if (!afterPickCustomer.snapshot) {
    employeeErrors.add(1);
    return;
  }

  // Deux allers-retours distincts : le composant a un hook Livewire
  // `updatedPickerService()` qui réinitialise pickerCustomName/Price dès que
  // `pickerService` change. En les envoyant dans le même batch que
  // `addPickedItem`, ce hook efface nos valeurs avant validation. On fixe
  // donc `pickerService` d'abord, puis les champs custom + l'appel dans un
  // second temps.
  const afterPickerService = livewireCall(BASE_URL, createPage.updatePath, afterPickCustomer.snapshot, {
    updates: { pickerService: 'other' },
  }, { tags: { name: 'employee_order_pick_service' } });

  if (!afterPickerService.snapshot) {
    employeeErrors.add(1);
    return;
  }

  const afterAddItem = livewireCall(BASE_URL, createPage.updatePath, afterPickerService.snapshot, {
    updates: {
      pickerCustomName: 'Article test de charge',
      pickerCustomPrice: '1000',
      pickerQuantity: 1,
    },
    calls: [{ path: '', method: 'addPickedItem', params: [] }],
  }, { tags: { name: 'employee_order_add_item' } });

  if (!afterAddItem.snapshot) {
    employeeErrors.add(1);
    return;
  }

  const created = livewireCall(BASE_URL, createPage.updatePath, afterAddItem.snapshot, {
    calls: [{ path: '', method: 'create', params: [] }],
  }, { tags: { name: 'employee_order_create_submit' } });

  const createdOk = check(created, {
    'employé création commande redirige': (r) => !!(r.effects && r.effects.redirect),
  });

  if (!createdOk) {
    employeeErrors.add(1);
    return;
  }

  sleep(0.3);

  // --- Changement de statut sur la commande qu'on vient de créer ---
  // effects.redirect peut être une URL absolue ou un chemin relatif selon
  // la version de Livewire — on normalise en chemin relatif à BASE_URL.
  const orderPath = created.effects.redirect.replace(BASE_URL, '');
  const showPage = livewireVisit(BASE_URL, orderPath, { tags: { name: 'employee_order_show' } });
  const showOk = check(showPage.res, { 'employé détail commande 200': (r) => r.status === 200 });

  if (!showOk || !showPage.snapshot) {
    employeeErrors.add(1);
    return;
  }

  const transitioned = livewireCall(BASE_URL, showPage.updatePath, showPage.snapshot, {
    calls: [{ path: '', method: 'transitionTo', params: ['traitement'] }],
  }, { tags: { name: 'employee_order_transition' } });

  check(transitioned.res, { 'employé changement statut 200': (r) => r.status === 200 }) || employeeErrors.add(1);
}

export const options = {
  vus: parseInt(__ENV.VUS || '10', 10),
  duration: __ENV.DURATION || '30s',
  // Indispensable : sans ça, k6 vide le cookie jar à chaque itération, ce
  // qui casse la session Laravel dès la 2e itération d'une même VU (repéré
  // en debug — voir load-testing/README.md).
  noCookiesReset: true,
};

export default function () {
  runEmployeeProfile(__VU, __ITER);
  sleep(1);
}
