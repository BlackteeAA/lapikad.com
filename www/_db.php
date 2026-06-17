<?php
require_once __DIR__ . "/_bootstrap.php";

$host = "db";
$user = "questtrip";
$pass = "questtrip";
$dbname = "questtrip";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("SET CHARACTER SET utf8mb4");
?>
