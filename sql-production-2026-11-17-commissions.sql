-- PressLink — mise à jour de la base de production (commissions des commerciaux)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_17_011002_create_sales_commissions
-- - sales_commissions : une ligne par paiement de l'abonnement (formule ou renouvellement)
--   d'un pressing apporté par un commercial, au taux du jour ; payée ensuite par l'équipe.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'sales_commissions';
-- (une ligne = déjà fait, ne pas relancer)

CREATE TABLE `sales_commissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_agent_id` bigint unsigned NOT NULL,
  `pressing_id` bigint unsigned NULL DEFAULT NULL,
  `subscription_request_id` bigint unsigned NULL DEFAULT NULL,
  `pressing_name` varchar(150) NOT NULL,
  `label` varchar(255) NOT NULL,
  `base_fcfa` int unsigned NOT NULL,
  `rate` tinyint unsigned NOT NULL,
  `amount_fcfa` int unsigned NOT NULL,
  `earned_at` timestamp NOT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `payout_reference` varchar(100) NULL DEFAULT NULL,
  `paid_by` bigint unsigned NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_commissions_subscription_request_id_unique` (`subscription_request_id`),
  KEY `sales_commissions_sales_agent_id_earned_at_index` (`sales_agent_id`, `earned_at`),
  KEY `sales_commissions_pressing_id_foreign` (`pressing_id`),
  KEY `sales_commissions_paid_by_foreign` (`paid_by`),
  CONSTRAINT `sales_commissions_sales_agent_id_foreign` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_commissions_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_commissions_subscription_request_id_foreign` FOREIGN KEY (`subscription_request_id`) REFERENCES `subscription_requests` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_commissions_paid_by_foreign` FOREIGN KEY (`paid_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_17_011002_create_sales_commissions', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
