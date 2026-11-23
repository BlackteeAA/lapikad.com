<?php
// CSV attendee list (UTF-8 with BOM so Excel shows Thai correctly).
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!eventRole($conn, $event, $userId)) redirect("event_manage.php");

$stmt = $conn->prepare("
    SELECT u.name, u.student_name, u.class_room, u.student_no, u.email, a.method, a.checked_in_at, s.name AS scanner_name
    FROM event_attendances a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN users s ON s.id = a.checked_in_by
    WHERE a.event_id=?
    ORDER BY a.checked_in_at ASC
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$methodLabel = ["staff_scan" => "สภาสแกน", "self_scan" => "สแกนเอง", "manual" => "เพิ่มเอง"];
$filename = "attendance_event_" . $eventId . "_" . date("Ymd") . ".csv";

// Prevent spreadsheet formula injection from user-controlled names.
function csvSafe($v) {
    $v = (string)$v;
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
}

// Force Excel to keep a value as literal text. Needed for ชั้น/เลขที่ (e.g. "5/11"),
// which Excel's CSV auto-detection otherwise reads as a date ("5-พ.ย.") or strips
// leading zeros from as a number. Wrapping in ="..." makes Excel evaluate it as a
// formula whose result is just the quoted text — also immune to formula injection
// since the content sits inside the quotes rather than starting a new formula.
function csvText($v) {
    return '="' . str_replace('"', '""', (string)$v) . '"';
}

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [csvSafe($event["title"])]);
fputcsv($out, ["ลำดับ", "ชื่อ", "ชั้น", "เลขที่", "อีเมล", "เวลาเช็คชื่อ", "วิธี", "ผู้สแกน"]);
foreach ($rows as $i => $r) {
    $displayName = $r["student_name"] !== "" && $r["student_name"] !== null ? $r["student_name"] : $r["name"];
    fputcsv($out, [
        $i + 1,
        csvSafe($displayName),
        csvText($r["class_room"] ?? ""),
        csvText($r["student_no"] ?? ""),
        csvSafe($r["email"]),
        csvText($r["checked_in_at"]),
        $methodLabel[$r["method"]] ?? $r["method"],
        csvSafe($r["scanner_name"] ?? ""),
    ]);
}
fclose($out);
