<?php
// CSV attendee list (UTF-8 with BOM so Excel shows Thai correctly).
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!eventRole($conn, $event, $userId)) redirect("event_manage.php");

$stmt = $conn->prepare("
    SELECT u.name, u.email, a.method, a.checked_in_at, s.name AS scanner_name
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

header("Content-Type: text/csv; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");

$out = fopen("php://output", "w");
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, [csvSafe($event["title"])]);
fputcsv($out, ["ลำดับ", "ชื่อ", "อีเมล", "เวลาเช็คชื่อ", "วิธี", "ผู้สแกน"]);
foreach ($rows as $i => $r) {
    fputcsv($out, [
        $i + 1,
        csvSafe($r["name"]),
        csvSafe($r["email"]),
        $r["checked_in_at"],
        $methodLabel[$r["method"]] ?? $r["method"],
        csvSafe($r["scanner_name"] ?? ""),
    ]);
}
fclose($out);
