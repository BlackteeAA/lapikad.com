<?php
// Self check-in: a student scans the rotating event QR (event_display.php).
// Reached either from event_scan.php (?raw=<scanned text>) or by opening the
// QR's URL directly with the phone camera (?e=<event id>&t=<token>).
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
if (($_SESSION["role"] ?? "") !== "admin" && !hasStudentInfo($conn, $userId)) {
    redirect("event_student_info.php?next=event_scan.php");
}
$eventId = intval($_GET["e"] ?? 0);
$token   = trim($_GET["t"] ?? "");

if (isset($_GET["raw"])) {
    $raw   = trim($_GET["raw"]);
    $parts = parse_url($raw);
    if (!empty($parts["query"])) {
        parse_str($parts["query"], $q);
        $eventId = intval($q["e"] ?? 0);
        $token   = trim($q["t"] ?? "");
    }
}

$success = false;
$title   = "QR ไม่ถูกต้อง";
$message = "QR นี้ไม่ใช่ QR เช็คชื่อกิจกรรม";
$event   = $eventId > 0 ? getEvent($conn, $eventId) : null;

if ($event) {
    if (!$event["is_open"]) {
        $title   = "ยังไม่เปิดเช็คชื่อ";
        $message = "กิจกรรม \"" . $event["title"] . "\" ยังไม่เปิดหรือปิดเช็คชื่อแล้ว";
    } elseif (!$event["allow_self_checkin"]) {
        $title   = "ต้องให้สภาสแกน";
        $message = "กิจกรรมนี้เช็คชื่อโดยให้สภานักเรียนสแกน QR ประจำตัวของคุณเท่านั้น";
    } elseif (!isValidEventQrToken($event, $token)) {
        $title   = "QR หมดอายุ";
        $message = "QR เปลี่ยนทุก " . EVENT_QR_SLOT_SECONDS . " วินาที กรุณาสแกนจากจอกิจกรรมอีกครั้ง";
    } else {
        $result = recordAttendance($conn, $event, $userId, "self_scan", null);
        if ($result === "ok") {
            $success = true;
            $title   = "เช็คชื่อสำเร็จ";
            $message = $event["title"] . ($event["reward_points"] > 0 ? " · ได้รับ " . intval($event["reward_points"]) . " คะแนน" : "");
        } elseif ($result === "already") {
            $success = true;
            $title   = "เช็คชื่อไปแล้ว";
            $message = "คุณเช็คชื่อกิจกรรม \"" . $event["title"] . "\" เรียบร้อยแล้ว";
        } else {
            $title   = "เกิดข้อผิดพลาด";
            $message = "บันทึกไม่สำเร็จ กรุณาลองใหม่";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body class="<?= $success ? "success-screen" : "fail-screen" ?>">
  <main class="result-page">
    <section class="result-card">
      <div class="result-mark"><?= $success ? "✓" : "!" ?></div>
      <h1><?= e($title) ?></h1>
      <p><?= e($message) ?></p>
      <?php if (!$success): ?>
        <a class="btn result-btn" href="event_scan.php">สแกนใหม่</a>
      <?php endif; ?>
      <a class="btn result-btn" href="events.php">กลับหน้ากิจกรรม</a>
    </section>
  </main>
</body>
</html>
