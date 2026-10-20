-- Remet la copie de démonstration (presslink_capture) dans l'état d'avant le tournage :
-- pas de clôture ni de dépense aujourd'hui, et seulement les 4 commandes d'Awa Konan.
SET @c = (SELECT id FROM customers WHERE phone = '+2250701234567');
SET @garder = (SELECT MAX(id) FROM orders WHERE customer_id = @c AND order_number = 'PL-949922');
DELETE FROM order_payments WHERE order_id IN (SELECT id FROM orders WHERE customer_id = @c AND id > @garder);
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE customer_id = @c AND id > @garder);
DELETE FROM order_status_histories WHERE order_id IN (SELECT id FROM orders WHERE customer_id = @c AND id > @garder);
DELETE FROM orders WHERE customer_id = @c AND id > @garder;
DELETE FROM cash_closings WHERE pressing_id = 1 AND business_date = CURDATE();
DELETE FROM cash_expenses WHERE pressing_id = 1 AND business_date = CURDATE();
