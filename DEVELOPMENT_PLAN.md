# Plan de développement — PressLink (MVP)

Basé sur : positionnement, MVP, cahier des fonctionnalités, user flows, architecture des écrans, wireframes et design system déjà validés.

Stack retenue : **Laravel (API + Livewire dashboard) + MySQL + Flutter (app client) + Sanctum/OTP + FCM**.

---

## Phase 0 — Fondations techniques (≈1 semaine)

- Init repo (monorepo ou 2 repos : `presslink-api` / `presslink-app`)
- Laravel : projet + Sanctum + config MySQL + Docker ou Valet local
- Structure des rôles/permissions (Client, Employé, Admin pressing, Super Admin) — Spatie Permission ou champ `role` simple selon besoin réel
- Convention API : versionning `/api/v1`, format de réponse JSON standard, gestion d'erreurs uniforme
- Flutter : init projet, architecture (ex. feature-first + Riverpod/Bloc), thème avec les tokens du design system (couleurs, Inter, radius, spacing)
- CI basique (lint + tests) dès le départ pour ne pas payer cette dette plus tard

**Livrable** : squelette API qui répond, squelette app qui compile et affiche l'écran d'accueil avec le thème.

---

## Phase 1 — Modèle de données & règles métier (≈1 semaine)

Tables principales (cf. cahier des fonctionnalités) :

```
users (table polymorphe ou séparée client/staff selon guard)
customers
pressings
  pressing_users (pivot : user ↔ pressing, avec rôle employee/admin)
  pressing_customers (pivot : un client peut rejoindre plusieurs pressings)
services (tarifs par pressing : nom, prix)
orders
  order_items (service, quantité, prix unitaire, sous-total)
  order_status_history (statut, horodatage, auteur)
subscriptions (plan, statut, dates, compteur commandes)
notifications
```

Implémenter dès cette phase les règles métier RB-01 à RB-10 (isolation pressing/client, 1 commande = 1 pressing + 1 client, au moins 1 article, historisation des statuts, soft delete des commandes, etc.) sous forme de policies Laravel + contraintes DB (foreign keys, scopes globaux).

**Livrable** : migrations + seeders (données de démo réalistes) + policies testées.

---

## Phase 2 — Authentification (≈1 semaine)

- Client : inscription téléphone → OTP (choisir un provider SMS dès maintenant même si mock en dev) → création profil
- Staff (employé/admin) : email/téléphone + mot de passe, reset password
- Sanctum : tokens API pour Flutter, sessions pour le dashboard Livewire
- Association client ↔ pressing via **code pressing** (flux le plus simple retenu pour le MVP)

**Livrable** : un client peut s'inscrire et rejoindre un pressing ; un employé/admin peut se connecter au dashboard.

---

## Phase 3 — Cœur métier : commandes (≈2-3 semaines, le plus gros morceau)

C'est la boucle centrale : **dépôt → commande → statuts → notification → récupération**.

1. Gestion des clients (CRUD + recherche) côté dashboard
2. Gestion des tarifs/services par pressing
3. Création de commande (wizard 4 étapes) : client → articles → détails → confirmation, génération auto du numéro `PL-XXXXXX`
4. Changement de statut (Reçue → Traitement → Prête → Récupérée) avec historisation automatique
5. Recherche de commande (numéro, nom, téléphone)
6. Écran de récupération (marquer récupérée + horodatage + employé)
7. Règle abonnement : blocage de création si quota de commandes dépassé ou essai expiré (RB-09)

**Livrable** : un employé peut créer, suivre et clôturer une commande de bout en bout dans le dashboard.

---

## Phase 4 — Notifications (≈3-5 jours)

- Intégration FCM (push) déclenchée sur les événements : commande créée, prête, récupérée
- Table `notifications` + endpoint de lecture côté app client
- Préparer l'abstraction pour brancher SMS/WhatsApp en V1 sans tout réécrire

**Livrable** : le client reçoit une notification push réelle à chaque changement de statut.

---

## Phase 5 — Application client Flutter (≈2-3 semaines, en parallèle de la phase 3-4 si ressources dispo)

Écrans dans l'ordre de priorité :
1. Splash / Onboarding / Auth (téléphone + OTP)
2. Accueil (résumé + commandes récentes)
3. Mes commandes (liste + filtres) + Détail commande (statut, articles, historique)
4. Rejoindre un pressing (code)
5. Notifications
6. Profil

**Livrable** : app installable (TestFlight / APK interne) couvrant les 5 flows critiques du MVP côté client.

---

## Phase 6 — Dashboard pressing (Laravel/Livewire) (≈2 semaines, en parallèle de la phase 3)

- Dashboard (KPI du jour + commandes récentes)
- Commandes (liste filtrable + détail)
- Clients
- Tarifs
- Équipe (gestion employés, rôles, désactivation)
- Abonnement (plan actuel, consommation, essai) — paiement manuel/simple au MVP, pas d'automatisation immédiate
- Paramètres pressing (infos, code pressing)

Appliquer directement les composants du design system (StatusBadge, OrderCard/Table, Sidebar, KPI Card) définis dans la maquette haute fidélité.

**Livrable** : un admin de pressing peut gérer tout son établissement sans support technique.

---

## Phase 7 — Back-office Super Admin (≈1 semaine)

Volume minimal pour le pilote :
- Liste des pressings (créer/suspendre/réactiver)
- Vue globale (pressings actifs, clients, commandes)
- Gestion basique des plans d'abonnement

**Livrable** : PressLink (l'équipe) peut onboarder et surveiller les pressings pilotes sans passer par la base de données.

---

## Phase 8 — Durcissement & pilote (≈1-2 semaines)

- Tests bout en bout des 5 flows critiques
- Gestion des états (loading/empty/error/offline) sur l'app et le dashboard
- Isolation multi-tenant vérifiée (RB-01/RB-02) — tests spécifiques
- Onboarding de 3 à 5 vrais pressings, essai 14 jours
- Mise en place du tracking des indicateurs de succès (commandes créées, notifications envoyées, taux de récupération, volonté de payer)

---

## Découpage indicatif (équipe réduite, 1-2 devs)

| Semaine | Contenu |
|---|---|
| 1 | Phase 0 |
| 2 | Phase 1 |
| 3 | Phase 2 |
| 4-6 | Phase 3 |
| 6-8 | Phase 5 + 6 en parallèle |
| 7 | Phase 4 (s'intercale) |
| 9 | Phase 7 |
| 10-11 | Phase 8 + pilote terrain |

**≈ 10-11 semaines** jusqu'au lancement du pilote avec les premiers pressings réels, en solo/duo. À ajuster selon la taille réelle de l'équipe.

---

## Hors périmètre (rappel, ne pas construire maintenant)

QR code, SMS/WhatsApp automatisés, paiement en ligne, fidélité, promotions, livraison, multi-agences, statistiques avancées, API publique, IA. Cf. cahier des fonctionnalités §22.

---

## Prochaine décision à prendre avant de coder

- Monorepo vs repos séparés (API / Flutter)
- Provider OTP/SMS pour la Côte d'Ivoire (coût, fiabilité)
- Hébergement (VPS, PaaS type Forge/Vapor, ou autre) pour Laravel + MySQL
