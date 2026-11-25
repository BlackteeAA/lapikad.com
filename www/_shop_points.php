<?php
// Shop check-in points ("แต้มร้านค้า"). Requires sql/add_shop_checkins.sql.
//
//   check-in : customer scans the shop's table QR (shop_checkin.php) once a day
//              → customer +SHOP_CHECKIN_CUSTOMER_POINTS at that shop,
//                shop +SHOP_CHECKIN_SHOP_POINTS popularity
//   redeem   : shop scans the customer's personal QR (shop_deduct.php) and
//              deducts points for a reward the shop defined itself
//   ranking  : shops ordered by popularity points of the last SHOP_RANK_DAYS days
require_once __DIR__ . "/_events.php"; // _auth, siteBaseUrl(), qrImageUrl(), getEventToken()

define("SHOP_CHECKIN_CUSTOMER_POINTS", 10);
define("SHOP_CHECKIN_SHOP_POINTS", 10);
define("SHOP_RANK_DAYS", 30);
define("SHOP_TZ", "Asia/Bangkok");
// Personal QR shown in my_points.php for shops to scan.
define("CUSTOMER_QR_PREFIX", "LAPIKAD-ME:");

function shopToday() {
    return (new DateTime("now", new DateTimeZone(SHOP_TZ)))->format("Y-m-d");
}

function normalizeShopToken($raw) {
    $raw = strtoupper(trim((string)$raw));
    return preg_match('/^[0-9A-F]{20}$/', $raw) ? $raw : "";
}

// The shop's table QR token, created on first use.
function ensureShopCheckinToken($conn, $place) {
    if (!empty($place["checkin_token"])) return $place["checkin_token"];
    return regenerateShopCheckinToken($conn, intval($place["id"]), true);
}

// New token (old printed QR stops working). $onlyIfEmpty avoids overwriting a
// token another request created at the same moment.
function regenerateShopCheckinToken($conn, $placeId, $onlyIfEmpty = false) {
    for ($i = 0; $i < 5; $i++) {
        $token = strtoupper(bin2hex(random_bytes(10)));
        $sql = "UPDATE places SET checkin_token=? WHERE id=?" . ($onlyIfEmpty ? " AND checkin_token IS NULL" : "");
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $token, $placeId);
        try {
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            continue; // unique collision, retry
        }
        if ($stmt->affected_rows > 0) return $token;
        if ($onlyIfEmpty) {
            $r = $conn->query("SELECT checkin_token FROM places WHERE id=" . intval($placeId))->fetch_assoc();
            if ($r && !empty($r["checkin_token"])) return $r["checkin_token"];
        }
    }
    return null;
}

function shopCheckinUrl($token) {
    return siteBaseUrl() . "/shop_checkin.php?s=" . $token;
}

function getShopByCheckinToken($conn, $token) {
    $stmt = $conn->prepare("SELECT * FROM places WHERE checkin_token=? AND owner_user_id IS NOT NULL");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getShopPlace($conn, $placeId) {
    $stmt = $conn->prepare("SELECT * FROM places WHERE id=? AND owner_user_id IS NOT NULL");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getShopBalance($conn, $userId, $placeId) {
    $stmt = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=?");
    $stmt->bind_param("ii", $userId, $placeId);
    $stmt->execute();
    $r = $stmt->get_result()->fetch_assoc();
    return $r ? intval($r["points"]) : 0;
}

// Returns "ok", "already" (checked in today), "own_shop", "inactive" or "error".
function shopCheckin($conn, $userId, $place) {
    if (intval($place["owner_user_id"]) === intval($userId)) return "own_shop";
    if (isset($place["is_active"]) && !intval($place["is_active"])) return "inactive";

    $placeId  = intval($place["id"]);
    $pts      = SHOP_CHECKIN_CUSTOMER_POINTS;
    $shopPts  = SHOP_CHECKIN_SHOP_POINTS;
    $today    = shopToday();
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("
            INSERT IGNORE INTO shop_point_txns (user_id, place_id, kind, points, shop_points, checkin_date)
            VALUES (?, ?, 'checkin', ?, ?, ?)
        ");
        $stmt->bind_param("iiiis", $userId, $placeId, $pts, $shopPts, $today);
        if (!$stmt->execute()) throw new Exception($conn->error);
        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            return "already";
        }
        $stmt = $conn->prepare("
            INSERT INTO user_shop_points (user_id, place_id, points) VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE points = points + VALUES(points)
        ");
        $stmt->bind_param("iii", $userId, $placeId, $pts);
        if (!$stmt->execute()) throw new Exception($conn->error);
        $conn->commit();
        return "ok";
    } catch (Exception $e) {
        $conn->rollback();
        return "error";
    }
}

// Shop deducts a customer's points. Returns "ok", "insufficient", "invalid" or "error".
function redeemShopPoints($conn, $placeId, $userId, $points, $note, $byUserId) {
    $points = intval($points);
    if ($points <= 0) return "invalid";
    $note = mb_substr(trim((string)$note), 0, 150, "UTF-8");
    $note = $note !== "" ? $note : null;

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=? FOR UPDATE");
        $stmt->bind_param("ii", $userId, $placeId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!$row || intval($row["points"]) < $points) {
            $conn->rollback();
            return "insufficient";
        }
        $stmt = $conn->prepare("UPDATE user_shop_points SET points = points - ? WHERE user_id=? AND place_id=?");
        $stmt->bind_param("iii", $points, $userId, $placeId);
        if (!$stmt->execute()) throw new Exception($conn->error);

        $neg = -$points;
        $stmt = $conn->prepare("
            INSERT INTO shop_point_txns (user_id, place_id, kind, points, shop_points, note, by_user_id)
            VALUES (?, ?, 'redeem', ?, 0, ?, ?)
        ");
        $stmt->bind_param("iiisi", $userId, $placeId, $neg, $note, $byUserId);
        if (!$stmt->execute()) throw new Exception($conn->error);
        $conn->commit();
        return "ok";
    } catch (Exception $e) {
        $conn->rollback();
        return "error";
    }
}

// Active shops ordered by popularity points of the last SHOP_RANK_DAYS days
// (ties: more check-ins ever, then oldest shop first). $limit 0 = all.
function getPopularShops($conn, $limit = 0) {
    $days = SHOP_RANK_DAYS;
    $sql = "
        SELECT p.*,
               COALESCE(SUM(CASE WHEN t.created_at >= NOW() - INTERVAL $days DAY THEN t.shop_points END), 0) AS score,
               COALESCE(SUM(CASE WHEN t.created_at >= NOW() - INTERVAL $days DAY THEN 1 END), 0) AS recent_checkins,
               COUNT(t.id) AS total_checkins
        FROM places p
        LEFT JOIN shop_point_txns t ON t.place_id = p.id AND t.kind = 'checkin'
        WHERE p.owner_user_id IS NOT NULL AND p.is_active = 1
        GROUP BY p.id
        ORDER BY score DESC, total_checkins DESC, p.id ASC
    " . ($limit > 0 ? " LIMIT " . intval($limit) : "");
    try {
        return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        return []; // migration not run yet
    }
}

// ["rank" => n (1-based) or null, "total" => shops, "score", "recent_checkins"]
function getShopRank($conn, $placeId) {
    $all = getPopularShops($conn);
    foreach ($all as $i => $s) {
        if (intval($s["id"]) === intval($placeId)) {
            return ["rank" => $i + 1, "total" => count($all), "score" => intval($s["score"]), "recent_checkins" => intval($s["recent_checkins"])];
        }
    }
    return ["rank" => null, "total" => count($all), "score" => 0, "recent_checkins" => 0];
}

// ── Reward images (assets/images/rewards/) ──
define("SHOP_REWARD_IMG_MAX_MB", 5);

// Saves an uploaded reward image. Returns [error message or null, url or null];
// [null, null] when no file was chosen.
function saveShopRewardImage($file, $placeId) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) return [null, null];
    if ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) return [uploadTooLargeMessage(), null];
    if ($file["error"] !== UPLOAD_ERR_OK) return ["อัปโหลดรูปไม่สำเร็จ (รหัสข้อผิดพลาด " . intval($file["error"]) . ")", null];
    if ($file["size"] > SHOP_REWARD_IMG_MAX_MB * 1024 * 1024) return ["รูปต้องไม่เกิน " . SHOP_REWARD_IMG_MAX_MB . "MB", null];

    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"], true) || @getimagesize($file["tmp_name"]) === false) {
        return ["ต้องเป็นไฟล์รูป (jpg, png, webp, gif)", null];
    }
    $dir = __DIR__ . "/assets/images/rewards/";
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("saveShopRewardImage: directory not writable: $dir");
        return ["บันทึกรูปไม่ได้: โฟลเดอร์ assets/images/rewards/ ไม่มีสิทธิ์เขียน (แจ้งผู้ดูแลเซิร์ฟเวอร์)", null];
    }
    $fname = "shop" . intval($placeId) . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    if (!move_uploaded_file($file["tmp_name"], $dir . $fname)) return ["บันทึกรูปไม่สำเร็จ", null];
    return [null, "assets/images/rewards/" . $fname];
}

// Only ever deletes files inside assets/images/rewards/.
function deleteShopRewardImage($url) {
    if ($url && preg_match('#^assets/images/rewards/[A-Za-z0-9_.-]+$#', $url)) {
        @unlink(__DIR__ . "/" . $url);
    }
}

// Square thumbnail of a reward's image, or "" when it has none.
function rewardThumbHtml($rw, $size = 52) {
    if (empty($rw["image_url"])) return "";
    $s = intval($size);
    return '<img class="sp-rw-img" src="' . e($rw["image_url"]) . '" alt="" loading="lazy" style="width:' . $s . 'px;height:' . $s . 'px">';
}

// Rewards the shop defined itself (rewards.place_id).
function getShopRewards($conn, $placeId) {
    $stmt = $conn->prepare("SELECT id, name, description, cost_points, image_url FROM rewards WHERE place_id=? ORDER BY cost_points ASC, id ASC");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Customer's personal QR content (same token as the school QR, users.event_token).
function customerQrData($conn, $userId) {
    $token = getEventToken($conn, $userId);
    return $token ? CUSTOMER_QR_PREFIX . $token : null;
}

// Token from a scanned customer QR ("LAPIKAD-ME:…", the school "LAPIKAD-STU:…", or bare).
function extractCustomerToken($raw) {
    $raw = strtoupper(trim((string)$raw));
    if (str_starts_with($raw, CUSTOMER_QR_PREFIX)) $raw = substr($raw, strlen(CUSTOMER_QR_PREFIX));
    return extractStudentToken($raw);
}

function findCustomerByToken($conn, $token) {
    if ($token === "") return null;
    $stmt = $conn->prepare("SELECT id, name, avatar_url, is_banned FROM users WHERE event_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $u = $stmt->get_result()->fetch_assoc();
    return $u && empty($u["is_banned"]) ? $u : null;
}

function shopImageStyle($place) {
    return !empty($place["image_url"])
        ? 'background-image:url("' . e($place["image_url"]) . '")'
        : 'background:linear-gradient(135deg,#f59e0b,#ef4444)';
}
