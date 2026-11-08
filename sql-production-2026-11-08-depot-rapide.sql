-- PressLink — mise à jour de la base de production (dépôt rapide)
-- À exécuter UNE SEULE FOIS dans phpMyAdmin, onglet SQL, AVANT le déploiement.
-- Équivaut à la migration : 2026_11_08_102836_add_quick_deposit_to_orders
-- - orders.details_pending : commande « à compléter » (détail et prix saisis plus tard)
-- - orders.declared_pieces : nombre de pièces comptées au dépôt
-- Aucune donnée existante n'est modifiée.
-- Vérification préalable : SHOW COLUMNS FROM `orders` LIKE 'details_pending';
-- (une ligne = déjà fait, ne pas relancer)

ALTER TABLE `orders`
  ADD COLUMN `details_pending` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`,
  ADD COLUMN `declared_pieces` smallint unsigned DEFAULT NULL AFTER `details_pending`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_11_08_102836_add_quick_deposit_to_orders', COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
