-- Lets admins add/remove a user's points at a specific shop (admin_shop_points.php).
-- Adds the 'admin' kind to the shop points ledger. Run after add_shop_checkins.sql.
ALTER TABLE shop_point_txns MODIFY kind ENUM('checkin','redeem','admin') NOT NULL;
