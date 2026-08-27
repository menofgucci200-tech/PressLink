# Rapport de tests de charge — PressLink (Phase 8)

**Date** : 27/08/2026
**Périmètre** : simulation d'utilisateurs réalistes (client mobile, employé,
admin, super admin) + tests de concurrence métier + fuzzing multi-tenant
sous charge, sur Laravel 13 + MySQL 8 + FCM (mock).
**Contrainte respectée** : aucune fonctionnalité produit ajoutée. Les
findings ci-dessous sont des CONSTATS, pas des corrections — sauf mention
contraire, rien dans `app/` n'a été modifié pour produire ce rapport.

---

## Addendum (27/08/2026) — 4 findings corrigés avant mise en production

Suite à ce rapport, les 4 findings jugés bloquants pour un déploiement
public ont été corrigés (findings A, B, C, D ci-dessous — désormais
marqués 🟢 CORRIGÉ). Les findings E et F (FAIBLE) n'ont pas été traités,
conformément à leur priorité. Résumé des correctifs, chacun revalidé par
les tests de concurrence (`vendor/bin/phpunit -c phpunit.concurrency.xml`,
6/6 sur plusieurs runs) et la suite standard (`php artisan test`, 106/106) :

- **A — Notifications synchrones** : `OrderNotification` implémente
  désormais `ShouldQueueAfterCommit` (pas juste `ShouldQueue` — voir note
  dans le fichier : ces notifications sont dispatchées DEPUIS
  `OrderObserver`, DANS la transaction de `CreateOrderAction`, et
  `after_commit` vaut `false` par défaut pour la queue `database` ; un
  simple `ShouldQueue` pousse le job avant que la commande ne soit
  committée — repéré en testant le correctif en conditions réelles avec
  `queue:work`, qui a d'abord produit des `ModelNotFoundException` sur des
  commandes non encore committées). Revalidé : `loadtest:notifications-flood
  --count=50` puis `queue:work --stop-when-empty` → 100 jobs créés, 0 échec,
  50 notifications correctement écrites en base.
- **B — Suspension de pressing** : `CreateOrderAction::handle()` refuse
  désormais toute création si `$pressing->status !== PressingStatus::Active`.
- **C — Rate limiting** : `/me` et `/logout` sortis du throttle partagé
  `15,1` avec `login`/`register`/`check-phone` ; nouveau throttle dédié
  `120,1` (authentifié, moins sensible). Revalidé manuellement : 30 appels
  consécutifs à `/me` avec un token valide → 30× `200`, 0× `429`.
- **D — Lost update sur changement de statut** : `Orders\Show::transitionTo()`
  prend désormais un verrou pessimiste (`lockForUpdate()`) et relit le
  statut sous ce verrou avant de valider la transition.

Détail des diagnostics originaux ci-dessous (§4), conservés tels quels
pour la traçabilité — chaque finding corrigé porte maintenant une note
🟢 renvoyant à cet addendum.

---

## 0. Environnement de test et sa limite principale

Tout a tourné **dans ce conteneur** (MySQL 8.0.46 dédié `presslink_loadtest`,
k6 v1.8.1, aucun accès réseau externe) — voir `README.md` pour le détail.

**Point capital à garder en tête pour toute la suite du rapport** : ce
conteneur n'a pas `php-fpm` disponible (PPA bloqué par la politique
réseau). Le serveur cible est donc le serveur PHP intégré
(`php artisan serve`) avec `PHP_CLI_SERVER_WORKERS` pour la concurrence —
**pas une stack de production**. Les paliers 10/50/100 ont été exécutés
réellement ici ; les paliers 250/500/1000 sont fournis en scripts prêts à
l'emploi mais **doivent être exécutés sur une vraie infra (php-fpm+nginx)**
avant toute décision de capacité définitive (voir §6).

---

## 1. Critères de réussite définis AVANT les tests

Seuils proposés pour un MVP sur l'infrastructure cible réelle
(php-fpm/nginx — pas le serveur de dev de ce conteneur, structurellement
plus lent). Faute d'historique de production PressLink pour calibrer des
seuils "vécus", ces valeurs sont des références standard MVP SaaS
raisonnables, pas des mesures — à ajuster après les paliers 250-1000 sur
votre infra réelle.

| Critère | Seuil proposé | Justification |
|---|---|---|
| Taux d'erreur HTTP (hors 4xx métier attendus) | < 1 % | Standard MVP ; au-delà, l'expérience utilisateur se dégrade visiblement |
| p95 pages de lecture (dashboard, listes) | < 500 ms | Perçu comme "réactif" par un utilisateur |
| p95 actions d'écriture (créer commande, changer statut) | < 800 ms | Marge pour transaction DB + notification synchrone (voir finding A) |
| p99 (toutes routes) | < 1.5 s | Tolérance large pour les requêtes atypiques (recherche, exports) |
| Isolation multi-tenant | **0 violation, non négociable** | RB-01 — un incident = faille de sécurité, pas une question de charge |
| Commandes perdues/dupliquées sous concurrence | **0** | Intégrité des données commerciales du pressing |
| Dépassement de quota d'abonnement | **0** | Impact facturation/business model |
| Notification envoyée au mauvais pressing/client | **0** | RB-01 appliqué aux notifications |
| Corruption de données (items, historique de statut incohérent) | **0** | Intégrité de l'audit trail |

---

## 2. Résultats mesurés — montée en charge (Section 4)

Profils mélangés (`stages/ramp.js`) : 55 % client, 25 % employé, 15 %
admin, 5 % super admin. `p50/p95/p99/max` toujours regardés — jamais
seulement la moyenne.

| Charge (VUs) | RPS | p50 | p95 | p99 | max | Erreurs | CPU (moy/max) | RAM PHP | Connexions MySQL |
|---|---|---|---|---|---|---|---|---|---|
| 10 | 19.1 | 28 ms | **52 ms** | 69 ms | 140 ms | 7.4 %¹ | 4.6 % / 5.4 % | 147 MB | ≤ 2 |
| 50 | 30.0 | 1050 ms | **1367 ms** | 1440 ms | 1549 ms | 14.4 %¹ | 6.7 % / 8.1 % | 148 MB | ≤ 2 |
| 100 (4 workers) | 31.7 | 2285 ms | **2826 ms** | 2921 ms | 3032 ms | 26.9 %¹ | 9.3 % / 10.7 % | 150 MB | ≤ 2 |
| 100 (16 workers, comparaison) | 30.7 | 2499 ms | **2801 ms** | 2897 ms | 2961 ms | 23.7 %¹ | 52.1 % / 63.8 % | 147 MB | ≤ 2 |
| 250 / 500 / 1000 | — | — | — | — | — | — | — | — | — |

¹ **Quasi-totalité des "erreurs" = 429 (rate limit) sur les tentatives de
connexion CLIENT, pas des 500 ni des erreurs applicatives** — voir finding
C (§4). Aucune 500 n'a été observée sur l'ensemble des 3 paliers.

**250/500/1000** : non exécutés dans ce conteneur (voir §0). Scripts prêts
(`k6/stages/ramp.js -e TARGET_VUS=250|500|1000`), à lancer sur staging/prod
réel — analyser chaque palier avant de passer au suivant, comme prévu.

### Premier point de saturation identifié

**Aucune saturation CPU ni MySQL n'a été observée jusqu'à 100 VUs** dans
cet environnement : le CPU des workers PHP reste sous 11 % (4 workers) et
MySQL sous 2 connexions simultanées, alors même que la latence p95 explose
(52 ms → 2.8 s). C'est un signal important : **la dégradation n'est pas
une saturation de ressources serveur classique**.

Preuve à l'appui : passer de 4 à 16 workers PHP fait *monter le CPU* (9 %
→ 52 % en moyenne, jusqu'à 64 % en pic) **sans améliorer ni le débit
(31.7 → 30.7 req/s) ni la latence (p95 2.83s → 2.80s)** — ce qui exclut le
nombre de workers comme cause de la dégradation.

La cause la plus probable, corroborée par les données : le rate limiting
par IP sur les routes d'authentification client (finding C) réduit
artificiellement le volume de trafic CLIENT effectivement traité à mesure
que la charge augmente (plus de VUs client = plus de 429 = moins de
requêtes utiles servies), ce qui *masque* le vrai comportement du serveur
sous charge réelle. **Le "point de saturation" mesuré ici (~30 req/s) est
donc un artefact de test partiellement expliqué par ce rate limiting, pas
une preuve de la capacité réelle de l'infrastructure Laravel+MySQL.**

**Recommandation prioritaire avant tout capacity planning** : ré-exécuter
les paliers sur une infra php-fpm+nginx réelle, en testant depuis
plusieurs IPs sources (ou avec le throttle client temporairement desserré
côté staging pour le test) afin d'isoler l'effet du rate limiting de la
capacité réelle du serveur applicatif.

---

## 3. Résultats — notifications en rafale (Section 6)

`php artisan loadtest:notifications-flood --count={100,500,1000}` — bascule
N commandes en "Prête" (déclenche `OrderReadyNotification` pour chacune),
jamais de vraie notification envoyée (voir README §5.6).

| Commandes | Temps total | Temps moyen/commande | Notifications DB créées | Jobs créés dans `jobs` |
|---|---|---|---|---|
| 100 | 0.63 s | 6.31 ms | 100 | **0** |
| 500 | 3.31 s | 6.61 ms | 500 | **0** |
| 1000 | 5.98 s | 5.98 ms | 1000 | **0** |

Voir finding A (§4) : `OrderNotification` n'implémente pas `ShouldQueue`,
malgré `QUEUE_CONNECTION=database` et une table `jobs` opérationnelle — le
coût de notification (~6 ms/commande, mesuré ici en boucle interne, mais
qui s'ajoute intégralement à CHAQUE requête HTTP individuelle en usage
réel) est payé de façon synchrone, dans la requête de l'utilisateur qui
déclenche le changement de statut.

**FCM non testable dans cet environnement** : Firebase n'est pas configuré
et aucun client de charge n'a de `fcm_token` — 100 % des envois se
dégradent en no-op loggué. Le comportement réel de FCM sous charge
(latence API Google, taux d'échec, comportement du retry sur token
invalide — code déjà présent dans `FcmChannel::send()`) nécessite un vrai
environnement de staging avec des identifiants Firebase sandbox.

---

## 4. Findings — classés par sévérité

### 🔴 ÉLEVÉ

**A. Notifications synchrones — pas de `ShouldQueue`** — 🟢 CORRIGÉ (voir addendum en tête de rapport)
- **Où** : `app/Notifications/OrderNotification.php`, déclenché depuis `app/Observers/OrderObserver.php`
- **Cause probable** : la classe de base des notifications de commande n'implémente pas `Illuminate\Contracts\Queue\ShouldQueue`, alors que `QUEUE_CONNECTION=database` est configuré et que la table `jobs` existe et fonctionne.
- **Preuve** : `loadtest:notifications-flood` (§3) — 0 job créé dans `jobs` sur 1600 transitions de statut testées ; toutes les notifications (DB + tentative FCM) s'exécutent dans le thread de la requête HTTP.
- **Impact** : chaque `create()` de commande et chaque changement de statut paie le coût de notification (DB + appel FCM potentiel) de façon synchrone. Sous charge réelle (ex. réouverture de service, plusieurs employés marquent des commandes prêtes en rafale), ce coût s'additionne pleinement à la latence perçue, sans possibilité d'absorption par des workers de queue.
- **Recommandation** : `class OrderNotification extends Notification implements ShouldQueue` + `use Queueable;`, puis `php artisan queue:work` en production. Changement à 2 lignes, infrastructure déjà en place — **non appliqué ici** conformément à la consigne de ne pas ajouter/modifier de fonctionnalité produit dans cette tâche.

**B. Suspension d'un pressing non appliquée côté staff** — 🟢 CORRIGÉ (voir addendum en tête de rapport)
- **Où** : `app/Actions/Orders/CreateOrderAction.php` (et plus largement, aucune route/middleware staff ne vérifie `PressingStatus`)
- **Cause probable** : `PressingStatus::Suspended` n'est consommé que par l'affichage du back-office Super Admin (`app/Livewire/Admin/Pressings/{Index,Show}.php`) — jamais comme garde métier.
- **Preuve** : `tests/Concurrency/PressingSuspensionTest.php` — une commande est créée avec succès pour un pressing explicitement suspendu, **sans même nécessiter de concurrence** pour le révéler (le test "seul" suffit ; la variante concurrente confirme juste qu'une suspension simultanée ne change rien).
- **Impact** : suspendre un pressing (ex. impayé, comportement abusif) n'empêche en rien son staff de continuer à utiliser l'application normalement.
- **Recommandation** : vérifier `$pressing->status === PressingStatus::Active` dans `CreateOrderAction::handle()`, et envisager un middleware bloquant plus largement l'accès aux routes staff (`/`, `/commandes`, etc.) pour un pressing suspendu.

### 🟠 MOYEN

**C. Rate limiting par IP trop large sur les routes client, y compris `/me`** — 🟢 CORRIGÉ (voir addendum en tête de rapport)
- **Où** : `routes/api.php`, groupe `Route::prefix('auth/customer')->middleware('throttle:15,1')`
- **Cause probable** : `/me` (lecture de profil, appelée fréquemment par une app mobile légitimement connectée) partage le même throttle IP-scoped que `/login`/`/register` (actions rares et sensibles).
- **Preuve** : palier 10 VUs → 38 % de `client profil 200` en échec (429) ; palier 50 VUs → 93 % des tentatives de connexion CLIENT rejetées. Toutes les VUs de ce test partagent la même IP conteneur, ce qui amplifie l'effet — mais un réseau d'entreprise ou un NAT mobile réel produit la même signature IP partagée pour plusieurs utilisateurs distincts.
- **Impact** : sous certaines topologies réseau réelles, des utilisateurs légitimes pourraient se bloquer mutuellement, y compris sur un simple rafraîchissement de profil. Complique aussi l'interprétation des résultats de charge (voir §2, "point de saturation").
- **Recommandation** : sortir `/me` (et `/logout`) du groupe throttle partagé avec `login`/`register`/`check-phone` ; envisager une clé de throttle par utilisateur authentifié (plutôt que par IP) pour les routes déjà sous `auth:sanctum`.

**D. Changement de statut concurrent : lost update possible, audit trail potentiellement incohérent** — 🟢 CORRIGÉ (voir addendum en tête de rapport)
- **Où** : `app/Observers/OrderObserver.php` (`updating()`), pas de verrou pessimiste
- **Cause probable** : la validation de transition compare au statut chargé au début de CHAQUE requête (`getOriginal('status')`), sans `lockForUpdate()` — deux requêtes concurrentes peuvent chacune valider une transition différente depuis le même statut de départ.
- **Preuve** : `tests/Concurrency/OrderStatusConcurrencyTest.php` — deux transitions valides envoyées en parallèle (`Reçue→Traitement` et `Reçue→Annulée`) sont TOUTES LES DEUX acceptées côté application ; le statut final ne reflète que la dernière écriture committée, mais `order_status_histories` peut contenir les deux transitions comme si elles s'étaient enchaînées.
- **Impact** : peu probable en usage normal (deux employés modifiant simultanément LA MÊME commande à la même seconde), mais un désaccord entre l'audit trail et l'état réel est un vrai problème de confiance dans les données en cas de litige/contrôle.
- **Recommandation** : `Order::lockForUpdate()` avant la validation de transition, ou horodatage/version optimiste.

### 🟡 FAIBLE

**E. Quota d'abonnement : pas de verrou explicite, mais aucun dépassement reproduit**
- **Où** : `app/Actions/Orders/CreateOrderAction.php`
- **Preuve** : `tests/Concurrency/SubscriptionQuotaConcurrencyTest.php`, 5 exécutions avec 2 requêtes concurrentes pour le dernier slot de quota → **0 dépassement observé** à chaque fois (probablement grâce au verrou de ligne implicite pris par `increment('orders_used')`, qui sérialise les deux transactions au niveau MySQL).
- **Recommandation** : ajouter `lockForUpdate()` par prudence (défense en profondeur), sans urgence vu l'absence de reproduction empirique même après plusieurs tentatives.

**F. Double-adhésion à un pressing : protégée par la contrainte unique, à surveiller**
- **Où** : `app/Http/Controllers/Api/V1/PressingController.php::join()`
- **Preuve** : `tests/Concurrency/PressingJoinConcurrencyTest.php` — deux requêtes concurrentes de join pour le même client/pressing ne produisent jamais de doublon ni de 500 (contrainte `UNIQUE(pressing_id, customer_id)` en base). Aucune action requise.

### ✅ Point positif à souligner

**G. Isolation multi-tenant : 0 violation, y compris sous charge**
- Le fuzzing cross-pressing (`k6/security/tenant_fuzzing.js`) — tentatives d'accès à des clients/commandes/services d'un AUTRE pressing, exécutées PENDANT une charge de plusieurs VUs — n'a produit **aucune fuite de données** : 100 % des tentatives rejetées en 403, sur toutes les combinaisons testées (client étranger, commande étrangère, service étranger). L'audit sécurité de la Phase 8 (RB-01, IDOR) tient sous charge.

---

## 5. Concurrence métier — synthèse (Section 5)

| Scénario | Résultat | Sévérité associée |
|---|---|---|
| 2 employés créent des commandes simultanément | ✅ Aucune corruption croisée, `orders_used` incrémenté correctement (2/2, pas de lost update sur l'incrément) | — |
| 2 employés modifient le même statut de commande | ⚠️ Les deux transitions peuvent être acceptées (lost update sur le statut final, historique potentiellement incohérent) | MOYEN (D) |
| 2 requêtes pour le dernier slot de quota | ✅ Aucun dépassement reproduit (5/5 runs) — pas de verrou explicite néanmoins | FAIBLE (E) |
| 2 requêtes rejoignent le même pressing | ✅ Contrainte unique protège, pas de 500 | FAIBLE (F) |
| Suspension pendant une création de commande | 🔴 Suspension sans aucun effet, avec ou sans concurrence | ÉLEVÉ (B) |
| Changement de statut simultané | Voir "2 employés modifient le même statut" ci-dessus | MOYEN (D) |

---

## 6. Recommandations, par priorité

1. ~~**ÉLEVÉ — notifications** : implémenter `ShouldQueue` sur `OrderNotification` + déployer `php artisan queue:work` (finding A).~~ 🟢 **Fait** (voir addendum — `ShouldQueueAfterCommit`, pas juste `ShouldQueue`).
2. ~~**ÉLEVÉ — suspension** : faire respecter `PressingStatus::Suspended` dans `CreateOrderAction` (finding B).~~ 🟢 **Fait** (voir addendum).
3. ~~**MOYEN — throttle** : séparer `/me`/`/logout` du throttle partagé avec `login`/`register` (finding C).~~ 🟢 **Fait** (voir addendum).
4. ~~**MOYEN — verrou de statut** : `lockForUpdate()` avant validation de transition (finding D).~~ 🟢 **Fait** (voir addendum).
5. **Avant tout capacity planning définitif** : ré-exécuter les paliers 100/250/500/1000 sur une vraie stack php-fpm+nginx, en neutralisant l'effet du rate-limiting IP pour isoler la capacité réelle du serveur applicatif (§2). **Toujours ouvert** — nécessite une infra de production, indisponible dans ce conteneur.
6. **FAIBLE — défense en profondeur** : `lockForUpdate()` sur la lecture d'abonnement dans `CreateOrderAction` (finding E). **Non traité**, priorité FAIBLE (aucun dépassement reproduit empiriquement), à faire par prudence lors d'un prochain passage.

Un middleware bloquant plus largement l'accès aux routes staff pour un
pressing suspendu (au-delà de la seule création de commande) reste une
amélioration possible mais plus large que le correctif appliqué ici —
voir §7.

---

## 7. Limites de cet audit

- Serveur PHP intégré, pas php-fpm (voir §0) — résultats de latence/débit
  non transposables tels quels à la production.
- Rate limiting par IP partagé entre toutes les VUs du test (une seule
  machine) — probablement plus sévère ici qu'avec de vrais utilisateurs
  distribués (voir finding C et §2).
- FCM non testable réellement (pas d'identifiants Firebase dans ce
  conteneur) — comportement d'échec/retry FCM sous charge non vérifié.
- Paliers 250/500/1000 non exécutés faute d'infra de production
  disponible dans ce conteneur — scripts livrés, prêts à l'emploi.
- 4 cœurs CPU disponibles dans ce conteneur — une infra de production
  aura probablement un profil de ressources différent.
- Le correctif de la suspension (finding B) reste ciblé sur
  `CreateOrderAction` (le point concret testé et documenté) — un pressing
  suspendu peut donc toujours techniquement accéder à d'autres pages
  staff (dashboard, listes) sans en modifier les données. Un middleware
  bloquant plus largement l'accès aux routes staff serait une itération
  supplémentaire, plus large que le périmètre de ce correctif.
