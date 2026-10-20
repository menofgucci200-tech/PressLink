-- PressLink — mise à jour de la base de production (dépenses de caisse)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_10_21_160000_create_cash_expenses_and_extend_closings
--
-- Ce que fait ce script :
--  1. crée la table des dépenses de caisse (argent sorti de la caisse dans la
--     journée : produits, transport…) ;
--  2. la clôture garde les espèces encaissées et les dépenses : encaissé −
--     dépenses = ce qui doit être dans la caisse, comparé à ce qui est compté ;
--     les clôtures déjà faites gardent leurs montants ;
--  3. le Gérant peut enregistrer les dépenses ; l'accès « voir ses propres
--     encaissements » disparaît (la liste des paiements est retirée de la caisse) ;
--  4. indique à Laravel que la migration est faite.
-- Aucune commande et aucun paiement n'est modifié.

-- 1. Dépenses de caisse
CREATE TABLE `cash_expenses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pressing_id` bigint unsigned NOT NULL,
  `business_date` date NOT NULL,
  `amount_fcfa` int unsigned NOT NULL,
  `label` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cash_expenses_recorded_by_foreign` (`recorded_by`),
  KEY `cash_expenses_pressing_id_business_date_index` (`pressing_id`, `business_date`),
  CONSTRAINT `cash_expenses_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `cash_expenses_recorded_by_foreign` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Clôtures : espèces encaissées et dépenses de la journée
ALTER TABLE `cash_closings`
  ADD `cash_received_fcfa` int NOT NULL DEFAULT 0 AFTER `total_fcfa`,
  ADD `expenses_fcfa` int unsigned NOT NULL DEFAULT 0 AFTER `cash_received_fcfa`,
  MODIFY `expected_cash_fcfa` int NOT NULL;

UPDATE `cash_closings` SET `cash_received_fcfa` = `expected_cash_fcfa`;

-- 3. Accès des rôles
UPDATE `roles`
SET `permissions` = JSON_REMOVE(`permissions`, JSON_UNQUOTE(JSON_SEARCH(`permissions`, 'one', 'cash.own_payments')))
WHERE JSON_SEARCH(`permissions`, 'one', 'cash.own_payments') IS NOT NULL;

UPDATE `roles`
SET `permissions` = JSON_ARRAY_APPEND(`permissions`, '$', 'cash.expenses')
WHERE `key` = 'manager' AND JSON_SEARCH(`permissions`, 'one', 'cash.expenses') IS NULL;

-- 4. Indique à Laravel que la migration est faite
INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_21_160000_create_cash_expenses_and_extend_closings', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
