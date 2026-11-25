<?php
// "สแกนเช็คชื่อ": a student scans the rotating event QR (event_display.php).
// Only accepted from the scanner inside the event page (event_scan.php?id=…),
// which sends ?room=<event id>&raw=<scanned text>, and only for students who
// entered the event first (event_link.php). Scans from the bottom-nav camera
// (scan_route.php) or the phone's own camera arrive without "room" and are
// pointed back to the event page instead.
require_once "_events.php";

$userId = intval($_SESSION["user_id"]);
$roomId = intval($_GET["room"] ?? 0);

$eventId = intval($_GET["e"] ?? 0);
$token   = trim($_GET["t"] ?? "");
if (isset($_GET["raw"])) {
    $raw   = trim($_GET["raw"]);
    $parts = parse_url($raw);
    $eventId = 0;
    $token   = "";
    if (!empty($parts["query"])) {
        parse_str($parts["query"], $q);
        $eventId = intval($q["e"] ?? 0);
        $token   = trim($q["t"] ?? "");
    }
}

$success  = false;
$title    = "QR ไม่ถูกต้อง";
$message  = "QR นี้ไม่ใช่ QR เช็คชื่อของกิจกรรม";
$event    = $eventId > 0 ? getEvent($conn, $eventId) : null;
$joined   = $event ? hasJoinedEvent($conn, $eventId, $userId) : false;
// Where the "back" button goes: the event page I scanned from, or the scanned event's page.
$backRoom = $roomId > 0 ? $roomId : ($joined ? $eventId : 0);

if ($event) {
    if (!$joined) {
        $title   = "ยังไม่ได้เข้างาน";
        $message = "ต้องสแกนลิงก์หรือ QR เข้างานของกิจกรรม \"" . $event["title"] . "\" จากสภานักเรียนก่อน จึงจะเช็คชื่อได้";
    } elseif ($roomId !== $eventId) {
        $title   = $roomId > 0 ? "QR ของกิจกรรมอื่น" : "สแกนจากหน้ากิจกรรม";
        $message = $roomId > 0
            ? "QR นี้เป็นของกิจกรรม \"" . $event["title"] . "\" ไม่ใช่กิจกรรมที่คุณเปิดอยู่"
            : "QR เช็คชื่อต้องสแกนจากปุ่ม \"สแกนเช็คชื่อ\" ในหน้ากิจกรรม";
    } elseif (!canJoinEvent($conn, $event, $userId)) {
        $title   = "ต่างโรงเรียน";
        $message = "กิจกรรม \"" . $event["title"] . "\" เป็นของโรงเรียนอื่น";
    } elseif (!$event["is_open"]) {
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
            $message = $event["title"];
        } elseif ($result === "already") {
            $success = true;
            $title   = "เช็คชื่อไปแล้ว";
            $message = "คุณเช็คชื่อกิจกรรม \"" . $event["title"] . "\" ไปแล้ว";
        } elseif ($result === "other_school") {
            $title   = "ต่างโรงเรียน";
            $message = "กิจกรรมนี้เป็นของโรงเรียนอื่น";
        } elseif ($result === "not_joined") {
            $title   = "ยังไม่ได้เข้างาน";
            $message = "ต้องสแกนลิงก์หรือ QR เข้างานจากสภานักเรียนก่อน";
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
      <?php if (!$success && $roomId > 0): ?>
        <a class="btn result-btn" href="event_scan.php?id=<?= $roomId ?>">สแกนใหม่</a>
      <?php endif; ?>
      <?php if ($backRoom > 0): ?>
        <a class="btn result-btn" href="event_room.php?id=<?= $backRoom ?>">ไปหน้ากิจกรรม</a>
      <?php else: ?>
        <a class="btn result-btn" href="dashboard.php">กลับหน้าหลัก</a>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
