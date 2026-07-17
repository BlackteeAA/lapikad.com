<?php
require_once "_auth.php";
require_once "_claude.php";

header("Content-Type: application/json; charset=utf-8");

if (!csrf_verify()) {
    http_response_code(403);
    echo json_encode(["reply" => "คำขอไม่ถูกต้อง กรุณาโหลดหน้าใหม่แล้วลองอีกครั้ง"], JSON_UNESCAPED_UNICODE);
    exit;
}

$lastChat = $_SESSION["last_chat_attempt"] ?? 0;
if (time() - $lastChat < 5) {
    http_response_code(429);
    echo json_encode(["reply" => "พิมพ์เร็วไปหน่อยนะครับ รอสักครู่แล้วลองใหม่อีกครั้ง"], JSON_UNESCAPED_UNICODE);
    exit;
}
$_SESSION["last_chat_attempt"] = time();

$userMessage = trim($_POST["message"] ?? "");
$placeId = (isset($_POST["place_id"]) && $_POST["place_id"] !== "") ? intval($_POST["place_id"]) : null;
$lat = (isset($_POST["lat"]) && $_POST["lat"] !== "") ? floatval($_POST["lat"]) : null;
$lng = (isset($_POST["lng"]) && $_POST["lng"] !== "") ? floatval($_POST["lng"]) : null;

if ($userMessage === "") {
    echo json_encode(["reply" => "พิมพ์คำถามมาได้เลยครับ"], JSON_UNESCAPED_UNICODE);
    exit;
}

$contextText = "";
$placeCards = [];

if ($placeId !== null) {
    $stmt = $conn->prepare("SELECT id, name, description, category, district, province, location_text FROM places WHERE id=? AND is_active=1");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $place = $stmt->get_result()->fetch_assoc();

    if ($place) {
        $locParts = array_filter([$place["district"], $place["province"], $place["location_text"]]);

        $contextText .= "สถานที่: " . $place["name"] . "\n";
        if ($place["category"]) $contextText .= "หมวดหมู่: " . $place["category"] . "\n";
        if ($locParts) $contextText .= "พื้นที่: " . implode(" ", $locParts) . "\n";
        if ($place["description"]) $contextText .= "รายละเอียด: " . $place["description"] . "\n";

        $qs = $conn->prepare("SELECT title, description, quest_type, reward_points FROM quests WHERE place_id=?");
        $qs->bind_param("i", $placeId);
        $qs->execute();
        $quests = $qs->get_result()->fetch_all(MYSQLI_ASSOC);

        if ($quests) {
            $contextText .= "ภารกิจในสถานที่นี้:\n";
            foreach ($quests as $q) {
                $contextText .= "- " . $q["title"] . " (+" . $q["reward_points"] . " คะแนน) " . ($q["description"] ?: "") . "\n";
            }
        }
    }
} elseif ($lat !== null && $lng !== null) {
    $places = $conn->query("
        SELECT id, name, description, category, district, province, location_text, image_url, lat, lng
        FROM places
        WHERE is_active=1 AND lat IS NOT NULL AND lng IS NOT NULL
    ")->fetch_all(MYSQLI_ASSOC);

    foreach ($places as &$p) {
        $p["distance_km"] = haversineKm($lat, $lng, floatval($p["lat"]), floatval($p["lng"]));
    }
    unset($p);

    usort($places, fn($a, $b) => $a["distance_km"] <=> $b["distance_km"]);
    $nearest = array_slice($places, 0, 5);

    if ($nearest) {
        $contextText .= "สถานที่ใกล้ตำแหน่งผู้ใช้ (เรียงจากใกล้สุด):\n";
        foreach ($nearest as $p) {
            $locParts = array_filter([$p["district"], $p["province"], $p["location_text"]]);
            $contextText .= "- " . $p["name"] . " (" . ($p["category"] ?: "ไม่ระบุหมวดหมู่") . ") ห่างประมาณ "
                . round($p["distance_km"], 1) . " กม. " . implode(" ", $locParts) . "\n";
        }

        $placeCards = array_map(function ($p) {
            return [
                "id" => intval($p["id"]),
                "name" => $p["name"],
                "category" => $p["category"],
                "image_url" => $p["image_url"],
                "description" => $p["description"] ? mb_substr($p["description"], 0, 90, "UTF-8") : "",
                "distance_km" => round($p["distance_km"], 1),
            ];
        }, $nearest);
    }
}

if ($contextText === "") {
    $contextText = "ไม่มีข้อมูลสถานที่ในบริบทนี้";
}

$systemPrompt = "คุณชื่อ \"น้องพิกัด\" เป็น AI ไกด์นำเที่ยวของล่าพิกัด.com "
    . "ตอบจากข้อมูลสถานที่ที่ให้มาเท่านั้น ถ้าไม่มีข้อมูลให้บอกตามตรงว่าไม่ทราบ ตอบเป็นภาษาไทย กระชับ\n\n"
    . "ข้อมูลสถานที่:\n" . $contextText;

$reply = askClaude($systemPrompt, $userMessage);

echo json_encode(["reply" => $reply, "places" => $placeCards], JSON_UNESCAPED_UNICODE);
?>
