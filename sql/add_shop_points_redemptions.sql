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

-- Replaces the old instant-redeem reward_redemptions table for shop-scoped rewards.
-- reward_redemptions itself is left untouched as historical data from the old flow.
CREATE TABLE shop_redemptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL,
  user_id INT NOT NULL,
  place_id INT NOT NULL,
  reward_id INT NOT NULL,
  points_cost INT NOT NULL,
  status ENUM('pending','completed','expired','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  confirmed_at TIMESTAMP NULL,
  confirmed_by INT NULL,
  UNIQUE KEY unique_code (code),
  KEY idx_place_status (place_id, status),
  KEY idx_user_status (user_id, status),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
  FOREIGN KEY (reward_id) REFERENCES rewards(id) ON DELETE CASCADE,
  FOREIGN KEY (confirmed_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
