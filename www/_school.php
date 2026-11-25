<?php
// Schools and per-school roles. Requires sql/add_schools.sql.
//   student  นักเรียน      — belongs to a school, checks in to its events
//   council  สภานักเรียน   — manages its own school's events and school rewards
//   teacher  ครู/ผู้ดูแล   — council rights + manages the school's members
// Site admins (users.role='admin') can act on every school.
require_once __DIR__ . "/_db.php";

const SCHOOL_ROLES = [
    "student" => "นักเรียน",
    "council" => "สภานักเรียน",
    "teacher" => "ครู/ผู้ดูแลโรงเรียน",
];

function schoolRoleLabel($role) {
    return SCHOOL_ROLES[$role] ?? "";
}

function isSiteAdmin() {
    return ($_SESSION["role"] ?? "") === "admin";
}

// ["school_id" => int|null, "school_role" => string|null, "school_name" => string|null, "school_logo" => string|null]
// Cached per request; pass $refresh after changing the user's own school.
function getUserSchool($conn, $userId, $refresh = false) {
    static $cache = [];
    $userId = intval($userId);
    if (!$refresh && isset($cache[$userId])) return $cache[$userId];

    $stmt = $conn->prepare("
        SELECT u.school_id, u.school_role, s.name AS school_name, s.logo_url AS school_logo
        FROM users u LEFT JOIN schools s ON s.id = u.school_id
        WHERE u.id=?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc() ?: [];

    $schoolId = isset($row["school_id"]) && $row["school_id"] !== null && $row["school_name"] !== null
        ? intval($row["school_id"]) : null;
    return $cache[$userId] = [
        "school_id"   => $schoolId,
        "school_role" => $schoolId ? ($row["school_role"] ?? "student") : null,
        "school_name" => $schoolId ? $row["school_name"] : null,
        "school_logo" => $schoolId ? ($row["school_logo"] ?? null) : null,
    ];
}

// Council or teacher of some school (not counting site admins).
function isSchoolCouncil($conn, $userId) {
    $s = getUserSchool($conn, $userId);
    return $s["school_id"] !== null && in_array($s["school_role"], ["council", "teacher"], true);
}

// May this user manage events / school rewards of $schoolId?
function canManageSchoolContent($conn, $userId, $schoolId) {
    if (isSiteAdmin()) return true;
    if ($schoolId === null) return false;
    $s = getUserSchool($conn, $userId);
    return $s["school_id"] === intval($schoolId) && in_array($s["school_role"], ["council", "teacher"], true);
}

// May this user add/remove members and change roles in $schoolId?
function canManageSchoolMembers($conn, $userId, $schoolId) {
    if (isSiteAdmin()) return true;
    $s = getUserSchool($conn, $userId);
    return $s["school_id"] !== null && $s["school_id"] === intval($schoolId) && $s["school_role"] === "teacher";
}

// "ม.5/2" → ["ม.5", "ม.5/2"]; tolerates "ม5/2", "ม. 5 / 2". No class → [null, null].
function splitClassRoom($cr) {
    $cr = preg_replace('/\s+/u', '', trim((string)$cr));
    if ($cr === "") return [null, null];
    $cr = preg_replace('/^(ม|ป)\.?(?=\d)/u', '$1.', $cr);
    if (preg_match('#^(.+?)/(.+)$#u', $cr, $m)) return [$m[1], $cr];
    return [$cr, null];
}

// Students grouped as [grade => [room => [users…]]], naturally sorted (ม.1/2 before ม.1/10),
// each room sorted by roll number. Users without a class go to $noClass.
function groupByClassRoom($list, &$noClass = null) {
    $grades = [];
    $noClass = [];
    foreach ($list as $u) {
        [$grade, $room] = splitClassRoom($u["class_room"] ?? "");
        if ($grade === null) { $noClass[] = $u; continue; }
        $grades[$grade][$room ?? ""][] = $u;
    }
    $byNo = function ($a, $b) {
        $na = intval($a["student_no"] ?? 0) ?: PHP_INT_MAX;
        $nb = intval($b["student_no"] ?? 0) ?: PHP_INT_MAX;
        return $na <=> $nb ?: strcmp($a["student_name"] ?: $a["name"], $b["student_name"] ?: $b["name"]);
    };
    uksort($grades, "strnatcmp");
    foreach ($grades as &$rooms) {
        uksort($rooms, "strnatcmp");
        foreach ($rooms as &$l) usort($l, $byNo);
        unset($l);
    }
    unset($rooms);
    return $grades;
}

function getSchool($conn, $schoolId) {
    $stmt = $conn->prepare("SELECT * FROM schools WHERE id=?");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

function getAllSchools($conn) {
    return $conn->query("SELECT id, name, district, province, logo_url FROM schools ORDER BY name")->fetch_all(MYSQLI_ASSOC);
}

function schoolPlaceText($school) {
    $parts = array_filter([
        !empty($school["district"]) ? "อ." . $school["district"] : "",
        !empty($school["province"]) ? "จ." . $school["province"] : "",
    ]);
    return implode(" ", $parts);
}

// Saves an uploaded logo ($_FILES entry) for $schoolId, replacing the old file.
// Returns null on success (or when no file was chosen), otherwise a Thai error message.
const SCHOOL_LOGO_MAX_MB = 5;

// True when the request body was larger than post_max_size: PHP then drops
// $_POST and $_FILES entirely, so the CSRF check would fail silently.
function uploadPostTooLarge() {
    return ($_SERVER["REQUEST_METHOD"] ?? "") === "POST"
        && empty($_POST) && empty($_FILES)
        && intval($_SERVER["CONTENT_LENGTH"] ?? 0) > 0;
}

function uploadTooLargeMessage() {
    return "ไฟล์ใหญ่เกินไป (เซิร์ฟเวอร์รับได้ไม่เกิน " . ini_get("upload_max_filesize") . ") กรุณาย่อรูปแล้วลองใหม่";
}

function saveSchoolLogo($conn, $schoolId, $file) {
    if (!isset($file) || $file["error"] === UPLOAD_ERR_NO_FILE) return null;
    if ($file["error"] === UPLOAD_ERR_INI_SIZE || $file["error"] === UPLOAD_ERR_FORM_SIZE) return uploadTooLargeMessage();
    if ($file["error"] !== UPLOAD_ERR_OK) return "อัปโหลดโลโก้ไม่สำเร็จ (รหัสข้อผิดพลาด " . intval($file["error"]) . ")";
    if ($file["size"] > SCHOOL_LOGO_MAX_MB * 1024 * 1024) return "ไฟล์โลโก้ต้องไม่เกิน " . SCHOOL_LOGO_MAX_MB . "MB";

    $ext = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg", "jpeg", "png", "webp", "gif"], true) || @getimagesize($file["tmp_name"]) === false) {
        return "โลโก้ต้องเป็นไฟล์รูป (jpg, png, webp, gif)";
    }

    $dir = __DIR__ . "/assets/images/schools/";
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    if (!is_dir($dir) || !is_writable($dir)) {
        error_log("saveSchoolLogo: directory not writable: $dir");
        return "บันทึกไฟล์ไม่ได้: โฟลเดอร์ assets/images/schools/ ไม่มีสิทธิ์เขียน (แจ้งผู้ดูแลเซิร์ฟเวอร์)";
    }
    $fname = "school_" . intval($schoolId) . "_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
    if (!move_uploaded_file($file["tmp_name"], $dir . $fname)) return "บันทึกไฟล์โลโก้ไม่สำเร็จ";

    $old = getSchool($conn, $schoolId)["logo_url"] ?? "";
    $url = "assets/images/schools/" . $fname;
    $stmt = $conn->prepare("UPDATE schools SET logo_url=? WHERE id=?");
    $stmt->bind_param("si", $url, $schoolId);
    $stmt->execute();

    deleteSchoolLogoFile($old);
    return null;
}

function removeSchoolLogo($conn, $schoolId) {
    $old = getSchool($conn, $schoolId)["logo_url"] ?? "";
    $stmt = $conn->prepare("UPDATE schools SET logo_url=NULL WHERE id=?");
    $stmt->bind_param("i", $schoolId);
    $stmt->execute();
    deleteSchoolLogoFile($old);
}

// Only ever deletes files inside assets/images/schools/.
function deleteSchoolLogoFile($url) {
    if ($url && preg_match('#^assets/images/schools/[A-Za-z0-9_.-]+$#', $url)) {
        @unlink(__DIR__ . "/" . $url);
    }
}

// <img> of the school's logo, or a coloured circle with the first letter of its name.
// The logo is shown whole (not cropped), keeping its own shape and any PNG transparency.
function schoolLogoHtml($school, $size = 40) {
    $size = intval($size);
    $box = "width:{$size}px;height:{$size}px;flex-shrink:0;";
    if (!empty($school["logo_url"])) {
        return '<img class="school-logo" src="' . e($school["logo_url"]) . '" alt="" style="' . $box . 'object-fit:contain;background:transparent">';
    }
    $initial = mb_substr($school["name"] ?? "?", 0, 1, "UTF-8");
    return '<span class="school-logo-ph" style="' . $box . 'border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#e0e7ff;color:#4338ca;font-weight:700;font-size:' . intval($size * 0.45) . 'px">' . e($initial) . '</span>';
}

// Assign a user to a school with a role (null $schoolId removes them from any school).
function setUserSchool($conn, $userId, $schoolId, $role) {
    if ($schoolId === null) {
        $stmt = $conn->prepare("UPDATE users SET school_id=NULL, school_role=NULL WHERE id=?");
        $stmt->bind_param("i", $userId);
    } else {
        if (!array_key_exists($role, SCHOOL_ROLES)) $role = "student";
        $stmt = $conn->prepare("UPDATE users SET school_id=?, school_role=? WHERE id=?");
        $stmt->bind_param("isi", $schoolId, $role, $userId);
    }
    $stmt->execute();
    getUserSchool($conn, $userId, true);
}
?>
