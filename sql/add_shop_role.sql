-- Adds a self-serve "shop" role: users apply via shop_requests, an admin approves,
-- which flips users.role to 'shop' and creates/links a places row (places.owner_user_id).
-- Shop owners then manage their own quests (capped reward_points, enforced in app code)
-- and rewards (rewards.place_id, redemption scoped in app code).
--
-- NOTE: init.sql is intentionally not modified. The live schema already has columns
-- (places.category/image_url/lat/lng/district/province, users.avatar_url/bio,
-- user_quests.photo_url) that were added directly in production and were never
-- captured in a migration file. This migration only adds what the shop-role feature
-- needs on top of whatever schema is already live; it does not attempt to reconcile
-- that pre-existing drift.

ALTER TABLE users MODIFY COLUMN role ENUM('user','admin','shop') NOT NULL DEFAULT 'user';

ALTER TABLE places ADD COLUMN owner_user_id INT NULL;
ALTER TABLE places ADD UNIQUE KEY unique_owner (owner_user_id);
ALTER TABLE places ADD FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE TABLE shop_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  shop_name VARCHAR(150) NOT NULL,
  location_text VARCHAR(255),
  image_url VARCHAR(255),
  category VARCHAR(100),
  lat DOUBLE NULL,
  lng DOUBLE NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NULL,
  reviewed_by INT NULL,
  reviewed_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE rewards ADD COLUMN place_id INT NULL;
ALTER TABLE rewards ADD FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE;
