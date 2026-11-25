<?php
// Student's page for one event ("หน้ากิจกรรม"), reached after entering through a
// council link (event_link.php). Shows which event they're in, whether they're
// checked in, their personal QR, and the button to scan the event's QR to check
// in — that only works from here, not from the camera button in the bottom nav.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!$event) redirect("dashboard.php");
if (!hasJoinedEvent($conn, $eventId, $userId)) {
    // Council/teachers who didn't enter as a student go to the control panel.
    redirect(eventRole($conn, $event, $userId) ? "event_admin.php?id=" . $eventId : "dashboard.php");
}

$mySchool = getUserSchool($conn, $userId);

$stmt = $conn->prepare("SELECT name, student_name, class_room, student_no, avatar_url FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$myName = trim($me["student_name"] ?? "") !== "" ? $me["student_name"] : $me["name"];

// Am I already checked in?
$stmt = $conn->prepare("SELECT id, method, checked_in_at FROM event_attendances WHERE event_id=? AND user_id=?");
$stmt->bind_param("ii", $eventId, $userId);
$stmt->execute();
$myAttendance = $stmt->get_result()->fetch_assoc();

// Newest attendance id of mine, for the live "checked in" check below.
$stmt = $conn->prepare("SELECT COALESCE(MAX(id),0) m FROM event_attendances WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$lastAttendanceId = intval($stmt->get_result()->fetch_assoc()["m"]);

// Events I came to.
$stmt = $conn->prepare("
    SELECT e.title, a.checked_in_at
    FROM event_attendances a JOIN events e ON e.id = a.event_id
    WHERE a.user_id = ?
    ORDER BY a.checked_in_at DESC
    LIMIT 20
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$myRate    = !empty($mySchool["school_id"]) && $mySchool["school_role"] !== "teacher"
    ? getMyAttendanceRate($conn, $userId, intval($mySchool["school_id"])) : null;
$canScan   = $event["is_open"] && $event["allow_self_checkin"] && !$myAttendance;
$justJoined = !empty($_GET["joined"]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($event["title"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/events.css">
  <style>
    body { font-family:'Kanit',sans-serif; }
    .er-now {
      display:flex; align-items:center; gap:12px;
      padding:14px 16px; margin-bottom:14px; border-radius:20px;
      background:linear-gradient(135deg,#2563eb,#6366f1); color:#fff;
    }
    .er-now .pulse { width:10px; height:10px; border-radius:50%; background:#4ade80; flex-shrink:0; box-shadow:0 0 0 0 rgba(74,222,128,.7); animation:erPulse 1.8s infinite; }
    @keyframes erPulse { 70% { box-shadow:0 0 0 10px rgba(74,222,128,0); } 100% { box-shadow:0 0 0 0 rgba(74,222,128,0); } }
    .er-now small { display:block; font-size:12px; opacity:.85; }
    .er-now strong { display:block; font-size:17px; line-height:1.3; }
    .er-now span.meta { display:block; font-size:12.5px; opacity:.85; margin-top:2px; }
    .er-joined { padding:12px 16px; border-radius:16px; background:#dcfce7; color:#15803d; font-weight:500; font-size:14px; margin-bottom:14px; }
    .er-status { display:flex; align-items:center; gap:12px; padding:14px 16px; border-radius:18px; background:#fff; margin-bottom:14px; }
    .er-status .ic { width:42px; height:42px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
    .er-status.done .ic { background:#dcfce7; color:#16a34a; }
    .er-status.wait .ic { background:#fef9c3; color:#a16207; }
    .er-status strong { display:block; font-size:15px; color:#0f172a; }
    .er-status span { font-size:12.5px; color:#64748b; }
    .ev-action.disabled { opacity:.5; pointer-events:none; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <!-- Which event I'm in -->
    <div class="er-now">
      <span class="pulse"></span>
      <div style="min-width:0">
        <small>คุณอยู่ในกิจกรรม</small>
        <strong><?= e($event["title"]) ?></strong>
        <?php if ($event["starts_at"] || $event["location_text"]): ?>
          <span class="meta">
            <?= $event["starts_at"] ? e(eventDateText($event["starts_at"])) : "" ?>
            <?= $event["location_text"] ? ($event["starts_at"] ? " · " : "") . e($event["location_text"]) : "" ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($justJoined): ?>
      <div class="er-joined">✓ เข้างานสำเร็จ อย่าลืมกด "สแกนเช็คชื่อ" หรือให้สภาสแกน QR ของคุณ</div>
    <?php endif; ?>

    <!-- Me -->
    <div class="ev-hero">
      <?php if (!empty($mySchool["school_name"])): ?>
      <div class="ev-school">
        <?= schoolLogoHtml(["name" => $mySchool["school_name"], "logo_url" => $mySchool["school_logo"]], 34) ?>
        <span><?= e($mySchool["school_name"]) ?></span>
      </div>
      <?php endif; ?>
      <h1><?= e($myName) ?></h1>
      <?php if (!empty($me["class_room"])): ?>
        <p>ชั้น <?= e($me["class_room"]) ?> · เลขที่ <?= e($me["student_no"]) ?></p>
      <?php endif; ?>
      <?php if ($myRate && $myRate["total"]): ?>
      <div class="ev-stats">
        <div>
          <span>เข้าร่วมกิจกรรม</span>
          <b><?= $myRate["attended"] ?> <small>จาก <?= $myRate["total"] ?> กิจกรรม</small></b>
        </div>
        <div>
          <span>อัตราการมา</span>
          <b><?= attendancePercent($myRate["attended"], $myRate["total"]) ?>%</b>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Check-in status for this event -->
    <?php if ($myAttendance): ?>
      <div class="er-status done">
        <div class="ic">✓</div>
        <div>
          <strong>เช็คชื่อกิจกรรมนี้แล้ว</strong>
          <span>เมื่อ <?= e(eventDateText($myAttendance["checked_in_at"])) ?></span>
        </div>
      </div>
    <?php else: ?>
      <div class="er-status wait">
        <div class="ic">!</div>
        <div>
          <strong>ยังไม่ได้เช็คชื่อ</strong>
          <span>
            <?php if (!$event["is_open"]): ?>
              สภายังไม่เปิดเช็คชื่อ รอสักครู่
            <?php elseif ($event["allow_self_checkin"]): ?>
              สแกน QR บนจอของกิจกรรม หรือให้สภาสแกน QR ของคุณ
            <?php else: ?>
              ให้สภาสแกน QR ของคุณเพื่อเช็คชื่อ
            <?php endif; ?>
          </span>
        </div>
      </div>
    <?php endif; ?>

    <div class="ev-actions">
      <a class="ev-action" href="event_my_qr.php?id=<?= $eventId ?>">
        <div class="ic" style="background:#e0e7ff">
          <svg viewBox="0 0 24 24" style="fill:#4f46e5"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm11-2h2v2h-2v-2zm-3 0h2v3h-2v-3zm3 3h2v2h-2v-2zm2 2h3v3h-3v-3zm-5 1h3v2h-3v-2zm7-6h1v4h-1v-4z"/></svg>
        </div>
        <strong>QR ของฉัน</strong>
        <span>ให้สภาสแกนเช็คชื่อ</span>
      </a>
      <a class="ev-action <?= $canScan ? "" : "disabled" ?>" href="event_scan.php?id=<?= $eventId ?>">
        <div class="ic" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M4 4h4V2H2v6h2V4zm16 0v4h2V2h-6v2h4zM4 20v-4H2v6h6v-2H4zm16 0h-4v2h6v-6h-2v4zM2 11h20v2H2v-2z"/></svg>
        </div>
        <strong>สแกนเช็คชื่อ</strong>
        <span><?= $myAttendance ? "เช็คชื่อแล้ว" : ($canScan ? "สแกน QR บนจอกิจกรรม" : "ยังสแกนไม่ได้") ?></span>
      </a>
    </div>

    <?php if (!empty($event["description"])): ?>
    <div class="ev-section">รายละเอียดกิจกรรม</div>
    <div class="pg-card" style="padding:16px 18px;color:#334155;font-size:14px">
      <?= nl2br(e($event["description"])) ?>
    </div>
    <?php endif; ?>

    <div class="ev-section">ประวัติการเข้าร่วมของฉัน <span style="font-size:12.5px;color:#64748b;font-weight:500"><?= count($history) ?> กิจกรรม</span></div>
    <div class="pg-card">
      <?php if (!$history): ?>
        <div class="empty">ยังไม่เคยเช็คชื่อกิจกรรม</div>
      <?php endif; ?>
      <?php foreach ($history as $h): ?>
        <div class="row-item">
          <div class="row-info">
            <strong><?= e($h["title"]) ?></strong>
            <span>เมื่อ <?= e(eventDateText($h["checked_in_at"])) ?></span>
          </div>
          <span class="badge done">มา</span>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <?php if (!$myAttendance): ?>
  <script>
  // When the council scans my QR (or I'm checked in another way), refresh to show it.
  (function () {
    let lastId = <?= $lastAttendanceId ?>;
    async function poll() {
      if (document.hidden) return;
      try {
        const res = await fetch('event_my_status.php?after=' + lastId, { cache: 'no-store' });
        const data = await res.json();
        if (data.id) location.reload();
      } catch (e) {}
    }
    setInterval(poll, 4000);
  })();
  </script>
  <?php endif; ?>
</body>
</html>
