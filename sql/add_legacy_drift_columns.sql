-- Columns the app reads that were ALTERed into production by hand and never
-- captured in a migration file. See the NOTE in sql/add_shop_role.sql.
--
-- Run this on a database built from init.sql (a fresh dev/test machine). On the
-- live database every column here already exists, so each statement fails with
-- "Duplicate column name" — that is expected and harmless; run the file
-- statement by statement there, or skip it entirely.
--
-- Without it, rename_shop_category.sql fails with
--   ERROR 1054: Unknown column 'category' in 'where clause'
-- and most of the site errors out as soon as it touches a place.

-- places: category + photo + map pin + Thai address, used by places.php,
-- place.php, admin_places*.php, the shop flow and the location report.
ALTER TABLE places ADD COLUMN category VARCHAR(100) NULL;
ALTER TABLE places ADD COLUMN image_url VARCHAR(255) NULL;
ALTER TABLE places ADD COLUMN lat DECIMAL(10,7) NULL;
ALTER TABLE places ADD COLUMN lng DECIMAL(10,7) NULL;
ALTER TABLE places ADD COLUMN district VARCHAR(100) NULL;
ALTER TABLE places ADD COLUMN province VARCHAR(100) NULL;

-- users: profile picture and bio (profile.php, user_profile.php, community.php).
ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN bio VARCHAR(300) NULL;

-- user_quests: photo a user attached when completing a quest
-- (quest.php uploads it, admin_quests.php shows the gallery).
ALTER TABLE user_quests ADD COLUMN photo_url VARCHAR(255) NULL;
