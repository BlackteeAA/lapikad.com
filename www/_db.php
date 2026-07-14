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

// Places in this category are shops: their quests can be redone once per day
// instead of only once ever (places.category value, set in admin_places_add.php).
define("SHOP_QUEST_CATEGORY", "ร้านค้า/ร้านอาหาร");

function isDailyRefreshQuest($placeCategory) {
    return $placeCategory === SHOP_QUEST_CATEGORY;
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
