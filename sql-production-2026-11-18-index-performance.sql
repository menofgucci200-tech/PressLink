-- PressLink : index de performance (tableau de bord, listes des commandes et des clients, console).
-- À passer dans phpMyAdmin AVANT le déploiement. Aucune donnée n'est modifiée.

ALTER TABLE `orders` ADD INDEX `orders_pressing_id_created_at_index` (`pressing_id`, `created_at`);
ALTER TABLE `orders` ADD INDEX `orders_pressing_id_dropped_off_at_index` (`pressing_id`, `dropped_off_at`);
ALTER TABLE `orders` ADD INDEX `orders_created_at_index` (`created_at`);

ALTER TABLE `order_status_histories` ADD INDEX `order_status_histories_order_id_status_created_at_index` (`order_id`, `status`, `created_at`);
ALTER TABLE `order_status_histories` ADD INDEX `order_status_histories_status_created_at_index` (`status`, `created_at`);

ALTER TABLE `pressing_customers` ADD INDEX `pressing_customers_pressing_id_joined_at_customer_id_index` (`pressing_id`, `joined_at`, `customer_id`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_18_014047_add_performance_indexes', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
