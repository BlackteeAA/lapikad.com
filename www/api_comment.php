<?php
require_once "_auth.php";
header("Content-Type: application/json");

$userId = intval($_SESSION["user_id"]);
$action = $_REQUEST["action"] ?? "";

if ($action === "get") {
    $postId = intval($_GET["post_id"] ?? 0);
    $rows   = $conn->query("
        SELECT c.content, c.created_at, u.name
        FROM post_comments c
        JOIN users u ON u.id = c.user_id
        WHERE c.post_id = $postId
        ORDER BY c.created_at ASC
        LIMIT 50
    ")->fetch_all(MYSQLI_ASSOC);
    echo json_encode($rows);
    exit;
}

if ($action === "add") {
    if (!csrf_verify()) { echo json_encode(["error"=>"invalid"]); exit; }
    $postId  = intval($_POST["post_id"] ?? 0);
    $content = trim($_POST["content"] ?? "");
    if (!$postId || $content === "") { echo json_encode(["error"=>"empty"]); exit; }
    $stmt = $conn->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $postId, $userId, $content);
    $stmt->execute();
    $count = $conn->query("SELECT COUNT(*) AS c FROM post_comments WHERE post_id=$postId")->fetch_assoc()["c"];
    echo json_encode(["ok"=>true, "count"=>$count, "name"=>$_SESSION["name"], "content"=>$content]);
    exit;
}

echo json_encode(["error"=>"unknown"]);
