-- School logo ("โลโก้โรงเรียน"), uploaded by a site admin (admin_schools.php) or the
-- school's teacher (school_members.php). Files live in www/assets/images/schools/.
-- Run this once, after sql/add_schools.sql.

ALTER TABLE schools ADD COLUMN logo_url VARCHAR(255) NULL AFTER province;
