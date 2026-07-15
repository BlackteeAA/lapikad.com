-- Extends shop_requests with the extra fields the 5-step "shop_apply.php" wizard
-- collects (English name, description, contact channels, opening hours,
-- highlights). shop_requests already exists in production (created by
-- add_shop_role.sql, plus a manual follow-up ALTER for category/lat/lng) —
-- this migration only adds columns on top of that.
--
-- These fields are stored for the admin's review context only; they are not
-- propagated into `places` on approval (places has no matching columns and no
-- public display of them was requested).

ALTER TABLE shop_requests ADD COLUMN shop_name_en VARCHAR(150) NULL;
ALTER TABLE shop_requests ADD COLUMN description VARCHAR(300) NULL;
ALTER TABLE shop_requests ADD COLUMN phone VARCHAR(30) NULL;
ALTER TABLE shop_requests ADD COLUMN line_id VARCHAR(100) NULL;
ALTER TABLE shop_requests ADD COLUMN facebook_url VARCHAR(255) NULL;
ALTER TABLE shop_requests ADD COLUMN contact_email VARCHAR(150) NULL;
ALTER TABLE shop_requests ADD COLUMN opening_hours VARCHAR(150) NULL;
ALTER TABLE shop_requests ADD COLUMN highlights VARCHAR(300) NULL;
