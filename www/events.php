<?php
require_once "_events.php";

$userId      = intval($_SESSION["user_id"]);
$isOrganizer = isEventOrganizer($conn, $userId);

$stmt = $conn->prepare("SELECT COUNT(*) c FROM event_staff WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$isStaff = $stmt->get_result()->fetch_assoc()["c"] > 0;

// Events open for check-in right now, plus upcoming ones, with my attendance flag.
$stmt = $conn->prepare("
    SELECT e.*, (a.id IS NOT NULL) AS attended
    FROM events e
    LEFT JOIN event_attendances a ON a.event_id = e.id AND a.user_id = ?
    WHERE e.is_open = 1 OR e.starts_at >= CURDATE()
    ORDER BY e.is_open DESC, e.starts_at IS NULL, e.starts_at ASC
    LIMIT 30
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT e.title, e.reward_points, a.checked_in_at
    FROM event_attendances a JOIN events e ON e.id = a.event_id
    WHERE a.user_id = ?
    ORDER BY a.checked_in_at DESC
    LIMIT 50
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$thMonths = ["", "ม.ค.", "ก.พ.", "มี.ค.", "เม.ย.", "พ.ค.", "มิ.ย.", "ก.ค.", "ส.ค.", "ก.ย.", "ต.ค.", "พ.ย.", "ธ.ค."];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>กิจกรรมโรงเรียน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/events.css">
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="ev-hero">
      <h1>กิจกรรมโรงเรียน</h1>
      <p>เช็คชื่อเข้าร่วมกิจกรรมด้วยการสแกน QR ง่ายๆ</p>
    </div>

    <div class="ev-actions">
      <a class="ev-action" href="event_my_qr.php">
        <div class="ic" style="background:#e0e7ff">
          <svg viewBox="0 0 24 24" style="fill:#4f46e5"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm11-2h2v2h-2v-2zm-3 0h2v3h-2v-3zm3 3h2v2h-2v-2zm2 2h3v3h-3v-3zm-5 1h3v2h-3v-2zm7-6h1v4h-1v-4z"/></svg>
        </div>
        <strong>QR ของฉัน</strong>
        <span>ให้สภาสแกนเช็คชื่อ</span>
      </a>
      <a class="ev-action" href="event_scan.php">
        <div class="ic" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M4 4h4V2H2v6h2V4zm16 0v4h2V2h-6v2h4zM4 20v-4H2v6h6v-2H4zm16 0h-4v2h6v-6h-2v4zM2 11h20v2H2v-2z"/></svg>
        </div>
        <strong>สแกนเช็คชื่อ</strong>
        <span>สแกน QR ของกิจกรรม</span>
      </a>
      <?php if ($isOrganizer || $isStaff): ?>
      <a class="ev-action" href="event_manage.php" style="grid-column:1 / -1;display:flex;align-items:center;gap:12px;text-align:left">
        <div class="ic" style="background:#fef9c3;margin:0">
          <svg viewBox="0 0 24 24" style="fill:#a16207"><path d="M19 3h-1V1h-2v2H8V1H6v2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zm0 16H5V8h14v11zM7 10h5v5H7v-5z"/></svg>
        </div>
        <div>
          <strong>จัดการกิจกรรม (สภานักเรียน)</strong>
          <span>สร้างกิจกรรม · สแกนเช็คชื่อ · ดูรายชื่อผู้เข้าร่วม</span>
        </div>
      </a>
      <?php endif; ?>
    </div>

    <div class="ev-section">กิจกรรมที่กำลังจะมาถึง</div>
    <div class="pg-card">
      <?php if (!$events): ?>
        <div class="empty">ยังไม่มีกิจกรรมในขณะนี้</div>
      <?php endif; ?>
      <?php foreach ($events as $ev): $t = $ev["starts_at"] ? strtotime($ev["starts_at"]) : null; ?>
        <div class="row-item">
          <div class="row-date">
            <?php if ($t): ?>
              <b><?= date("j", $t) ?></b><small><?= $thMonths[intval(date("n", $t))] ?></small>
            <?php else: ?>
              <small>ไม่ระบุ</small>
            <?php endif; ?>
          </div>
          <div class="row-info">
            <strong><?= e($ev["title"]) ?></strong>
            <span><?= $t ? date("H:i", $t) . " น." : "" ?><?= $ev["location_text"] ? " · " . e($ev["location_text"]) : "" ?></span>
            <?php if ($ev["reward_points"] > 0): ?>
              <span style="color:#a16207">เข้าร่วมรับ +<?= intval($ev["reward_points"]) ?> คะแนน</span>
            <?php endif; ?>
          </div>
          <?php if ($ev["attended"]): ?>
            <span class="badge done">เช็คชื่อแล้ว</span>
          <?php elseif ($ev["is_open"]): ?>
            <span class="badge open">เปิดเช็คชื่อ</span>
          <?php else: ?>
            <span class="badge closed">ยังไม่เปิด</span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="ev-section">ประวัติการเข้าร่วมของฉัน <span style="font-size:12.5px;color:#64748b;font-weight:500"><?= count($history) ?> กิจกรรม</span></div>
    <div class="pg-card">
      <?php if (!$history): ?>
        <div class="empty">ยังไม่เคยเข้าร่วมกิจกรรม</div>
      <?php endif; ?>
      <?php foreach ($history as $h): ?>
        <div class="row-item">
          <div class="row-info">
            <strong><?= e($h["title"]) ?></strong>
            <span>เช็คชื่อเมื่อ <?= e(eventDateText($h["checked_in_at"])) ?></span>
          </div>
          <?php if ($h["reward_points"] > 0): ?>
            <span class="badge pts">+<?= intval($h["reward_points"]) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
</body>
</html>
