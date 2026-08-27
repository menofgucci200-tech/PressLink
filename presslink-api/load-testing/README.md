# Tests de charge & simulation d'utilisateurs — PressLink

Ce dossier contient la stratégie de tests de charge de la Phase 8 : scripts
k6 (API/concurrence), tests de concurrence métier PHPUnit (transactions/
verrous réels), et le rapport final (`RAPPORT.md`).

**Contrainte respectée sur tout ce dossier : aucune fonctionnalité produit
n'a été ajoutée.** Tout ce qui suit est de l'outillage de test (commandes
Artisan `loadtest:*`, scripts k6, tests PHPUnit) — zéro changement dans
`app/` en dehors de `app/Console/Commands/LoadTest/`.

## 1. Outils choisis, et pourquoi

| Outil | Usage | Pourquoi |
|---|---|---|
| **k6** | Montée en charge API/pages, profils utilisateurs, fuzzing sécurité sous charge | Scriptable en JS, léger, métriques p50/p95/p99 natives, pas de navigateur — adapté à du vrai load-testing HTTP |
| **PHPUnit** (suite dédiée `tests/Concurrency/`) | Concurrence métier fine (transactions, verrous, contraintes BDD) | Accès direct à Eloquent/DB pour poser les fixtures et vérifier l'état final ; `Http::pool()` pour de vraies requêtes HTTP parallèles |
| **Laravel Dusk** | ❌ Non utilisé | Un navigateur réel par VU ne scale pas pour du load-testing et aurait rendu les scripts fragiles (sélecteurs CSS, timing) — exactement ce que la consigne demandait d'éviter. Les parcours Livewire sont pilotés en HTTP direct (voir §3). |

## 2. Serveur utilisé pour les tests — limite importante de cet environnement

Ce conteneur n'a pas d'accès à `php-fpm` (seul package disponible via le
PPA `ondrej/php`, bloqué par la politique réseau du sandbox — `php8.4-cli`
est pré-installé mais pas `php8.4-fpm`). `nginx` seul ne sert à rien sans
un moteur PHP en face.

**Solution retenue** : le serveur de développement PHP intégré, avec la
variable `PHP_CLI_SERVER_WORKERS` (native depuis PHP 7.4) qui fait tourner
plusieurs workers PHP en parallèle au lieu d'un seul process séquentiel :

```bash
PHP_CLI_SERVER_WORKERS=8 php artisan serve --env=loadtest --host=127.0.0.1 --port=8100
```

Vérifié manuellement avant utilisation (4 requêtes de 0.5s chacune,
envoyées en parallèle, traitées par 4 PID distincts en ~0.5s au lieu de
2s séquentiel — voir historique de session).

**⚠️ Sur votre infrastructure réelle (staging/prod), utilisez php-fpm +
nginx (ou équivalent) et ré-exécutez au minimum les paliers 250/500/1000 —
les résultats de ce rapport avec le serveur intégré PHP ne sont PAS
directement transposables à une stack de production.** Un des findings de
ce rapport (voir RAPPORT.md, §4) montre d'ailleurs que le nombre de workers
PHP (4 vs 16 testés ici) n'explique PAS la dégradation observée — mais
php-fpm reste le seul moteur qualifié pour du capacity-planning fiable.

## 3. Comment les parcours Livewire sont pilotés en HTTP pur

Les pages EMPLOYÉ/ADMIN/SUPER ADMIN sont des composants Livewire — la
connexion elle-même (`App\Livewire\Auth\Login::authenticate()`), la
création de commande et le changement de statut passent par le protocole
AJAX de Livewire, pas par des routes POST classiques.

`k6/lib/livewire.js` (JS) et `tests/Concurrency/Support/LivewireSession.php`
(PHP) réimplémentent ce protocole, vérifié manuellement par `curl` avant
d'être encodé :

1. `GET` la page → on y récupère le `wire:snapshot` du composant racine
   (attribut HTML, à dé-échapper) et le préfixe d'URL de mise à jour
   (`/livewire-xxxxxxxx/update`, présent dans l'URL du script Livewire).
2. `POST` ce préfixe avec un body
   `{ components: [{ snapshot, updates, calls }] }`, authentifié par le
   cookie de session + le header `X-XSRF-TOKEN` (cookie XSRF-TOKEN
   url-décodé — double-submit cookie de Laravel), et `X-Livewire: true`.
3. La réponse contient un nouveau `snapshot` (état réactualisé, à
   réutiliser pour l'appel suivant) et des `effects` (ex.
   `{ redirect: '/commandes/123' }`).

**Piège important rencontré et corrigé** : k6 **réinitialise son cookie
jar à chaque itération par défaut**. Sans `noCookiesReset: true` dans
`options`, la session Laravel saute dès la 2e itération d'une VU (chaque
script de `k6/scenarios/` et `k6/stages/ramp.js` a cette option).

## 4. Structure de ce dossier

```
load-testing/
├── README.md                 ce fichier
├── RAPPORT.md                rapport final (résultats, findings, seuils)
├── monitor.sh                échantillonne CPU/RAM PHP + connexions/slow queries MySQL pendant un run k6
├── results/                  résultats des paliers réellement exécutés dans ce conteneur
│   ├── stage-{10,50,100}.json         exports k6 --summary-export
│   ├── stage-100-w16.json             stage 100 VUs avec 16 workers PHP (comparaison)
│   ├── monitor-stage-*.csv            CPU/RAM/MySQL échantillonnés pendant chaque palier
│   └── notifications-flood.txt        résultats Section 6 (100/500/1000 commandes → Prête)
└── k6/
    ├── lib/
    │   ├── config.js          URLs, identifiants du jeu de charge, répartition VU→pressing
    │   └── livewire.js        client Livewire "maison" (voir §3)
    ├── scenarios/
    │   ├── client.js          profil CLIENT (API mobile Sanctum)
    │   ├── employee.js        profil EMPLOYÉ (Livewire : dashboard, clients, commandes, recherche, création, statut)
    │   ├── admin.js           profil ADMIN (Livewire, lecture seule : dashboard/clients/commandes/services/équipe/abonnement)
    │   └── super_admin.js     profil SUPER ADMIN (back-office /admin)
    ├── stages/
    │   └── ramp.js            montée en charge mixte (10→1000 VUs), pondération réaliste des profils
    └── security/
        └── tenant_fuzzing.js  fuzzing multi-tenant SOUS CHARGE (Section 7)

app/Console/Commands/LoadTest/
├── SeedCommand.php               php artisan loadtest:seed
├── PurgeCommand.php              php artisan loadtest:purge
└── NotificationsFloodCommand.php php artisan loadtest:notifications-flood

tests/Concurrency/                tests de concurrence métier (Section 5) — voir §6 ci-dessous
phpunit.concurrency.xml           config PHPUnit dédiée (base MySQL réelle, pas de RefreshDatabase)
.env.loadtest                     config d'environnement dédiée (MySQL presslink_loadtest)
```

## 5. Reproduire ces tests

### 5.1. Préparer l'environnement (une fois)

```bash
# MySQL dédié (jamais la base de dev/prod)
mysql -u root -e "CREATE DATABASE IF NOT EXISTS presslink_loadtest;
  CREATE USER IF NOT EXISTS 'loadtest'@'localhost' IDENTIFIED BY 'loadtest_pw_2026';
  GRANT ALL PRIVILEGES ON presslink_loadtest.* TO 'loadtest'@'localhost';"

cd presslink-api
php artisan migrate:fresh --env=loadtest --force
php artisan loadtest:seed --env=loadtest --pressings=5 --employees-per-pressing=4 --customers=500
```

Le jeu de données créé (voir `app/Console/Commands/LoadTest/SeedCommand.php`) :
5 pressings ("LT Pressing N"), 5 admins + 20 employés
(`admin{N}@loadtest.presslink.local` / `employee{N}@loadtest.presslink.local`,
mot de passe commun `loadtest-2026`), 1 super admin
(`superadmin@loadtest.presslink.local`), 500 clients (téléphone
`+225 01 <pressing 2 chiffres> <n° 4 chiffres>`), services + variantes par
pressing, ~1250 commandes réparties sur tout le workflow de statuts avec
historique et notifications générées naturellement (via les vraies Actions
de l'app, pas de fixtures à la main). **Isolation multi-tenant stricte** :
chaque commande passe par `CreateOrderAction` (mêmes garde-fous que la prod).
**Identifiable** : base MySQL dédiée + tout est préfixé/domainé
`LT ...` / `@loadtest.presslink.local`.

Pour repartir de zéro : `php artisan loadtest:seed --env=loadtest --fresh`
ou `php artisan loadtest:purge --env=loadtest`.

### 5.2. Démarrer le serveur cible

```bash
PHP_CLI_SERVER_WORKERS=8 php artisan serve --env=loadtest --host=127.0.0.1 --port=8100
```

### 5.3. Scripts k6 par profil (validation rapide, avant montée en charge)

```bash
cd load-testing/k6
k6 run -e BASE_URL=http://127.0.0.1:8100 --vus 3 --duration 20s scenarios/client.js
k6 run -e BASE_URL=http://127.0.0.1:8100 --vus 3 --duration 20s scenarios/employee.js
k6 run -e BASE_URL=http://127.0.0.1:8100 --vus 3 --duration 20s scenarios/admin.js
k6 run -e BASE_URL=http://127.0.0.1:8100 --vus 2 --duration 20s scenarios/super_admin.js
```

### 5.4. Montée en charge par paliers (Section 4)

**Lancer le monitoring AVANT k6, dans un terminal séparé** :

```bash
./load-testing/monitor.sh load-testing/results/monitor-stage-<N>.csv 2
```

Puis, pour chaque palier — **analyser les résultats avant de passer au
suivant**, comme demandé :

```bash
cd load-testing/k6
k6 run -e BASE_URL=http://127.0.0.1:8100 -e TARGET_VUS=10  -e RAMP=15s -e HOLD=60s -e RAMP_DOWN=10s --summary-export=../results/stage-10.json   stages/ramp.js
k6 run -e BASE_URL=http://127.0.0.1:8100 -e TARGET_VUS=50  -e RAMP=20s -e HOLD=60s -e RAMP_DOWN=10s --summary-export=../results/stage-50.json   stages/ramp.js
k6 run -e BASE_URL=http://127.0.0.1:8100 -e TARGET_VUS=100 -e RAMP=25s -e HOLD=60s -e RAMP_DOWN=15s --summary-export=../results/stage-100.json  stages/ramp.js
# 250/500/1000 : mêmes commandes, TARGET_VUS=250|500|1000, sur VOTRE
# infra réelle (php-fpm+nginx) — pas dans ce conteneur, voir RAPPORT.md §0.
k6 run -e BASE_URL=<votre-staging> -e TARGET_VUS=250  -e RAMP=30s -e HOLD=3m -e RAMP_DOWN=20s --summary-export=../results/stage-250.json  stages/ramp.js
k6 run -e BASE_URL=<votre-staging> -e TARGET_VUS=500  -e RAMP=45s -e HOLD=3m -e RAMP_DOWN=30s --summary-export=../results/stage-500.json  stages/ramp.js
k6 run -e BASE_URL=<votre-staging> -e TARGET_VUS=1000 -e RAMP=60s -e HOLD=3m -e RAMP_DOWN=30s --summary-export=../results/stage-1000.json stages/ramp.js
```

Toujours regarder **p50/p95/p99/max**, jamais seulement la moyenne — le
résumé k6 les affiche tous (`summaryTrendStats` dans `stages/ramp.js`).

### 5.5. Tests de concurrence métier (Section 5)

Nécessite le serveur de charge démarré (§5.2) :

```bash
cd presslink-api
vendor/bin/phpunit -c phpunit.concurrency.xml
```

6 tests, chacun avec de VRAIES requêtes HTTP concurrentes
(`Http::pool()`) contre le serveur en cours d'exécution :

- `OrderCreationConcurrencyTest` — 2 employés créent une commande en même temps
- `OrderStatusConcurrencyTest` — 2 changements de statut concurrents sur la même commande
- `SubscriptionQuotaConcurrencyTest` — 2 créations pour le dernier slot de quota
- `PressingJoinConcurrencyTest` — le même client rejoint 2x le même pressing en même temps
- `PressingSuspensionTest` (2 tests) — suspension d'un pressing pendant/avant une création de commande

Voir RAPPORT.md pour les constats de chaque test (certains documentent un
comportement actuel imparfait, sans le corriger — hors périmètre de cette
tâche).

### 5.6. Notifications en rafale (Section 6)

```bash
php artisan loadtest:notifications-flood --env=loadtest --count=100
php artisan loadtest:notifications-flood --env=loadtest --count=500
php artisan loadtest:notifications-flood --env=loadtest --count=1000
```

Fait passer N commandes du pressing de charge à "Prête" et mesure le
temps réel + l'activité de la table `jobs` (voir RAPPORT.md — 0 job créé,
finding central de cette section). **N'envoie jamais de vraie
notification** : `FcmChannel` se dégrade en no-op loggué dès qu'aucun
`fcm_token` n'est enregistré (le cas de tout le jeu de charge) ou que
Firebase n'est pas configuré.

### 5.7. Fuzzing multi-tenant sous charge (Section 7)

À lancer **en parallèle** d'un palier de `stages/ramp.js` (deux processus
k6 distincts, même serveur) pour tester l'isolation *sous* charge :

```bash
k6 run -e BASE_URL=http://127.0.0.1:8100 --vus 10 --duration 60s security/tenant_fuzzing.js
```

Seuils : `tenant_isolation_violations: count==0` et
`tenant_isolation_block_rate: rate==1` — toute violation fait échouer le
script explicitement.

## 6. Nettoyage

```bash
php artisan loadtest:purge --env=loadtest --force
```

Supprime uniquement les entités `LT ...` / `@loadtest.presslink.local` —
n'affecte jamais une base autre que celle contenant `loadtest` dans son
nom (garde de sécurité dans `SeedCommand`/`PurgeCommand`).
