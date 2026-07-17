<?php
$sql = "SELECT id FROM users UNION SELECT id FROM users WHERE status = 'completed'";
echo "no crash, sql var length: " . strlen($sql);
