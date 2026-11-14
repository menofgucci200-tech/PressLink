-- PressLink — mise à jour de la base de production (ramassage et livraison, lot 1)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut aux migrations :
--   2026_11_14_223249_add_pickup_and_delivery_to_pressings
--   2026_11_14_223250_create_customer_addresses_table
-- - pressings : 7 colonnes de réglages (services, frais, créneaux, délai),
--   toutes avec une valeur par défaut « service désactivé ».
-- - pressing_service_areas : communes desservies par chaque pressing.
-- - customer_addresses : adresses de ramassage et de livraison des clients.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'customer_addresses';
-- (une ligne = déjà fait, ne pas relancer)

ALTER TABLE `pressings`
  ADD COLUMN `pickup_enabled` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `delivery_enabled` tinyint(1) NOT NULL DEFAULT 0,
  ADD COLUMN `pickup_fee_fcfa` int unsigned NOT NULL DEFAULT 0,
  ADD COLUMN `delivery_fee_fcfa` int unsigned NOT NULL DEFAULT 0,
  ADD COLUMN `delivery_free_from_fcfa` int unsigned DEFAULT NULL,
  ADD COLUMN `delivery_slots` json DEFAULT NULL,
  ADD COLUMN `turnaround_hours` smallint unsigned DEFAULT NULL;

CREATE TABLE `pressing_service_areas` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pressing_id` bigint unsigned NOT NULL,
  `commune` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pressing_service_areas_pressing_id_commune_unique` (`pressing_id`, `commune`),
  KEY `pressing_service_areas_commune_index` (`commune`),
  CONSTRAINT `pressing_service_areas_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `customer_addresses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `label` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commune` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `neighborhood` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `customer_addresses_customer_id_foreign` (`customer_id`),
  CONSTRAINT `customer_addresses_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_223249_add_pickup_and_delivery_to_pressings', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_223250_create_customer_addresses_table', MAX(`batch`) FROM `migrations`;
