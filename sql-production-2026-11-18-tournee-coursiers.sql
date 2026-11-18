-- PressLink : livraison programmée par le pressing et passages confiés à un coursier.
-- À passer dans phpMyAdmin AVANT le déploiement.

ALTER TABLE `pickup_requests` ADD `courier_id` BIGINT UNSIGNED NULL AFTER `responded_by`;
ALTER TABLE `pickup_requests` ADD CONSTRAINT `pickup_requests_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `order_deliveries` ADD `courier_id` BIGINT UNSIGNED NULL AFTER `handled_by`;
ALTER TABLE `order_deliveries` ADD CONSTRAINT `order_deliveries_courier_id_foreign` FOREIGN KEY (`courier_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_18_011022_add_courier_to_rounds', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
