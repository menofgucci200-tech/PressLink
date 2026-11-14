-- PressLink — mise à jour de la base de production (ramassage et livraison, lot 2)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_14_225110_create_pickup_requests_table
-- - pickup_requests : demandes de ramassage envoyées depuis l'appli client.
-- - pickup_request_photos : photos du linge jointes aux demandes.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'pickup_requests';
-- (une ligne = déjà fait, ne pas relancer)

CREATE TABLE `pickup_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pressing_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `address_label` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commune` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `neighborhood` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pickup_date` date NOT NULL,
  `slot_from` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_to` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rescheduled` tinyint(1) NOT NULL DEFAULT 0,
  `items_estimate` smallint unsigned DEFAULT NULL,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pickup_fee_fcfa` int unsigned NOT NULL DEFAULT 0,
  `refusal_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responded_by` bigint unsigned DEFAULT NULL,
  `responded_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pickup_requests_pressing_id_status_index` (`pressing_id`, `status`),
  KEY `pickup_requests_customer_id_status_index` (`customer_id`, `status`),
  KEY `pickup_requests_responded_by_foreign` (`responded_by`),
  CONSTRAINT `pickup_requests_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pickup_requests_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pickup_requests_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pickup_request_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pickup_request_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pickup_request_photos_pickup_request_id_foreign` (`pickup_request_id`),
  CONSTRAINT `pickup_request_photos_pickup_request_id_foreign` FOREIGN KEY (`pickup_request_id`) REFERENCES `pickup_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_225110_create_pickup_requests_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
