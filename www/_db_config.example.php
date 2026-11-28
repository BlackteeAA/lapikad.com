<?php
// Template — copy this file to _db_config.php and fill in real credentials.
// _db_config.php is gitignored and must never be committed.
//
// Only needed when the environment doesn't already carry the credentials.
// _db.php reads this file first and falls back to the DB_HOST / DB_USER /
// DB_PASS / DB_NAME environment variables, which docker-compose.yml sets — so
// `docker compose up` needs no copy of this file at all. On cPanel or any other
// shared host, where you can't set env vars for PHP, copy it and fill it in.
$dbHost = "localhost";
$dbUser = "your_db_user";
$dbPass = "your_db_password";
$dbName = "your_db_name";
?>
