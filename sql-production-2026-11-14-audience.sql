-- PressLink — mise à jour de la base de production (audience du site)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_14_211748_create_audience_events_table
-- - audience_events : pages vues et clics du site vitrine (menu Audience de la
--   console super admin). Pas d'adresse IP : seulement une empreinte du jour.
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW TABLES LIKE 'audience_events';
-- (une ligne = déjà fait, ne pas relancer)

CREATE TABLE `audience_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_entry` tinyint(1) NOT NULL DEFAULT 0,
  `device` varchar(12) COLLATE utf8mb4_unicode_ci NOT NULL,
  `visitor` char(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurred_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `audience_events_occurred_at_index` (`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_14_211748_create_audience_events_table', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
