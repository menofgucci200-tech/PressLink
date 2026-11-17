-- PressLink — mise à jour de la base de production (1 mois offert à chaque nouveau pressing)
-- À exécuter dans phpMyAdmin, onglet SQL, AVANT ou APRÈS le déploiement (sans risque de le relancer).
-- Aucune migration : seulement deux réglages de la console Super Admin.
-- - trial_days : durée de l'essai des nouveaux pressings passée à 30 jours (« 1 mois offert »),
--   au cas où l'ancienne valeur (7 jours) avait été enregistrée depuis la page Formules.
-- - launch_offer : l'ancien texte « Offre de lancement » n'est plus affiché nulle part ; on le retire.
-- Les pressings déjà inscrits gardent leur date de fin d'essai actuelle.

INSERT INTO `settings` (`key`, `value`, `created_at`, `updated_at`)
VALUES ('trial_days', '30', NOW(), NOW())
ON DUPLICATE KEY UPDATE `value` = '30', `updated_at` = NOW();

DELETE FROM `settings` WHERE `key` = 'launch_offer';
