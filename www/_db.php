<?php
require_once __DIR__ . "/_bootstrap.php";
require_once __DIR__ . "/_db_config.php";

$conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName);

if ($conn->connect_error) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");
$conn->query("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
$conn->query("SET CHARACTER SET utf8mb4");

// Default category used when a shop application doesn't specify one
// (admin_shop_requests.php). No longer drives the daily-refresh mechanic itself.
define("SHOP_QUEST_CATEGORY", "ร้านค้า/ร้านอาหาร");

// A place with a real owner (places.owner_user_id) is a shop: its quests can be
// redone once per calendar day instead of only once ever.
function isDailyRefreshQuest($ownerUserId) {
    return $ownerUserId !== null;
}

// Extracts the LAPIKAD target code from a raw scanned QR value: either a bare
// "LAPIKAD:CODE" string, a full URL with a ?code= query param, or a plain code.
function extractQrCode($rawCode) {
    $rawCode = trim($rawCode);

    if ($rawCode === "") {
        return "";
    }

    if (str_starts_with(strtoupper($rawCode), "LAPIKAD:")) {
        return strtoupper(str_replace("LAPIKAD:", "", $rawCode));
    }

    if (filter_var($rawCode, FILTER_VALIDATE_URL)) {
        $parts = parse_url($rawCode);

        if (isset($parts["query"])) {
            parse_str($parts["query"], $query);

            if (isset($query["code"])) {
                return strtoupper(trim($query["code"]));
            }
        }
    }

    return strtoupper($rawCode);
}

define("REDEMPTION_EXPIRY_MINUTES", 5);

function generateRedemptionCode() {
    return strtoupper(bin2hex(random_bytes(6)));
}

// If $row (a shop_redemptions row) is still 'pending' but past its expiry, refunds
// the reserved points/stock and marks it 'expired'. Returns the (possibly updated)
// status string. Safe to call repeatedly — a no-op once the row isn't pending.
function expireIfNeeded($conn, $row) {
    if ($row["status"] !== "pending") {
        return $row["status"];
    }
    if (strtotime($row["expires_at"]) > time()) {
        return "pending";
    }

    $conn->begin_transaction();
    try {
        $upd = $conn->prepare("UPDATE shop_redemptions SET status='expired' WHERE id=? AND status='pending'");
        $upd->bind_param("i", $row["id"]);
        $upd->execute();

        if ($upd->affected_rows > 0) {
            $refundPts = $conn->prepare("
                INSERT INTO user_shop_points (user_id, place_id, points) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE points = points + VALUES(points)
            ");
            $refundPts->bind_param("iii", $row["user_id"], $row["place_id"], $row["points_cost"]);
            $refundPts->execute();

            $refundStock = $conn->prepare("UPDATE rewards SET stock = stock + 1 WHERE id=?");
            $refundStock->bind_param("i", $row["reward_id"]);
            $refundStock->execute();
        }

        $conn->commit();
        return "expired";
    } catch (Exception $e) {
        $conn->rollback();
        return $row["status"];
    }
}

define("REMEMBER_COOKIE_DAYS", 30);

function clearRememberCookie() {
    setcookie("remember_token", "", ["expires" => time() - 3600, "path" => "/"]);
}

// Issues a fresh selector:validator remember-me token for $userId, stores the
// validator's hash (never the raw value) and sets the cookie.
function setRememberCookie($conn, $userId) {
    $selector  = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $hash      = hash("sha256", $validator);
    $expires   = date("Y-m-d H:i:s", time() + 86400 * REMEMBER_COOKIE_DAYS);

    $stmt = $conn->prepare("INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $userId, $selector, $hash, $expires);
    $stmt->execute();

    setcookie("remember_token", $selector . ":" . $validator, [
        "expires"  => time() + 86400 * REMEMBER_COOKIE_DAYS,
        "path"     => "/",
        "secure"   => !empty($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Lax",
    ]);
}

// If a valid remember-me cookie is present, logs the user in (rotating the
// token) and returns true. Otherwise clears any stale cookie and returns false.
function tryRememberLogin($conn) {
    if (empty($_COOKIE["remember_token"])) return false;

    $parts = explode(":", $_COOKIE["remember_token"], 2);
    if (count($parts) !== 2) { clearRememberCookie(); return false; }
    [$selector, $validator] = $parts;

    $stmt = $conn->prepare("
        SELECT rt.id, rt.user_id, rt.validator_hash, u.name, u.role, u.is_banned
        FROM remember_tokens rt JOIN users u ON u.id = rt.user_id
        WHERE rt.selector=? AND rt.expires_at > NOW()
    ");
    $stmt->bind_param("s", $selector);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row || !hash_equals($row["validator_hash"], hash("sha256", $validator)) || !empty($row["is_banned"])) {
        clearRememberCookie();
        if ($row) {
            $del = $conn->prepare("DELETE FROM remember_tokens WHERE id=?");
            $del->bind_param("i", $row["id"]);
            $del->execute();
        }
        return false;
    }

    $newValidator = bin2hex(random_bytes(32));
    $newHash      = hash("sha256", $newValidator);
    $expires      = date("Y-m-d H:i:s", time() + 86400 * REMEMBER_COOKIE_DAYS);
    $upd = $conn->prepare("UPDATE remember_tokens SET validator_hash=?, expires_at=? WHERE id=?");
    $upd->bind_param("ssi", $newHash, $expires, $row["id"]);
    $upd->execute();

    setcookie("remember_token", $selector . ":" . $newValidator, [
        "expires"  => time() + 86400 * REMEMBER_COOKIE_DAYS,
        "path"     => "/",
        "secure"   => !empty($_SERVER["HTTPS"]),
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_regenerate_id(true);
    $_SESSION["user_id"] = $row["user_id"];
    $_SESSION["name"]    = $row["name"];
    $_SESSION["role"]    = $row["role"];
    return true;
}
?>
