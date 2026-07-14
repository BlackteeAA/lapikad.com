-- Lets quests at shops (places.category = 'ร้านค้า/ร้านอาหาร') be completed once per
-- calendar day instead of only once ever. Tourist/other categories are unaffected
-- and remain a single lifetime completion. Run this once against the live database.

ALTER TABLE user_quests ADD INDEX idx_user_quests_user (user_id);
ALTER TABLE user_quests DROP INDEX unique_user_quest;
ALTER TABLE user_quests ADD COLUMN completed_date DATE NULL;
UPDATE user_quests SET completed_date = DATE(completed_at) WHERE completed_date IS NULL;
ALTER TABLE user_quests MODIFY COLUMN completed_date DATE NOT NULL;
ALTER TABLE user_quests ADD UNIQUE KEY unique_user_quest_day (user_id, quest_id, completed_date);
