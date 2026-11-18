-- PressLink : libère les numéros des équipes de pressings déjà supprimés.
-- Supprime les comptes d'équipe rattachés à aucun pressing, qui ne sont ni
-- super admin ni commerciaux (restes de pressings supprimés avant ce correctif).
-- Les commandes, paiements, journaux… qui les citaient gardent leur ligne
-- (la colonne passe à NULL automatiquement). Sans risque à relancer.

DELETE FROM personal_access_tokens
WHERE tokenable_type = 'App\\Models\\User'
  AND tokenable_id IN (
    SELECT id FROM (
      SELECT u.id FROM users u
      WHERE u.is_super_admin = 0
        AND NOT EXISTS (SELECT 1 FROM pressing_users pu WHERE pu.user_id = u.id)
        AND NOT EXISTS (SELECT 1 FROM sales_agents sa WHERE sa.user_id = u.id)
    ) orphelins
  );

DELETE FROM sessions
WHERE user_id IN (
    SELECT id FROM (
      SELECT u.id FROM users u
      WHERE u.is_super_admin = 0
        AND NOT EXISTS (SELECT 1 FROM pressing_users pu WHERE pu.user_id = u.id)
        AND NOT EXISTS (SELECT 1 FROM sales_agents sa WHERE sa.user_id = u.id)
    ) orphelins
  );

DELETE u FROM users u
WHERE u.is_super_admin = 0
  AND NOT EXISTS (SELECT 1 FROM pressing_users pu WHERE pu.user_id = u.id)
  AND NOT EXISTS (SELECT 1 FROM sales_agents sa WHERE sa.user_id = u.id);
