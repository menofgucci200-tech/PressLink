// Configuration partagée par tous les scripts k6 de load-testing/.
// Toutes les valeurs sont surchargeables via des variables d'env k6
// (k6 run -e BASE_URL=... ou --env BASE_URL=...) pour pouvoir pointer vers
// un vrai environnement de staging sans modifier les scripts.

export const BASE_URL = __ENV.BASE_URL || 'http://127.0.0.1:8100';

// Identifiants du jeu de données généré par `php artisan loadtest:seed`
// (voir app/Console/Commands/LoadTest/SeedCommand.php). Ne JAMAIS pointer
// ces scripts vers une base contenant de vraies données de production.
export const LOADTEST_PASSWORD = 'loadtest-2026';
export const PRESSING_COUNT = parseInt(__ENV.PRESSING_COUNT || '5', 10);
export const CUSTOMERS_PER_PRESSING = parseInt(__ENV.CUSTOMERS_PER_PRESSING || '100', 10);
export const EMPLOYEES_PER_PRESSING = parseInt(__ENV.EMPLOYEES_PER_PRESSING || '4', 10);

// Répartit un identifiant de VU (1..N) sur 1..pressingCount de façon stable.
export function pressingIndexForVu(vuId) {
  return ((vuId - 1) % PRESSING_COUNT) + 1;
}

export function adminEmailForPressing(pressingIndex) {
  return `admin${pressingIndex}@loadtest.presslink.local`;
}

export function employeeEmailForVu(vuId) {
  const employeeNumber = ((vuId - 1) % (PRESSING_COUNT * EMPLOYEES_PER_PRESSING)) + 1;
  return `employee${employeeNumber}@loadtest.presslink.local`;
}

export const SUPER_ADMIN_EMAIL = 'superadmin@loadtest.presslink.local';

// Numéro de téléphone client généré par le seeder :
// +225 01 <pressing sur 2 chiffres> <n° client sur 4 chiffres>
export function customerPhoneForVu(vuId) {
  const pressingIndex = pressingIndexForVu(vuId);
  const customerNumber = ((vuId - 1) % CUSTOMERS_PER_PRESSING) + 1;
  return `+22501${String(pressingIndex).padStart(2, '0')}${String(customerNumber).padStart(4, '0')}`;
}

export const COMMON_THRESHOLDS = {
  http_req_failed: ['rate<0.01'],
  http_req_duration: ['p(95)<800', 'p(99)<1500'],
};
