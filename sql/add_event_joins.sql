-- "เข้างาน": a student who opens a council check-in link / QR (event_link.php) joins
-- the event first. Only joined students can then get the event's points, by
-- scanning the event's on-screen QR from inside the event page (event_room.php)
-- or by showing their personal QR to the council.
-- Requires sql/add_event_links.sql. Run this once.

CREATE TABLE event_joins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  event_id INT NOT NULL,
  user_id INT NOT NULL,
  link_id INT NULL,
  joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_event_join (event_id, user_id),
  KEY idx_join_user (user_id),
  FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (link_id) REFERENCES event_links(id) ON DELETE SET NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
