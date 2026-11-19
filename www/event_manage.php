<?php
// Organizer home: create events, list events I own or help scan for.
require_once "_events.php";

$userId      = intval($_SESSION["user_id"]);
$isAdmin     = ($_SESSION["role"] ?? "") === "admin";
$isOrganizer = isEventOrganizer($conn, $userId);

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("event_manage.php");

    if (($_POST["action"] ?? "") === "create_event" && $isOrganizer) {
        $title    = trim($_POST["title"] ?? "");
        $desc     = trim($_POST["description"] ?? "");
        $location = trim($_POST["location_text"] ?? "");
        $startsAt = trim($_POST["starts_at"] ?? "");
        $points   = max(0, min(1000, intval($_POST["reward_points"] ?? 0)));
        $allowSelf = !empty($_POST["allow_self_checkin"]) ? 1 : 0;
        $startsAt = $startsAt !== "" ? date("Y-m-d H:i:s", strtotime($startsAt)) : null;
        $secret   = bin2hex(random_bytes(16));

        if ($title === "") {
            $msg = "กรุณากรอกชื่อกิจกรรม";
            $msgType = "bad";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO events (title, description, location_text, starts_at, reward_points, allow_self_checkin, qr_secret, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("ssssiisi", $title, $desc, $location, $startsAt, $points, $allowSelf, $secret, $userId);
            $stmt->execute();
            redirect("event_admin.php?id=" . $conn->insert_id . "&created=1");
        }
    }
}

if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT e.*, 'owner' AS my_role, (SELECT COUNT(*) FROM event_attendances a WHERE a.event_id=e.id) AS attendees
        FROM events e ORDER BY e.created_at DESC LIMIT 100
    ");
} else {
    $stmt = $conn->prepare("
        SELECT e.*, IF(e.created_by = ?, 'owner', 'staff') AS my_role,
               (SELECT COUNT(*) FROM event_attendances a WHERE a.event_id=e.id) AS attendees
        FROM events e
        WHERE e.created_by = ? OR e.id IN (SELECT event_id FROM event_staff WHERE user_id = ?)
        ORDER BY e.created_at DESC LIMIT 100
    ");
    $stmt->bind_param("iii", $userId, $userId, $userId);
}
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$isOrganizer && !$events) redirect("events.php");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการกิจกรรม | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/events.css">
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="events.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>จัดการกิจกรรม</h1>
      <?php if ($isOrganizer): ?>
        <button type="button" class="adm-btn primary" onclick="toggleForm()">+ สร้างกิจกรรม</button>
      <?php endif; ?>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($isOrganizer): ?>
    <div id="create-form" class="pg-card" style="display:<?= $msgType === "bad" ? "block" : "none" ?>">
      <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:10px 0 0">สร้างกิจกรรมใหม่</h2>
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="create_event">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input name="title" placeholder="ชื่อกิจกรรม เช่น เข้าแถวเช้าวันจันทร์, กีฬาสี" required maxlength="180">
        <input name="location_text" placeholder="สถานที่ เช่น หอประชุม, สนามฟุตบอล" maxlength="255">
        <textarea name="description" rows="2" placeholder="รายละเอียด (ไม่บังคับ)"></textarea>
        <div class="adm-row">
          <div style="display:grid;gap:4px">
            <label>วันเวลาเริ่ม</label>
            <input type="datetime-local" name="starts_at">
          </div>
          <div style="display:grid;gap:4px">
            <label>คะแนนที่ได้รับ</label>
            <input type="number" name="reward_points" min="0" max="1000" value="0">
          </div>
        </div>
        <label class="adm-check">
          <input type="checkbox" name="allow_self_checkin" value="1" checked>
          อนุญาตให้นักเรียนสแกน QR บนจอเพื่อเช็คชื่อเอง
        </label>
        <button class="adm-btn primary" type="submit">สร้างกิจกรรม</button>
      </form>
    </div>
    <?php endif; ?>

    <div class="pg-card">
      <?php if (!$events): ?>
        <div class="empty">ยังไม่มีกิจกรรม กด "+ สร้างกิจกรรม" เพื่อเริ่ม</div>
      <?php endif; ?>
      <?php foreach ($events as $ev): ?>
        <a class="row-item" href="event_admin.php?id=<?= $ev["id"] ?>">
          <div class="row-info">
            <strong><?= e($ev["title"]) ?></strong>
            <span><?= $ev["starts_at"] ? e(eventDateText($ev["starts_at"])) : "ไม่ระบุวันเวลา" ?></span>
            <span>ผู้เข้าร่วม <?= number_format($ev["attendees"]) ?> คน<?= $ev["my_role"] === "staff" ? " · คุณเป็นผู้ช่วยสแกน" : "" ?></span>
          </div>
          <span class="badge <?= $ev["is_open"] ? "open" : "closed" ?>"><?= $ev["is_open"] ? "เปิดเช็คชื่อ" : "ปิดอยู่" ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </main>
  <script>
  function toggleForm() {
    const el = document.getElementById('create-form');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
    if (el.style.display === 'block') el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  </script>
</body>
</html>
