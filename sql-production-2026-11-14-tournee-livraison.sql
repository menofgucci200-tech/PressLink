-- PressLink — mise à jour de la base de production (ramassage et livraison, lot 3 : tournée)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_14_232138_create_order_deliveries_table
-- - pickup_requests : lien vers la commande créée au ramassage, date et auteur du ramassage.
-- - order_deliveries : livraisons à domicile demandées depuis l'appli client.
-- - roles : les rôles Gérant et Employé reçoivent la permission « Tournée » (deliveries.run).
-- Aucune autre donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'order_deliveries';
-- (une ligne = déjà fait, ne pas relancer)

ALTER TABLE `pickup_requests`
  ADD COLUMN `order_id` bigint unsigned DEFAULT NULL AFTER `customer_id`,
  ADD COLUMN `picked_up_at` timestamp NULL DEFAULT NULL AFTER `responded_at`,
  ADD COLUMN `picked_up_by` bigint unsigned DEFAULT NULL AFTER `picked_up_at`,
  ADD KEY `pickup_requests_order_id_foreign` (`order_id`),
  ADD KEY `pickup_requests_picked_up_by_foreign` (`picked_up_by`),
  ADD CONSTRAINT `pickup_requests_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `pickup_requests_picked_up_by_foreign` FOREIGN KEY (`picked_up_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

CREATE TABLE `order_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pressing_id` bigint unsigned NOT NULL,
  `order_id` bigint unsigned NOT NULL,
  `customer_id` bigint unsigned NOT NULL,
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `address_label` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `commune` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `neighborhood` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `landmark` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery_date` date NOT NULL,
  `slot_from` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_to` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rescheduled` tinyint(1) NOT NULL DEFAULT 0,
  `note` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivery_fee_fcfa` int unsigned NOT NULL DEFAULT 0,
  `cancel_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `handled_by` bigint unsigned DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_deliveries_customer_id_foreign` (`customer_id`),
  KEY `order_deliveries_handled_by_foreign` (`handled_by`),
  KEY `order_deliveries_pressing_id_status_delivery_date_index` (`pressing_id`, `status`, `delivery_date`),
  KEY `order_deliveries_order_id_status_index` (`order_id`, `status`),
  CONSTRAINT `order_deliveries_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_deliveries_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_deliveries_customer_id_foreign` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_deliveries_handled_by_foreign` FOREIGN KEY (`handled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE `roles`
SET `permissions` = JSON_ARRAY_APPEND(`permissions`, '$', 'deliveries.run')
WHERE `key` IN ('manager', 'employee')
  AND JSON_VALID(`permissions`)
  AND NOT JSON_CONTAINS(`permissions`, '"deliveries.run"');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_232138_create_order_deliveries_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
