<?php
// Shared helpers for the school events / attendance line ("กิจกรรมโรงเรียน").
// Requires sql/add_school_events.sql.
require_once __DIR__ . "/_auth.php";
require_once __DIR__ . "/_school.php";

// How often the self check-in QR on the display screen rotates.
define("EVENT_QR_SLOT_SECONDS", 30);
// How many past slots are still accepted (covers slow phones / page lag).
define("EVENT_QR_GRACE_SLOTS", 2);
// Prefix of the personal QR a student shows to staff.
define("EVENT_STUDENT_QR_PREFIX", "LAPIKAD-STU:");

// Admins, and council/teacher members of a school, may create events (for their school).
function isEventOrganizer($conn, $userId) {
    return isSiteAdmin() || isSchoolCouncil($conn, $userId);
}

// Is the user ready to use the student menu? Everyone except site admins must
// belong to a school; students and council also fill in their real
// name/class/roll-number (event_student_info.php), separate from the account's
// login name (users.name), which is often just a nickname.
function hasStudentInfo($conn, $userId) {
    if (isSiteAdmin()) return true;
    $school = getUserSchool($conn, $userId);
    if ($school["school_id"] === null) return false;
    if ($school["school_role"] === "teacher") return true;

    $stmt = $conn->prepare("SELECT student_name, class_room, student_no FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row
        && trim($row["student_name"] ?? "") !== ""
        && trim($row["class_room"] ?? "") !== ""
        && trim($row["student_no"] ?? "") !== "";
}

// Events with school_id NULL are open to every school; otherwise only that school's members.
function canJoinEvent($conn, $event, $userId) {
    if ($event["school_id"] === null || isSiteAdmin()) return true;
    return getUserSchool($conn, $userId)["school_id"] === intval($event["school_id"]);
}

function getEvent($conn, $eventId) {
    $stmt = $conn->prepare("SELECT * FROM events WHERE id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// 'owner' (creator, admin, or council/teacher of the event's school: full control),
// 'staff' (may scan + view list), or null.
function eventRole($conn, $event, $userId) {
    if (!$event) return null;
    if (isSiteAdmin()) return "owner";
    if ($event["created_by"] !== null && intval($event["created_by"]) === intval($userId)) return "owner";
    if ($event["school_id"] !== null && canManageSchoolContent($conn, $userId, intval($event["school_id"]))) return "owner";

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

// Host and scheme come from APP_ALLOWED_HOSTS (_bootstrap.php), never from the
// request's Host header — a forged one would otherwise be baked into the QR
// codes and check-in links we print and hand out.
function siteBaseUrl() {
    $dir = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"] ?? "/")), "/");
    return app_base_url() . $dir;
}

function eventCheckinUrl($event, $slot = null) {
    $slot = $slot ?? eventQrSlot();
    return siteBaseUrl() . "/event_checkin.php?e=" . intval($event["id"]) . "&t=" . eventQrToken($event, $slot);
}

function qrImageUrl($data, $size = 300) {
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&margin=10&ecc=H&data=" . urlencode($data);
}

// ── Check-in links / QR made by the council (sql/add_event_links.sql) ──
// Times are shown in Thai time regardless of the server's timezone.
define("EVENT_LINK_TZ", "Asia/Bangkok");

// May this user act as scan helper for at least one event?
function isEventStaff($conn, $userId) {
    $stmt = $conn->prepare("SELECT 1 FROM event_staff WHERE user_id=? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function normalizeEventLinkToken($raw) {
    $raw = strtoupper(trim((string)$raw));
    return preg_match('/^[0-9A-F]{20}$/', $raw) ? $raw : "";
}

function eventLinkUrl($token) {
    return siteBaseUrl() . "/event_link.php?k=" . $token;
}

function getEventLink($conn, $token) {
    $stmt = $conn->prepare("SELECT * FROM event_links WHERE token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function eventLinkExpired($link) {
    return $link["expires_at"] !== null && time() >= intval($link["expires_at"]);
}

// Unix timestamp → "24 พ.ย. 2569 · 15:30 น." in Thai time.
function thaiTimestampText($ts) {
    $d = (new DateTime("@" . intval($ts)))->setTimezone(new DateTimeZone(EVENT_LINK_TZ));
    $months = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    return intval($d->format("j")) . " " . $months[intval($d->format("n"))] . " " . (intval($d->format("Y")) + 543) . " · " . $d->format("H:i") . " น.";
}

// Expiry chosen in the create-link form → Unix timestamp, null (never), or false (invalid).
function eventLinkExpiryFromForm($preset, $custom) {
    $tz = new DateTimeZone(EVENT_LINK_TZ);
    $minutes = ["15m" => 15, "30m" => 30, "1h" => 60, "3h" => 180, "1d" => 1440];
    if (isset($minutes[$preset])) return time() + $minutes[$preset] * 60;
    if ($preset === "none") return null;
    if ($preset === "today") return (new DateTime("today 23:59:59", $tz))->getTimestamp();
    if ($preset === "custom") {
        $d = DateTime::createFromFormat("Y-m-d\TH:i", trim((string)$custom), $tz);
        if (!$d || $d->getTimestamp() <= time()) return false;
        return $d->getTimestamp();
    }
    return false;
}

// ── "เข้างาน" (sql/add_event_joins.sql) ──
// A student joins an event through a council link (event_link.php). Being
// checked in ("เช็คชื่อ": self scan of the on-screen QR, or council scanning the
// student's personal QR) is only allowed after joining.

// Has the user joined the event? If the event_joins table doesn't exist yet
// (migration not run), the join step is skipped so check-in keeps working.
function hasJoinedEvent($conn, $eventId, $userId) {
    try {
        $stmt = $conn->prepare("SELECT 1 FROM event_joins WHERE event_id=? AND user_id=?");
        $stmt->bind_param("ii", $eventId, $userId);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (mysqli_sql_exception $e) {
        return true;
    }
}

// Returns "ok", "already", "other_school" or "error".
function joinEvent($conn, $event, $userId, $linkId = null) {
    if (!canJoinEvent($conn, $event, $userId)) return "other_school";
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO event_joins (event_id, user_id, link_id) VALUES (?, ?, ?)");
        $stmt->bind_param("iii", $event["id"], $userId, $linkId);
        $stmt->execute();
        return $stmt->affected_rows > 0 ? "ok" : "already";
    } catch (mysqli_sql_exception $e) {
        return "error";
    }
}

// Events the user is in right now: joined, and either check-in is open or they
// joined within the last day. Newest first, with whether they're checked in.
function getCurrentEvents($conn, $userId) {
    try {
        $stmt = $conn->prepare("
            SELECT e.*, j.joined_at, (a.id IS NOT NULL) AS attended
            FROM event_joins j
            JOIN events e ON e.id = j.event_id
            LEFT JOIN event_attendances a ON a.event_id = e.id AND a.user_id = j.user_id
            WHERE j.user_id = ? AND (e.is_open = 1 OR j.joined_at >= NOW() - INTERVAL 1 DAY)
            ORDER BY j.joined_at DESC
            LIMIT 5
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (mysqli_sql_exception $e) {
        return [];
    }
}

// Records attendance ("เช็คชื่อ"). School events don't give points; they only
// track who came (see the attendance report, event_attendance.php).
// Returns "ok", "already", "other_school", "not_joined" or "error".
// Self scans and council scans need the student to have joined the event first;
// "manual" (council adds a name by hand) doesn't.
function recordAttendance($conn, $event, $userId, $method, $byUserId = null) {
    if ($event["school_id"] !== null && getUserSchool($conn, $userId)["school_id"] !== intval($event["school_id"])) {
        return "other_school";
    }
    if (in_array($method, ["self_scan", "staff_scan"], true) && !hasJoinedEvent($conn, intval($event["id"]), $userId)) {
        return "not_joined";
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("INSERT IGNORE INTO event_attendances (event_id, user_id, method, checked_in_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iisi", $event["id"], $userId, $method, $byUserId);
        if (!$stmt->execute()) throw new Exception($conn->error);

        if ($stmt->affected_rows === 0) {
            $conn->rollback();
            return "already";
        }

        $conn->commit();
        return "ok";
    } catch (Exception $e) {
        $conn->rollback();
        return "error";
    }
}

// ── Participant list (event_admin.php, live-updated by event_attendees_feed.php) ──
// One row per student who joined and/or was checked in. Each row carries
// join_id and att_id so the live feed can send only what changed after
// ($afterJoin, $afterAtt).

function getEventParticipants($conn, $eventId, $afterJoin = 0, $afterAtt = 0) {
    try {
        $stmt = $conn->prepare("
            SELECT u.id AS user_id, u.name, u.student_name, u.class_room, u.student_no, u.email, u.avatar_url,
                   j.id AS join_id, j.joined_at, a.id AS att_id, a.method, a.checked_in_at, s.name AS scanner_name
            FROM (
                SELECT user_id FROM event_joins WHERE event_id = ?
                UNION
                SELECT user_id FROM event_attendances WHERE event_id = ?
            ) p
            JOIN users u ON u.id = p.user_id
            LEFT JOIN event_joins j ON j.event_id = ? AND j.user_id = p.user_id
            LEFT JOIN event_attendances a ON a.event_id = ? AND a.user_id = p.user_id
            LEFT JOIN users s ON s.id = a.checked_in_by
            WHERE COALESCE(j.id, 0) > ? OR COALESCE(a.id, 0) > ?
            ORDER BY GREATEST(COALESCE(j.joined_at, '1970-01-02'), COALESCE(a.checked_in_at, '1970-01-02')) DESC
        ");
        $stmt->bind_param("iiiiii", $eventId, $eventId, $eventId, $eventId, $afterJoin, $afterAtt);
    } catch (mysqli_sql_exception $e) {
        // event_joins not created yet: attendance only.
        $stmt = $conn->prepare("
            SELECT u.id AS user_id, u.name, u.student_name, u.class_room, u.student_no, u.email, u.avatar_url,
                   NULL AS join_id, NULL AS joined_at, a.id AS att_id, a.method, a.checked_in_at, s.name AS scanner_name
            FROM event_attendances a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN users s ON s.id = a.checked_in_by
            WHERE a.event_id = ? AND a.id > ?
            ORDER BY a.id DESC
        ");
        $stmt->bind_param("ii", $eventId, $afterAtt);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r["display_name"] = $r["student_name"] !== "" && $r["student_name"] !== null ? $r["student_name"] : $r["name"];
    }
    unset($r);
    return $rows;
}

// User ids currently in the list (so the live page can drop removed rows).
function getEventParticipantIds($conn, $eventId) {
    try {
        $stmt = $conn->prepare("SELECT user_id FROM event_joins WHERE event_id=? UNION SELECT user_id FROM event_attendances WHERE event_id=?");
        $stmt->bind_param("ii", $eventId, $eventId);
    } catch (mysqli_sql_exception $e) {
        $stmt = $conn->prepare("SELECT user_id FROM event_attendances WHERE event_id=?");
        $stmt->bind_param("i", $eventId);
    }
    $stmt->execute();
    return array_map("intval", array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "user_id"));
}

// ["joined" => entered the event, "attended" => checked in, "waiting" => joined but
//  not checked in yet, "absent" => school students who neither joined nor were
//  checked in (null for all-school events)]
function eventParticipantCounts($conn, $eventId) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM event_attendances WHERE event_id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $attended = intval($stmt->get_result()->fetch_assoc()["c"]);

    $joined = 0;
    $waiting = 0;
    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS joined, COALESCE(SUM(a.id IS NULL), 0) AS waiting
            FROM event_joins j
            LEFT JOIN event_attendances a ON a.event_id = j.event_id AND a.user_id = j.user_id
            WHERE j.event_id=?
        ");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $joined  = intval($r["joined"]);
        $waiting = intval($r["waiting"]);
    } catch (mysqli_sql_exception $e) {
        // event_joins not created yet
    }
    $absent = null;
    $stmt = $conn->prepare("SELECT school_id FROM events WHERE id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $ev = $stmt->get_result()->fetch_assoc();
    if ($ev && $ev["school_id"] !== null) {
        $absent = count(getEventAbsentStudents($conn, $eventId, intval($ev["school_id"])));
    }
    return ["joined" => $joined, "attended" => $attended, "waiting" => $waiting, "absent" => $absent];
}

// One row of the participant list. $isOwner adds the remove button.
function eventParticipantRowHtml($p, $isOwner, $eventId) {
    $methodLabel = ["staff_scan" => "สภาสแกน", "self_scan" => "สแกนเอง", "manual" => "เพิ่มเอง", "link" => "ลิงก์"];
    $hm = fn($ts) => date("H:i", strtotime($ts)) . " น.";
    ob_start(); ?>
        <div class="row-item" data-user="<?= intval($p["user_id"]) ?>" data-join="<?= intval($p["join_id"] ?? 0) ?>" data-att="<?= intval($p["att_id"] ?? 0) ?>" data-search="<?= e(mb_strtolower($p["display_name"] . " " . $p["email"], "UTF-8")) ?>">
          <div class="avatar">
            <?php if (!empty($p["avatar_url"])): ?>
              <img src="<?= e($p["avatar_url"]) ?>" alt="">
            <?php else: ?>
              <?= e(mb_substr($p["display_name"], 0, 1, "UTF-8")) ?>
            <?php endif; ?>
          </div>
          <div class="row-info">
            <strong><?= e($p["display_name"]) ?></strong>
            <?php if (!empty($p["class_room"]) || !empty($p["student_no"])): ?>
              <div class="tag-row">
                <?php if (!empty($p["class_room"])): ?><span class="tag">ชั้น <?= e($p["class_room"]) ?></span><?php endif; ?>
                <?php if (!empty($p["student_no"])): ?><span class="tag">เลขที่ <?= e($p["student_no"]) ?></span><?php endif; ?>
              </div>
            <?php endif; ?>
            <span>
              <?php if ($p["joined_at"]): ?>เข้างาน <?= $hm($p["joined_at"]) ?><?php endif; ?>
              <?php if ($p["att_id"]): ?>
                <?= $p["joined_at"] ? " · " : "" ?>เช็คชื่อ <?= $hm($p["checked_in_at"]) ?> (<?= $methodLabel[$p["method"]] ?? "" ?><?= $p["scanner_name"] ? " โดย " . e($p["scanner_name"]) : "" ?>)
              <?php endif; ?>
            </span>
          </div>
          <span class="badge <?= $p["att_id"] ? "done" : "closed" ?>"><?= $p["att_id"] ? "มาแล้ว" : "รอเช็คชื่อ" ?></span>
          <?php if ($isOwner): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="event_id" value="<?= intval($eventId) ?>">
            <input type="hidden" name="action" value="remove_attendance">
            <input type="hidden" name="user_id" value="<?= intval($p["user_id"]) ?>">
            <button type="submit" class="icon-btn" title="ลบ" onclick="return confirm('ลบรายชื่อนี้ออกจากกิจกรรม? (จะนับว่าไม่มา)')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
    <?php
    return ob_get_clean();
}

// ── Attendance summary: who came, who didn't ("มา / ไม่มา") ──

// Students (and council) of a school, the people whose attendance is tracked.
function getSchoolStudents($conn, $schoolId) {
    $stmt = $conn->prepare("
        SELECT id, name, student_name, class_room, student_no, email, avatar_url, school_role
        FROM users
        WHERE school_id = ? AND school_role IN ('student','council') AND is_banned = 0
    ");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as &$r) {
        $r["display_name"] = trim($r["student_name"] ?? "") !== "" ? $r["student_name"] : $r["name"];
    }
    unset($r);
    return $rows;
}

// School students who neither entered (เข้างาน) nor were checked in to the event.
function getEventAbsentStudents($conn, $eventId, $schoolId) {
    $present = [];
    $stmt = $conn->prepare("SELECT user_id FROM event_attendances WHERE event_id=?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $present[intval($r["user_id"])] = true;
    try {
        $stmt = $conn->prepare("SELECT user_id FROM event_joins WHERE event_id=?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) $present[intval($r["user_id"])] = true;
    } catch (mysqli_sql_exception $e) {
        // event_joins not created yet
    }
    return array_values(array_filter(getSchoolStudents($conn, $schoolId), fn($u) => !isset($present[intval($u["id"])])));
}

// Events that count toward a school's attendance: the school's own events and
// all-school events that have already started (or, without a start time, that
// someone was checked in to). Oldest first.
function getSchoolAttendanceEvents($conn, $schoolId) {
    $stmt = $conn->prepare("
        SELECT e.id, e.title, e.starts_at, e.created_at, e.school_id,
               (SELECT COUNT(*) FROM event_attendances a WHERE a.event_id = e.id) AS attended
        FROM events e
        WHERE (e.school_id = ? OR e.school_id IS NULL)
          AND (e.starts_at <= NOW()
               OR (e.starts_at IS NULL AND EXISTS (SELECT 1 FROM event_attendances a2 WHERE a2.event_id = e.id)))
        ORDER BY COALESCE(e.starts_at, e.created_at) ASC, e.id ASC
    ");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// [user_id => [event_id => true]] for the given events.
function getAttendanceMap($conn, $eventIds) {
    $map = [];
    if (!$eventIds) return $map;
    $ids = implode(",", array_map("intval", $eventIds));
    $res = $conn->query("SELECT user_id, event_id FROM event_attendances WHERE event_id IN ($ids)");
    while ($r = $res->fetch_assoc()) $map[intval($r["user_id"])][intval($r["event_id"])] = true;
    return $map;
}

// One student's attendance in their school: ["attended" => n, "total" => n].
function getMyAttendanceRate($conn, $userId, $schoolId) {
    $events = getSchoolAttendanceEvents($conn, $schoolId);
    if (!$events) return ["attended" => 0, "total" => 0];
    $ids = implode(",", array_map(fn($e) => intval($e["id"]), $events));
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM event_attendances WHERE user_id=? AND event_id IN ($ids)");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    return ["attended" => intval($stmt->get_result()->fetch_assoc()["c"]), "total" => count($events)];
}

function attendancePercent($attended, $total) {
    return $total > 0 ? (int)round($attended * 100 / $total) : null;
}

function eventDateText($dt) {
    if (!$dt) return "";
    $months = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
    $t = strtotime($dt);
    return intval(date("j", $t)) . " " . $months[intval(date("n", $t))] . " " . (intval(date("Y", $t)) + 543) . " · " . date("H:i", $t) . " น.";
}
?>
