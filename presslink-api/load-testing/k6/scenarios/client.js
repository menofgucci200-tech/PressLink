// Profil CLIENT (app mobile) — API Sanctum sous /api/v1.
// Parcours : login, profil, pressings, commandes, détail commande,
// notifications.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter } from 'k6/metrics';
import { BASE_URL, LOADTEST_PASSWORD, customerPhoneForVu } from '../lib/config.js';

export const clientErrors = new Counter('client_profile_errors');

// Un token par VU, mis en cache au niveau du module (chaque VU k6 a sa
// propre VM JS). Comme une vraie app mobile, on se connecte une fois puis on
// réutilise le token — et ça évite de cogner le throttle de connexion
// (`throttle:15,1`) qui, dans ce test, voit toutes les VUs partager la même
// IP source (contrairement à de vrais clients sur des réseaux différents).
let cachedToken = null;

function ensureLoggedIn(vuId) {
  if (cachedToken) {
    return cachedToken;
  }

  const phone = customerPhoneForVu(vuId);
  const loginRes = http.post(
    `${BASE_URL}/api/v1/auth/customer/login`,
    JSON.stringify({ phone, password: LOADTEST_PASSWORD }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, tags: { name: 'client_login' } }
  );

  const loginOk = check(loginRes, {
    'client login 200': (r) => r.status === 200,
    'client login renvoie un token': (r) => {
      try {
        return !!r.json('token');
      } catch (e) {
        return false;
      }
    },
  });

  if (!loginOk) {
    clientErrors.add(1);
    return null;
  }

  cachedToken = loginRes.json('token');

  return cachedToken;
}

export function runClientProfile(vuId) {
  const token = ensureLoggedIn(vuId);

  if (!token) {
    return;
  }

  const authHeaders = { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } };

  sleep(0.3);

  const profileRes = http.get(`${BASE_URL}/api/v1/auth/customer/me`, Object.assign({ tags: { name: 'client_profile' } }, authHeaders));
  check(profileRes, { 'client profil 200': (r) => r.status === 200 }) || clientErrors.add(1);

  sleep(0.2);

  const pressingsRes = http.get(`${BASE_URL}/api/v1/pressings/mine`, Object.assign({ tags: { name: 'client_pressings' } }, authHeaders));
  check(pressingsRes, { 'client pressings 200': (r) => r.status === 200 }) || clientErrors.add(1);

  sleep(0.2);

  const ordersRes = http.get(`${BASE_URL}/api/v1/orders`, Object.assign({ tags: { name: 'client_orders' } }, authHeaders));
  const ordersOk = check(ordersRes, { 'client commandes 200': (r) => r.status === 200 });
  if (!ordersOk) {
    clientErrors.add(1);
  }

  sleep(0.2);

  let orderId = null;
  if (ordersOk) {
    try {
      const data = ordersRes.json('data');
      if (data && data.length > 0) {
        orderId = data[0].id;
      }
    } catch (e) {
      // pas de commande pour ce client, on ignore
    }
  }

  if (orderId) {
    const orderRes = http.get(`${BASE_URL}/api/v1/orders/${orderId}`, Object.assign({ tags: { name: 'client_order_detail' } }, authHeaders));
    check(orderRes, { 'client détail commande 200': (r) => r.status === 200 }) || clientErrors.add(1);
    sleep(0.2);
  }

  const notifRes = http.get(`${BASE_URL}/api/v1/notifications`, Object.assign({ tags: { name: 'client_notifications' } }, authHeaders));
  check(notifRes, { 'client notifications 200': (r) => r.status === 200 }) || clientErrors.add(1);

  // Pas de logout ici : le token est mis en cache pour tout le cycle de vie
  // de la VU (voir ensureLoggedIn) et doit rester valide pour les
  // itérations suivantes, comme une session mobile réelle.
}

export const options = {
  vus: parseInt(__ENV.VUS || '10', 10),
  duration: __ENV.DURATION || '30s',
};

export default function () {
  runClientProfile(__VU);
  sleep(1);
}
