-- Removes the regular (shop / global) reward system.
-- School rewards (rewards.school_id IS NOT NULL, managed in council_rewards.php) are KEPT,
-- so the `rewards` table itself stays.
-- Run once against the live database AFTER uploading the new code.
--
-- Points are NOT touched: users.points, point_logs, user_shop_points,
-- quests.reward_points and events.reward_points all stay.

DROP TABLE IF EXISTS shop_redemptions;

-- Shop rewards and old global rewards (no school). School rewards are left alone.
DELETE FROM rewards WHERE school_id IS NULL;
