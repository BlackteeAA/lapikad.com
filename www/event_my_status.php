<?php
// Polled by event_my_qr.php: returns the user's newest attendance after ?after=<id>.
require_once "_events.php";

header("Content-Type: application/json; charset=utf-8");

$userId = intval($_SESSION["user_id"]);
$after  = intval($_GET["after"] ?? 0);

$stmt = $conn->prepare("
    SELECT a.id, e.title, e.reward_points
    FROM event_attendances a JOIN events e ON e.id = a.event_id
    WHERE a.user_id=? AND a.id > ?
    ORDER BY a.id DESC LIMIT 1
");
$stmt->bind_param("ii", $userId, $after);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

echo json_encode($row
    ? ["id" => intval($row["id"]), "title" => $row["title"], "points" => intval($row["reward_points"])]
    : ["id" => 0], JSON_UNESCAPED_UNICODE);
