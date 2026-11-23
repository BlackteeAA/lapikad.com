-- Student name/class/roll-number, collected inside the school-events ("เมนูนักเรียน")
-- module the first time a student opens it — kept separate from the account's
-- login/display name (users.name) since that's often a nickname. Used so staff
-- scanning a student's personal QR (event_staff_checkin.php) see who they really are.
-- Run this once against the live database.

ALTER TABLE users ADD COLUMN student_name VARCHAR(100) NULL AFTER name;
ALTER TABLE users ADD COLUMN class_room VARCHAR(20) NULL AFTER student_name;
ALTER TABLE users ADD COLUMN student_no VARCHAR(10) NULL AFTER class_room;
