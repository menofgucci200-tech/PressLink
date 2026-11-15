-- PressLink — mise à jour de la base de production (ramassage ouvert à la zone)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_15_110337_open_pickup_requests_to_the_zone
-- - pickup_requests : pressing_id peut être vide (demande laissée au premier
--   pressing de la commune qui l'accepte) ; index pour les retrouver par commune.
-- - pickup_request_dismissals : demandes de zone masquées par un pressing (« Pas pour nous »).
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'pickup_request_dismissals';
-- (une ligne = déjà fait, ne pas relancer)

ALTER TABLE `pickup_requests`
  MODIFY `pressing_id` bigint unsigned NULL DEFAULT NULL,
  ADD KEY `pickup_requests_status_commune_index` (`status`, `commune`);

CREATE TABLE `pickup_request_dismissals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pickup_request_id` bigint unsigned NOT NULL,
  `pressing_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pickup_request_dismissals_pickup_request_id_pressing_id_unique` (`pickup_request_id`, `pressing_id`),
  KEY `pickup_request_dismissals_pressing_id_foreign` (`pressing_id`),
  CONSTRAINT `pickup_request_dismissals_pickup_request_id_foreign` FOREIGN KEY (`pickup_request_id`) REFERENCES `pickup_requests` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pickup_request_dismissals_pressing_id_foreign` FOREIGN KEY (`pressing_id`) REFERENCES `pressings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_15_110337_open_pickup_requests_to_the_zone', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
