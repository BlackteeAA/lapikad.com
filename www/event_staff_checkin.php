<?php
// Handles a staff scan of a student's personal QR, shows who it was (so the
// scanner can check the face matches), then returns to the scanner automatically.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_POST["event_id"] ?? 0);
$event   = getEvent($conn, $eventId);

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_verify() || !eventRole($conn, $event, $userId)) {
    redirect("event_manage.php");
}

$state   = "fail"; // ok | already | fail
$title   = "QR ไม่ถูกต้อง";
$message = "นี่ไม่ใช่ QR ประจำตัวนักเรียน";
$student = null;

$token = extractStudentToken($_POST["scanned_code"] ?? "");

if (!$event["is_open"]) {
    $title   = "ปิดเช็คชื่ออยู่";
    $message = "เปิดเช็คชื่อกิจกรรมก่อนจึงจะสแกนได้";
} elseif ($token !== "") {
    $stmt = $conn->prepare("SELECT id, name, student_name, class_room, student_no, avatar_url, is_banned FROM users WHERE event_token=?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();

    if (!$student || !empty($student["is_banned"])) {
        $student = null;
        $title   = "ไม่พบนักเรียน";
        $message = "QR นี้ไม่มีในระบบ";
    } else {
        $student["display_name"] = $student["student_name"] !== "" && $student["student_name"] !== null
            ? $student["student_name"] : $student["name"];
        $r = recordAttendance($conn, $event, intval($student["id"]), "staff_scan", $userId);
        if ($r === "ok") {
            $state = "ok";
            $title = "เช็คชื่อสำเร็จ";
            $message = "";
        } elseif ($r === "already") {
            $state = "already";
            $title = "เช็คชื่อไปแล้ว";
            $message = "";
        } else {
            $title = "บันทึกไม่สำเร็จ";
            $message = "กรุณาสแกนใหม่";
        }
    }
}

$stmt = $conn->prepare("SELECT COUNT(*) c FROM event_attendances WHERE event_id=?");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$total = intval($stmt->get_result()->fetch_assoc()["c"]);

$bg = ["ok" => "#16a34a", "already" => "#d97706", "fail" => "#dc2626"][$state];
$scanUrl = "event_staff_scan.php?id=" . $eventId;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title><?= e($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing:border-box;margin:0;padding:0; }
    body {
      font-family:'Kanit',sans-serif;min-height:100vh;background:<?= $bg ?>;color:#fff;
      display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center;
    }
    .avatar {
      width:120px;height:120px;border-radius:50%;overflow:hidden;margin-bottom:16px;
      background:rgba(255,255,255,.2);border:4px solid #fff;
      display:flex;align-items:center;justify-content:center;font-size:52px;font-weight:700;
    }
    .avatar img { width:100%;height:100%;object-fit:cover; }
    .mark { font-size:64px;line-height:1;margin-bottom:12px; }
    .name { font-size:28px;font-weight:700;margin-bottom:4px; }
    .class-info { font-size:16px;opacity:.9;margin-bottom:4px; }
    h1 { font-size:20px;font-weight:600;opacity:.95; }
    p  { font-size:14px;opacity:.9;margin-top:6px; }
    .count { margin-top:18px;font-size:14px;background:rgba(0,0,0,.15);border-radius:99px;padding:6px 16px; }
    .btns { display:flex;gap:10px;margin-top:26px; }
    .btns a {
      background:#fff;color:<?= $bg ?>;text-decoration:none;border-radius:999px;
      padding:12px 22px;font-size:15px;font-weight:600;
    }
    .btns a.ghost { background:transparent;color:#fff;border:2px solid rgba(255,255,255,.7); }
    .bar { position:fixed;left:0;bottom:0;height:5px;background:rgba(255,255,255,.8);width:100%;animation:shrink 1.8s linear forwards; }
    @keyframes shrink { to { width:0; } }
  </style>
</head>
<body>
  <?php if ($student): ?>
    <div class="avatar">
      <?php if (!empty($student["avatar_url"])): ?>
        <img src="<?= e($student["avatar_url"]) ?>" alt="">
      <?php else: ?>
        <?= e(mb_substr($student["display_name"], 0, 1, "UTF-8")) ?>
      <?php endif; ?>
    </div>
    <div class="name"><?= e($student["display_name"]) ?></div>
    <?php if (!empty($student["class_room"]) || !empty($student["student_no"])): ?>
      <div class="class-info"><?= e($student["class_room"]) ?><?= !empty($student["student_no"]) ? " · เลขที่ " . e($student["student_no"]) : "" ?></div>
    <?php endif; ?>
  <?php else: ?>
    <div class="mark">!</div>
  <?php endif; ?>

  <h1><?= $state === "ok" ? "✓ " : "" ?><?= e($title) ?></h1>
  <?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?>
  <div class="count"><?= e($event["title"]) ?> · ทั้งหมด <?= $total ?> คน</div>

  <div class="btns">
    <a href="<?= e($scanUrl) ?>">สแกนต่อ</a>
    <a class="ghost" href="event_admin.php?id=<?= $eventId ?>" onclick="stopAuto()">ดูรายชื่อ</a>
  </div>

  <?php if ($state !== "fail"): ?>
  <div class="bar" id="bar"></div>
  <script>
    if (navigator.vibrate) navigator.vibrate(<?= $state === "ok" ? "120" : "[80,60,80]" ?>);
    const auto = setTimeout(() => location.replace(<?= json_encode($scanUrl) ?>), 1800);
    function stopAuto() { clearTimeout(auto); }
  </script>
  <?php else: ?>
  <script>
    if (navigator.vibrate) navigator.vibrate([200, 80, 200]);
    function stopAuto() {}
  </script>
  <?php endif; ?>
</body>
</html>
