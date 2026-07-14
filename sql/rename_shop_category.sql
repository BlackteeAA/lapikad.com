-- Renames the "ร้านอาหาร" place category to "ร้านค้า/ร้านอาหาร" so existing
-- shop places keep matching SHOP_QUEST_CATEGORY in www/_db.php and keep their
-- daily-refresh quest behavior. Run this once against the live database.

UPDATE places SET category = 'ร้านค้า/ร้านอาหาร' WHERE category = 'ร้านอาหาร';
