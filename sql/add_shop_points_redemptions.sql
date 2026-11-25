-- Adds a per-(user, shop-place) points balance ("แต้มร้าน") that is earned on top of
-- the existing global users.points whenever a user completes a quest at a place that
-- has an owner_user_id (i.e. a real shop), and can only be spent on that same shop's
-- rewards via a QR-confirmed redemption flow. Run this once against the live database
-- before uploading any of the Phase 1 code that depends on it.

CREATE TABLE user_shop_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  place_id INT NOT NULL,
  points INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_place (user_id, place_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
