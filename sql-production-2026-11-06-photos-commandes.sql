-- PressLink — mise à jour de la base de production (photos des commandes)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_06_013452_create_order_photos_table
-- - order_photos : photos des articles prises par le pressing (fichiers dans
--   storage/app/private/order-photos), visibles aussi par le client dans l'application.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'order_photos';
-- (une ligne = déjà fait, ne pas relancer)

CREATE TABLE `order_photos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `thumb_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size_bytes` int unsigned NOT NULL DEFAULT 0,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_photos_order_id_foreign` (`order_id`),
  KEY `order_photos_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `order_photos_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_photos_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_06_013452_create_order_photos_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
