<?php
require_once "_auth.php";
require_once "_claude.php";

header("Content-Type: application/json; charset=utf-8");

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(["reply" => "คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง", "quests" => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$lastAttempt = $_SESSION["last_quest_recommend"] ?? 0;
if (time() - $lastAttempt < 5) {
    http_response_code(429);
    echo json_encode(["reply" => "ขอเวลาสักครู่แล้วลองใหม่อีกครั้งนะครับ", "quests" => []], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION["last_quest_recommend"] = time();

$userId = intval($_SESSION["user_id"]);
$lat = (isset($_POST["lat"]) && $_POST["lat"] !== "") ? floatval($_POST["lat"]) : null;
$lng = (isset($_POST["lng"]) && $_POST["lng"] !== "") ? floatval($_POST["lng"]) : null;

$stmt = $conn->prepare("SELECT points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$userRow = $stmt->get_result()->fetch_assoc();
$points = intval($userRow["points"] ?? 0);

// Overall progress stats (same daily-refresh join pattern as places.php).
$totalRow = $conn->query("SELECT COUNT(*) AS total FROM quests q JOIN places p ON p.id = q.place_id WHERE p.is_active = 1")->fetch_assoc();
$totalQuests = intval($totalRow["total"] ?? 0);

$doneStmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.id) AS done
    FROM quests q
    JOIN places p ON p.id = q.place_id
    JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ?
        AND (p.owner_user_id IS NULL OR uq.completed_date = CURDATE())
    WHERE p.is_active = 1
");
$doneStmt->bind_param("i", $userId);
$doneStmt->execute();
$doneQuests = intval($doneStmt->get_result()->fetch_assoc()["done"] ?? 0);

// Quests the user hasn't completed yet (same daily-refresh logic as places.php:
// shop-owned places reset per calendar day, everything else is once-ever).
$stmt = $conn->prepare("
    SELECT q.id AS quest_id, q.title, q.reward_points,
           p.id AS place_id, p.name AS place_name, p.description, p.category,
           p.image_url, p.lat, p.lng, p.district, p.province, p.location_text
    FROM quests q
    JOIN places p ON p.id = q.place_id
    LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ?
        AND (p.owner_user_id IS NULL OR uq.completed_date = CURDATE())
    WHERE p.is_active = 1 AND uq.id IS NULL
    ORDER BY q.id
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($rows as &$r) {
    if ($lat !== null && $lng !== null && $r["lat"] !== null && $r["lng"] !== null) {
        $r["distance_km"] = round(haversineKm($lat, $lng, floatval($r["lat"]), floatval($r["lng"])), 1);
    } else {
        $r["distance_km"] = null;
    }
}
unset($r);

if ($lat !== null && $lng !== null) {
    usort($rows, function ($a, $b) {
        if ($a["distance_km"] === null) return 1;
        if ($b["distance_km"] === null) return -1;
        return $a["distance_km"] <=> $b["distance_km"];
    });
}

$candidates = array_slice($rows, 0, 8);

if (!$candidates) {
    echo json_encode([
        "reply" => "ตอนนี้คุณทำภารกิจครบทุกที่ที่มีแล้วครับ รอภารกิจใหม่เร็วๆ นี้นะครับ",
        "quests" => [],
        "points" => $points,
        "done_quests" => $doneQuests,
        "total_quests" => $totalQuests,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$contextLines = [];
foreach ($candidates as $c) {
    $distText = $c["distance_km"] !== null ? $c["distance_km"] . " กม." : "ไม่ทราบระยะทาง";
    $contextLines[] = "- ภารกิจ \"" . $c["title"] . "\" ที่สถานที่ \"" . $c["place_name"] . "\" (place_id=" . $c["place_id"] . ") "
        . "ได้ +" . $c["reward_points"] . " คะแนน ห่างประมาณ " . $distText;
}

$systemPrompt = "คุณชื่อ \"น้องพิกัด\" เป็น AI ผู้ช่วยของล่าพิกัด.com หน้าที่คือช่วยแนะนำว่าผู้ใช้ควรไปทำภารกิจไหนต่อ "
    . "จากรายการภารกิจที่ยังไม่ได้ทำที่ให้มาเท่านั้น ห้ามคำนวณระยะทางเอง ให้ใช้ระยะทางที่คำนวณมาให้แล้วในรายการเท่านั้น "
    . "ผู้ใช้มีคะแนนสะสมอยู่ " . $points . " คะแนน ตอบเป็นภาษาไทย กระชับ อธิบายสั้นๆ ว่าทำไมถึงแนะนำภารกิจที่เลือก\n\n"
    . "รายการภารกิจที่ยังไม่ได้ทำ:\n" . implode("\n", $contextLines);

// Reuse the last reply for ~60s if nothing that would change the recommendation
// has changed (same points, same candidate quests) — avoids paying for a fresh
// blocking Claude call on reloads / duplicate tabs with an identical context.
$cacheKey = md5($points . "|" . implode(",", array_column($candidates, "quest_id")));
$cached   = $_SESSION["quest_recommend_cache"] ?? null;
if ($cached && $cached["key"] === $cacheKey && (time() - $cached["time"]) < 60) {
    $reply = $cached["reply"];
} else {
    $reply = askClaude($systemPrompt, "ช่วยแนะนำภารกิจที่ควรไปทำต่อไปให้หน่อย พร้อมเหตุผลสั้นๆ");
    $_SESSION["quest_recommend_cache"] = ["key" => $cacheKey, "reply" => $reply, "time" => time()];
}

echo json_encode([
    "reply" => $reply,
    "points" => $points,
    "done_quests" => $doneQuests,
    "total_quests" => $totalQuests,
    "quests" => array_map(function ($c) {
        return [
            "quest_id" => intval($c["quest_id"]),
            "title" => $c["title"],
            "reward_points" => intval($c["reward_points"]),
            "place_id" => intval($c["place_id"]),
            "place_name" => $c["place_name"],
            "description" => $c["description"] ? mb_substr($c["description"], 0, 90, "UTF-8") : "",
            "category" => $c["category"],
            "image_url" => $c["image_url"],
            "lat" => $c["lat"] !== null ? floatval($c["lat"]) : null,
            "lng" => $c["lng"] !== null ? floatval($c["lng"]) : null,
            "distance_km" => $c["distance_km"],
        ];
    }, $candidates),
], JSON_UNESCAPED_UNICODE);
?>
