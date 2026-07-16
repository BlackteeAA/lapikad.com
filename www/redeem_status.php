<?php
require_once "_auth.php";

header("Content-Type: application/json");

$userId = intval($_SESSION["user_id"]);
$code   = strtoupper(trim($_GET["code"] ?? ""));

$stmt = $conn->prepare("SELECT * FROM shop_redemptions WHERE code=?");
$stmt->bind_param("s", $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || intval($row["user_id"]) !== $userId) {
    echo json_encode(["status" => "not_found"]);
    exit;
}

$status = expireIfNeeded($conn, $row);
echo json_encode(["status" => $status]);
?>
