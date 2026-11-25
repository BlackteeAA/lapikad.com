-- Shop check-in points ("แต้มร้านค้า"), replacing the old shop reward flow.
--
--   * A customer scans the QR printed on a partner shop's table
--     (shop_checkin.php?s=<places.checkin_token>), at most once per shop per day.
--     The customer gets points at THAT shop (user_shop_points), and the shop gets
--     popularity points. Shops with the most popularity points in the last 30 days
--     are shown first on the site ("ร้านดังตอนนี้").
--   * Each shop defines its own rewards (rewards.place_id). To redeem, the shop
--     scans the customer's personal QR and deducts points (shop_deduct.php).
--
-- Amounts live in www/_shop_points.php (SHOP_CHECKIN_* constants).
-- Run this once against the live database before uploading the new code.

ALTER TABLE places ADD COLUMN checkin_token CHAR(20) NULL;
ALTER TABLE places ADD UNIQUE KEY unique_checkin_token (checkin_token);

-- Per-(customer, shop) balance. Already created by add_shop_points_redemptions.sql
-- on most databases; created here if it isn't.
CREATE TABLE IF NOT EXISTS user_shop_points (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  place_id INT NOT NULL,
  points INT NOT NULL DEFAULT 0,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_place (user_id, place_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Every check-in (+points) and redemption (-points), per customer and shop.
-- checkin_date is set only for check-ins, so the unique key allows one check-in
-- per customer per shop per day while redemptions (NULL date) are unlimited.
CREATE TABLE shop_point_txns (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  place_id INT NOT NULL,
  kind ENUM('checkin','redeem') NOT NULL,
  points INT NOT NULL,
  shop_points INT NOT NULL DEFAULT 0,
  note VARCHAR(150) NULL,
  by_user_id INT NULL,
  checkin_date DATE NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_daily_checkin (user_id, place_id, checkin_date),
  KEY idx_place_time (place_id, kind, created_at),
  KEY idx_user_time (user_id, created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (place_id) REFERENCES places(id) ON DELETE CASCADE,
  FOREIGN KEY (by_user_id) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
