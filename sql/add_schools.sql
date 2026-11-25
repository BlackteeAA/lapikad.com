-- Schools ("โรงเรียน") with per-school roles, so each school's student council
-- ("สภานักเรียน") only sees and manages its own school's events and rewards.
--
--   users.school_id    which school the user belongs to (students pick it themselves
--                      in event_student_info.php; admins/teachers can also assign it)
--   users.school_role  'student'  นักเรียน       — check in to own school's events
--                      'council'  สภานักเรียน    — create/manage own school's events + school rewards
--                      'teacher'  ครู/ผู้ดูแล    — everything council can do + manage the school's members
--
-- events.school_id / rewards.school_id scope those rows to one school.
-- NULL keeps the old behaviour: an event visible to every school (admin-created),
-- or a reward in the admin's global bucket.
--
-- users.is_organizer is no longer read by the app. Existing organizers are migrated
-- to school_role='council' below, but they have no school yet — an admin must assign
-- each of them to a school in admin_users.php before they can create events again.
-- Run this once against the live database.

CREATE TABLE schools (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  district VARCHAR(100) NULL,
  province VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_school_name (name)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE users ADD COLUMN school_id INT NULL;
ALTER TABLE users ADD COLUMN school_role ENUM('student','council','teacher') NULL;
ALTER TABLE users ADD KEY idx_school (school_id);
ALTER TABLE users ADD FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE SET NULL;

ALTER TABLE events ADD COLUMN school_id INT NULL;
ALTER TABLE events ADD KEY idx_event_school (school_id);
ALTER TABLE events ADD FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

ALTER TABLE rewards ADD COLUMN school_id INT NULL;
ALTER TABLE rewards ADD FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE;

UPDATE users SET school_role = 'council' WHERE is_organizer = 1;
