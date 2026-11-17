-- PressLink — mise à jour de la base de production (commerciaux de terrain)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_17_005409_create_sales_agents
-- - sales_agents : les commerciaux (compte, code d'inscription, taux de commission, suspension).
-- - pressings : sales_agent_id / sales_agent_attached_at, le commercial qui a apporté le pressing.
-- - sales_agent_claims : signalements « ce pressing vient de moi », validés par le Super Admin.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'sales_agents';
-- (une ligne = déjà fait, ne pas relancer)

CREATE TABLE `sales_agents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `code` varchar(20) NOT NULL,
  `commission_rate` tinyint unsigned NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_agents_user_id_unique` (`user_id`),
  UNIQUE KEY `sales_agents_code_unique` (`code`),
  CONSTRAINT `sales_agents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pressings`
  ADD `sales_agent_id` bigint unsigned NULL DEFAULT NULL AFTER `sandbox_expires_at`,
  ADD `sales_agent_attached_at` timestamp NULL DEFAULT NULL AFTER `sales_agent_id`,
  ADD CONSTRAINT `pressings_sales_agent_id_foreign` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE SET NULL;

CREATE TABLE `sales_agent_claims` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sales_agent_id` bigint unsigned NOT NULL,
  `pressing_id` bigint unsigned NULL DEFAULT NULL,
  `pressing_name` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `note` varchar(500) NULL DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `handled_by` bigint unsigned NULL DEFAULT NULL,
  `handled_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `sales_agent_claims_status_created_at_index` (`status`, `created_at`),
  KEY `sales_agent_claims_sales_agent_id_foreign` (`sales_agent_id`),
  KEY `sales_agent_claims_pressing_id_foreign` (`pressing_id`),
  KEY `sales_agent_claims_handled_by_foreign` (`handled_by`),
  CONSTRAINT `sales_agent_claims_sales_agent_id_foreign` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_agent_claims_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_agent_claims_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_17_005409_create_sales_agents', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
