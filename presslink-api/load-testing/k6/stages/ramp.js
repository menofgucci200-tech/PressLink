// Script de montée en charge mixte — Section 4 du plan de tests.
//
// Un seul VU "joue" un profil tiré au hasard à chaque itération, avec une
// pondération qui reflète l'usage réel de PressLink (beaucoup plus de
// clients mobiles que de staff connecté en simultané) :
//   55% CLIENT, 25% EMPLOYÉ, 15% ADMIN, 5% SUPER ADMIN.
//
// Utilisation (voir load-testing/README.md pour le détail des paliers) :
//   k6 run -e BASE_URL=... -e TARGET_VUS=100 -e RAMP=30s -e HOLD=2m \
//     --summary-export=../results/stage-100.json stages/ramp.js
//
// TARGET_VUS est le seul paramètre à faire varier entre les paliers
// 10 → 50 → 100 → 250 → 500 → 1000 demandés dans le plan de charge.

import { sleep } from 'k6';
import { runClientProfile } from '../scenarios/client.js';
import { runEmployeeProfile } from '../scenarios/employee.js';
import { runAdminProfile } from '../scenarios/admin.js';
import { runSuperAdminProfile } from '../scenarios/super_admin.js';
import { COMMON_THRESHOLDS } from '../lib/config.js';

const TARGET_VUS = parseInt(__ENV.TARGET_VUS || '10', 10);
const RAMP = __ENV.RAMP || '30s';
const HOLD = __ENV.HOLD || '2m';
const RAMP_DOWN = __ENV.RAMP_DOWN || '20s';

export const options = {
  noCookiesReset: true,
  scenarios: {
    mixed_profiles: {
      executor: 'ramping-vus',
      startVUs: 0,
      stages: [
        { duration: RAMP, target: TARGET_VUS },
        { duration: HOLD, target: TARGET_VUS },
        { duration: RAMP_DOWN, target: 0 },
      ],
      gracefulRampDown: '10s',
    },
  },
  thresholds: COMMON_THRESHOLDS,
  // p50/p95/p99/max explicitement dans le résumé — le plan de charge
  // interdit de ne regarder que la moyenne.
  summaryTrendStats: ['avg', 'min', 'med', 'p(50)', 'p(90)', 'p(95)', 'p(99)', 'max'],
};

// Le profil est choisi UNE FOIS par VU (pas à chaque itération) : une VU
// représente une session/un navigateur simulé, et un même
// utilisateur ne change pas de rôle en cours de route. Chaque profil gère
// sa propre session (cookie jar partagé par VU) — les mélanger par
// itération ferait qu'un VU déjà connecté en tant qu'employé se re-présente
// à /login en étant déjà authentifié, ce qui casse le protocole Livewire.
let assignedProfile = null;

function profileForVu(vuId) {
  if (assignedProfile) {
    return assignedProfile;
  }

  // Répartition stable sur 20 VUs : 55% client, 25% employé, 15% admin, 5% super admin.
  const bucket = vuId % 20;

  if (bucket < 11) {
    assignedProfile = 'client';
  } else if (bucket < 16) {
    assignedProfile = 'employee';
  } else if (bucket < 19) {
    assignedProfile = 'admin';
  } else {
    assignedProfile = 'super_admin';
  }

  return assignedProfile;
}

export default function () {
  switch (profileForVu(__VU)) {
    case 'client':
      runClientProfile(__VU);
      break;
    case 'employee':
      runEmployeeProfile(__VU, __ITER);
      break;
    case 'admin':
      runAdminProfile(__VU);
      break;
    default:
      runSuperAdminProfile();
  }

  sleep(1);
}
