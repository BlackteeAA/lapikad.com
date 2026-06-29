<?php
require_once "_auth.php";
header("Content-Type: application/json");

if (!csrf_verify()) { echo json_encode(["error"=>"invalid"]); exit; }

$userId = intval($_SESSION["user_id"]);
$postId = intval($_POST["post_id"] ?? 0);

if (!$postId) { echo json_encode(["error"=>"invalid"]); exit; }

$stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id=? AND user_id=?");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
$exists = $stmt->get_result()->fetch_assoc();

if ($exists) {
    $stmt = $conn->prepare("DELETE FROM post_likes WHERE post_id=? AND user_id=?");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $liked = false;
} else {
    $stmt = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $liked = true;
}

$count = $conn->query("SELECT COUNT(*) AS c FROM post_likes WHERE post_id=$postId")->fetch_assoc()["c"];
echo json_encode(["liked" => $liked, "count" => $count]);
