-- PressLink — mise à jour de la base de production (rôle Coursier)
-- À exécuter dans phpMyAdmin, onglet SQL, AVANT ou APRÈS le déploiement (sans risque de le relancer).
-- Aucune migration : ajoute le rôle de base « Coursier » (accès Tournée seulement) aux pressings existants.
-- Les nouveaux pressings le reçoivent automatiquement.
-- 1) Un pressing qui a déjà créé son propre rôle « Coursier » : ce rôle devient le rôle de base
--    (ses accès et ses membres sont gardés).
-- 2) Les autres pressings reçoivent le rôle « Coursier » avec le seul accès « Tournée ».

UPDATE `roles` r
JOIN (
  SELECT MIN(`id`) AS `id`
  FROM `roles`
  WHERE `key` IS NULL AND `name` = 'Coursier'
    AND `pressing_id` NOT IN (SELECT `pressing_id` FROM (SELECT `pressing_id` FROM `roles` WHERE `key` = 'courier') AS `deja`)
  GROUP BY `pressing_id`
) AS `a_adopter` ON `a_adopter`.`id` = r.`id`
SET r.`key` = 'courier', r.`updated_at` = NOW();

INSERT INTO `roles` (`pressing_id`, `key`, `name`, `permissions`, `created_at`, `updated_at`)
SELECT p.`id`, 'courier', 'Coursier', '["deliveries.run"]', NOW(), NOW()
FROM `pressings` p
WHERE NOT EXISTS (
  SELECT 1 FROM `roles` r WHERE r.`pressing_id` = p.`id` AND (r.`key` = 'courier' OR r.`name` = 'Coursier')
);
