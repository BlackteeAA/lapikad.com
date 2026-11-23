<?php
// Shared helpers for the school events / attendance line ("กิจกรรมโรงเรียน").
// Requires sql/add_school_events.sql.
require_once __DIR__ . "/_auth.php";

// How often the self check-in QR on the display screen rotates.
define("EVENT_QR_SLOT_SECONDS", 30);
// How many past slots are still accepted (covers slow phones / page lag).
define("EVENT_QR_GRACE_SLOTS", 2);
// Prefix of the personal QR a student shows to staff.
define("EVENT_STUDENT_QR_PREFIX", "LAPIKAD-STU:");

// Admins and users flagged is_organizer may create events.
function isEventOrganizer($conn, $userId) {
    if (($_SESSION["role"] ?? "") === "admin") return true;
    $stmt = $conn->prepare("SELECT is_organizer FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row && !empty($row["is_organizer"]);
}

// Has this student already filled in their real name/class/roll-number?
// Collected inside the events module itself (event_student_info.php), separate
// from the account's login name (users.name), which is often just a nickname.
function hasStudentInfo($conn, $userId) {
    $stmt = $conn->prepare("SELECT student_name, class_room, student_no FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row
        && trim($row["student_name"] ?? "") !== ""
        && trim($row["class_room"] ?? "") !== ""
        && trim($row["student_no"] ?? "") !== "";
}

function getEvent($conn, $eventId) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// 'owner' (creator or admin: full control), 'staff' (may scan + view list), or null.
function eventRole($conn, $event, $userId) {
    if (!$event) return null;
    if (($_SESSION["role"] ?? "") === "admin") return "owner";
    if ($event["created_by"] !== null && intval($event["created_by"]) === intval($userId)) return "owner";

    $stmt = $conn->prepare("SELECT 1 FROM event_staff WHERE event_id=? AND user_id=?");
    $stmt->bind_param("ii", $event["id"], $userId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ? "staff" : null;
}

// Returns the user's personal attendance token, creating it on first use.
function getEventToken($conn, $userId) {
    $stmt = $conn->prepare("SELECT event_token FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && !empty($row["event_token"])) return $row["event_token"];

    for ($i = 0; $i < 5; $i++) {
        $token = strtoupper(bin2hex(random_bytes(8)));
        $upd = $conn->prepare("UPDATE users SET event_token=? WHERE id=? AND event_token IS NULL");
        $upd->bind_param("si", $token, $userId);
        try {
            if ($upd->execute() && $upd->affected_rows > 0) return $token;
        } catch (mysqli_sql_exception $e) {
            continue; // unique collision, retry
        }
        // Someone else set it concurrently; re-read.
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row && !empty($row["event_token"])) return $row["event_token"];
    }
    return null;
}

// Pulls the student token out of a scanned value ("LAPIKAD-STU:XXXX" or bare token).
function extractStudentToken($raw) {
    $raw = strtoupper(trim($raw));
    if (str_starts_with($raw, EVENT_STUDENT_QR_PREFIX)) {
        $raw = substr($raw, strlen(EVENT_STUDENT_QR_PREFIX));
    }
    return preg_match('/^[0-9A-F]{16}$/', $raw) ? $raw : "";
}

function eventQrSlot() {
    return intdiv(time(), EVENT_QR_SLOT_SECONDS);
}

function eventQrToken($event, $slot) {
    return strtoupper(substr(hash_hmac("sha256", $event["id"] . ":" . $slot, $event["qr_secret"]), 0, 10));
}

function isValidEventQrToken($event, $token) {
    $token = strtoupper(trim($token));
    if ($token === "") return false;
    $now = eventQrSlot();
    for ($s = $now; $s >= $now - EVENT_QR_GRACE_SLOTS; $s--) {
        if (hash_equals(eventQrToken($event, $s), $token)) return true;
    }
    return false;
}

function siteBaseUrl() {
    $scheme = (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
        || (($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https") ? "https" : "http";
    $dir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
    return $scheme . "://" . $_SERVER["HTTP_HOST"] . $dir;
}

function eventCheckinUrl($event, $slot = null) {
    $slot = $slot ?? eventQrSlot();
    return siteBaseUrl() . "/event_checkin.php?e=" . intval($event["id"]) . "&t=" . eventQrToken($event, $slot);
}

function qrImageUrl($data, $size = 300) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&margin=10&ecc=H&data=" . urlencode($data);
}

// Records attendance and awards points (if any). Returns "ok", "already" or "error".
function recordAttendance($conn, $event, $userId, $method, $byUserId = null) {
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO event_attendances (event_id, user_id, method, checked_in_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $event["id"], $userId, $method, $byUserId);
        if (!$stmt->execute()) throw new Exception($conn->error);

        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            return "already";
        }

        $pts = intval($event["reward_points"]);
        if ($pts > 0) {
            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $pts, $userId);
            if (!$stmt->execute()) throw new Exception($conn->error);

            $reason = "เข้าร่วมกิจกรรม: " . $event["title"];
            $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, NULL, ?, ?)");
            $stmt->bind_param("iis", $userId, $pts, $reason);
            if (!$stmt->execute()) throw new Exception($conn->error);
        }

        $conn->commit();
        return "ok";
    } catch (Exception $e) {
        $conn->rollback();
        return "error";
    }
}

function eventDateText($dt) {
    if (!$dt) return "";
    $months = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $t = strtotime($dt);
    return intval(date("j", $t)) . " " . $months[intval(date("n", $t))] . " " . (intval(date("Y", $t)) + 543) . " · " . date("H:i", $t) . " น.";
}
?>
