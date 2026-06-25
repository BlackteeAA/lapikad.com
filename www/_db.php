<?php
require_once __DIR__ . "/_bootstrap.php";

$host   = "localhost";
$user   = "a6ddp1n_lapikad";
$pass   = "FpC)3uAtcc19";
$dbname = "a6ddp1n_lapikad";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("SET CHARACTER SET utf8mb4");
?>
