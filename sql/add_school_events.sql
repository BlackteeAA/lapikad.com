-- School events / attendance ("กิจกรรมโรงเรียน"): a separate line from places/quests.
-- Organizers (e.g. student council) create events and check students in either by
--   1) scanning each student's personal QR (users.event_token), or
--   2) showing a rotating event QR that students scan themselves.
-- Run this once against the live database.

ALTER TABLE users ADD COLUMN is_organizer TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN event_token CHAR(16) NULL;
ALTER TABLE users ADD UNIQUE KEY unique_event_token (event_token);

CREATE TABLE events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT,
  location_text VARCHAR(255),
  starts_at DATETIME NULL,
  reward_points INT NOT NULL DEFAULT 0,
  allow_self_checkin TINYINT(1) NOT NULL DEFAULT 1,
  is_open TINYINT(1) NOT NULL DEFAULT 0,
  qr_secret CHAR(32) NOT NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_starts (starts_at),
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Extra helpers (other council members) who may scan for an event.
CREATE TABLE event_staff (
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  PRIMARY KEY (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE event_attendances (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  method ENUM('staff_scan','self_scan','manual') NOT NULL,
  checked_in_by INT NULL,
  checked_in_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_user (event_id, user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (checked_in_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
