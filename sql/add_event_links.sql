-- Check-in links / QR codes for an event ("ลิงก์ / QR เช็คชื่อ").
-- A council member (or teacher/admin) creates a link in event_admin.php, optionally
-- with an expiry time, and shares it as a URL or QR. A student who opens it
-- (event_link.php?k=<token>) is checked in and gets the event's points, provided
-- they already belong to the event's school and check-in is open.
--
-- expires_at is a Unix timestamp (seconds), not DATETIME, so it means the same
-- moment whatever timezone PHP or MySQL runs in. NULL = never expires.
-- Requires sql/add_school_events.sql and sql/add_schools.sql. Run this once.

CREATE TABLE event_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  token CHAR(20) NOT NULL,
  label VARCHAR(100) NULL,
  expires_at INT UNSIGNED NULL,
  created_by INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_link_token (token),
  KEY idx_link_event (event_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Remember which link each check-in came through (for the "used by N students" count).
ALTER TABLE event_attendances
  MODIFY method ENUM('staff_scan','self_scan','manual','link') NOT NULL;
ALTER TABLE event_attendances ADD COLUMN link_id INT NULL;
ALTER TABLE event_attendances
  ADD FOREIGN KEY (link_id) REFERENCES event_links(id) ON DELETE SET NULL;
