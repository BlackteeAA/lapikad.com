<?php
require_once "_auth.php";
header("Content-Type: application/json");

if (!csrf_verify()) { echo json_encode(["error"=>"invalid"]); exit; }

$userId   = intval($_SESSION["user_id"]);
$targetId = intval($_POST["target_id"] ?? 0);

if (!$targetId || $targetId === $userId) { echo json_encode(["error"=>"invalid"]); exit; }

$stmt = $conn->prepare("SELECT id FROM user_follows WHERE follower_id=? AND following_id=?");
$stmt->bind_param("ii", $userId, $targetId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();

if ($exists) {
    $stmt = $conn->prepare("DELETE FROM user_follows WHERE follower_id=? AND following_id=?");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();
    $following = false;
} else {
    $stmt = $conn->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $targetId);
    $stmt->execute();
    $following = true;
}

echo json_encode(["following" => $following]);
