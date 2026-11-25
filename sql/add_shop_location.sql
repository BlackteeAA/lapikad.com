-- Province / district on shop applications (shop_apply.php), copied into
-- places.province / places.district when the admin approves the request.
-- places already has these columns; existing shops set theirs in shop_location.php.
ALTER TABLE shop_requests ADD COLUMN province VARCHAR(100) NULL;
ALTER TABLE shop_requests ADD COLUMN district VARCHAR(100) NULL;
