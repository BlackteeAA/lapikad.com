<?php
require_once __DIR__ . "/_bootstrap.php";

// Credentials come from one of two places, in this order:
//
//   1. _db_config.php — a file next to this one, copied from
//      _db_config.example.php. This is how cPanel/shared hosting is set up.
//   2. The DB_HOST / DB_USER / DB_PASS / DB_NAME environment variables, which
//      docker-compose.yml passes into the container.
//
// Before, only (1) was read while compose only set (2), so the compose values
// looked meaningful but were ignored and the container needed a hand-copied
// file anyway. Either source works now, and a partial config file can be
// topped up from the environment.
if (is_file(__DIR__ . "/_db_config.php")) {
    require_once __DIR__ . "/_db_config.php";
}

// Parentheses are required: PHP 8 refuses to parse ?? mixed with ?: without them.
$dbHost = $dbHost ?? (getenv("DB_HOST") ?: "localhost");
$dbUser = $dbUser ?? (getenv("DB_USER") ?: "");
$dbPass = $dbPass ?? (getenv("DB_PASS") ?: "");
$dbName = $dbName ?? (getenv("DB_NAME") ?: "");

if ($dbUser === "" || $dbName === "") {
    error_log("_db.php: no database credentials — copy www/_db_config.example.php to www/_db_config.php, or set DB_USER/DB_NAME in the environment");
    die("Database is not configured.");
}

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

// Straight-line (Haversine) distance between two lat/lng points, in meters.
function haversineMeters($lat1, $lng1, $lat2, $lng2) {
    $R = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $R * $c;
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
    csrf_rotate();
    $_SESSION["user_id"] = $row["user_id"];
    $_SESSION["name"]    = $row["name"];
    $_SESSION["role"]    = $row["role"];
    return true;
}
?>
