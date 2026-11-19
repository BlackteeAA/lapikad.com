<?php
// One event's control panel: open/close check-in, scan, display QR, attendee list, staff.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? $_POST["event_id"] ?? 0);
$event   = getEvent($conn, $eventId);
$myRole  = eventRole($conn, $event, $userId);

if (!$myRole) redirect("event_manage.php");
$isOwner = $myRole === "owner";

$msg = "";
$msgType = "good";
if (!empty($_GET["created"])) $msg = "สร้างกิจกรรมสำเร็จ กด \"เปิดเช็คชื่อ\" เมื่อพร้อมเริ่ม";

function findUserByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("event_admin.php?id=" . $eventId);
    $action = $_POST["action"] ?? "";

    if ($action === "toggle_open" && $isOwner) {
        $stmt = $conn->prepare("UPDATE events SET is_open = 1 - is_open WHERE id=?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        $msg = $event["is_open"] ? "ปิดเช็คชื่อแล้ว" : "เปิดเช็คชื่อแล้ว";
    }

    if ($action === "manual_add") {
        $target = findUserByEmail($conn, trim($_POST["email"] ?? ""));
        if (!$target) {
            $msg = "ไม่พบผู้ใช้อีเมลนี้"; $msgType = "bad";
        } else {
            $r = recordAttendance($conn, $event, intval($target["id"]), "manual", $userId);
            if ($r === "ok")          $msg = "เพิ่ม " . $target["name"] . " เข้ากิจกรรมแล้ว";
            elseif ($r === "already") { $msg = $target["name"] . " เช็คชื่อไปแล้ว"; $msgType = "bad"; }
            else                      { $msg = "บันทึกไม่สำเร็จ"; $msgType = "bad"; }
        }
    }

    if ($action === "remove_attendance" && $isOwner) {
        $targetId = intval($_POST["user_id"] ?? 0);
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("DELETE FROM event_attendances WHERE event_id=? AND user_id=?");
            $stmt->bind_param("ii", $eventId, $targetId);
            $stmt->execute();
            $pts = intval($event["reward_points"]);
            if ($stmt->affected_rows > 0 && $pts > 0) {
                $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id=?");
                $stmt->bind_param("ii", $pts, $targetId);
                $stmt->execute();
                $neg = -$pts;
                $reason = "ยกเลิกการเช็คชื่อ: " . $event["title"];
                $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiis", $targetId, $userId, $neg, $reason);
                $stmt->execute();
            }
            $conn->commit();
            $msg = "ลบรายชื่อแล้ว";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "ลบไม่สำเร็จ"; $msgType = "bad";
        }
    }

    if ($action === "add_staff" && $isOwner) {
        $target = findUserByEmail($conn, trim($_POST["email"] ?? ""));
        if (!$target) {
            $msg = "ไม่พบผู้ใช้อีเมลนี้"; $msgType = "bad";
        } else {
            $stmt = $conn->prepare("INSERT IGNORE INTO event_staff (event_id, user_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $eventId, $target["id"]);
            $stmt->execute();
            $msg = "เพิ่ม " . $target["name"] . " เป็นผู้ช่วยสแกนแล้ว";
        }
    }

    if ($action === "remove_staff" && $isOwner) {
        $targetId = intval($_POST["user_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM event_staff WHERE event_id=? AND user_id=?");
        $stmt->bind_param("ii", $eventId, $targetId);
        $stmt->execute();
        $msg = "ลบผู้ช่วยแล้ว";
    }

    if ($action === "delete_event" && $isOwner) {
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        redirect("event_manage.php");
    }

    $event = getEvent($conn, $eventId);
}

$stmt = $conn->prepare("
    SELECT a.user_id, a.method, a.checked_in_at, u.name, u.email, u.avatar_url, s.name AS scanner_name
    FROM event_attendances a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN users s ON s.id = a.checked_in_by
    WHERE a.event_id=?
    ORDER BY a.checked_in_at DESC
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$attendees = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$byStaff = count(array_filter($attendees, fn($a) => $a["method"] !== "self_scan"));
$bySelf  = count($attendees) - $byStaff;

$stmt = $conn->prepare("SELECT u.id, u.name, u.email FROM event_staff es JOIN users u ON u.id = es.user_id WHERE es.event_id=? ORDER BY u.name");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$staff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$methodLabel = ["staff_scan" => "สภาสแกน", "self_scan" => "สแกนเอง", "manual" => "เพิ่มเอง"];
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
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="event_manage.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="ev-hero">
      <h1><?= e($event["title"]) ?></h1>
      <p>
        <?= $event["starts_at"] ? e(eventDateText($event["starts_at"])) : "ไม่ระบุวันเวลา" ?>
        <?= $event["location_text"] ? " · " . e($event["location_text"]) : "" ?>
      </p>
      <?php if ($event["description"]): ?>
        <p style="margin-top:6px"><?= nl2br(e($event["description"])) ?></p>
      <?php endif; ?>
      <p style="margin-top:10px">
        <span class="badge <?= $event["is_open"] ? "open" : "closed" ?>"><?= $event["is_open"] ? "● เปิดเช็คชื่ออยู่" : "ปิดเช็คชื่อ" ?></span>
        <?php if ($event["reward_points"] > 0): ?>
          <span class="badge pts">+<?= intval($event["reward_points"]) ?> คะแนน</span>
        <?php endif; ?>
      </p>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat"><b><?= count($attendees) ?></b><span>ผู้เข้าร่วม</span></div>
      <div class="stat"><b><?= $byStaff ?></b><span>สภาสแกน/เพิ่ม</span></div>
      <div class="stat"><b><?= $bySelf ?></b><span>สแกนเอง</span></div>
    </div>

    <?php if ($isOwner): ?>
    <form method="post" style="margin-bottom:10px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="event_id" value="<?= $eventId ?>">
      <input type="hidden" name="action" value="toggle_open">
      <button class="adm-btn block <?= $event["is_open"] ? "danger" : "green" ?>" type="submit">
        <?= $event["is_open"] ? "ปิดเช็คชื่อ" : "เปิดเช็คชื่อ" ?>
      </button>
    </form>
    <?php endif; ?>

    <div class="btn-grid">
      <a class="adm-btn primary" href="event_staff_scan.php?id=<?= $eventId ?>">สแกน QR นักเรียน</a>
      <?php if ($event["allow_self_checkin"]): ?>
        <a class="adm-btn ghost" href="event_display.php?id=<?= $eventId ?>" target="_blank">แสดง QR บนจอ</a>
      <?php else: ?>
        <span class="adm-btn ghost" style="opacity:.5;cursor:default">ปิดการสแกนเอง</span>
      <?php endif; ?>
      <a class="adm-btn ghost" href="event_export.php?id=<?= $eventId ?>">ดาวน์โหลดรายชื่อ (Excel)</a>
      <button type="button" class="adm-btn ghost" onclick="toggle('manual-form')">+ เพิ่มชื่อเอง</button>
    </div>

    <?php if (!$event["is_open"]): ?>
      <div class="adm-alert bad">ตอนนี้ปิดเช็คชื่ออยู่ ต้องเปิดก่อนจึงจะสแกนได้</div>
    <?php endif; ?>

    <div id="manual-form" class="pg-card" style="display:none">
      <form method="post" class="adm-form">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="event_id" value="<?= $eventId ?>">
        <input type="hidden" name="action" value="manual_add">
        <label>เพิ่มนักเรียนที่ไม่มีมือถือ / สแกนไม่ได้</label>
        <input type="email" name="email" placeholder="อีเมลของนักเรียน" required>
        <button class="adm-btn primary" type="submit">เพิ่มรายชื่อ</button>
      </form>
    </div>

    <div class="ev-section">รายชื่อผู้เข้าร่วม (<?= count($attendees) ?>)</div>
    <div class="pg-card">
      <?php if ($attendees): ?>
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="ค้นหาชื่อ" oninput="filterList(this.value)">
      </div>
      <?php else: ?>
        <div class="empty">ยังไม่มีผู้เช็คชื่อ</div>
      <?php endif; ?>
      <div id="att-list">
      <?php foreach ($attendees as $a): ?>
        <div class="row-item" data-search="<?= e(mb_strtolower($a["name"] . " " . $a["email"], "UTF-8")) ?>">
          <div class="avatar">
            <?php if (!empty($a["avatar_url"])): ?>
              <img src="<?= e($a["avatar_url"]) ?>" alt="">
            <?php else: ?>
              <?= e(mb_substr($a["name"], 0, 1, "UTF-8")) ?>
            <?php endif; ?>
          </div>
          <div class="row-info">
            <strong><?= e($a["name"]) ?></strong>
            <span><?= date("H:i", strtotime($a["checked_in_at"])) ?> น. · <?= $methodLabel[$a["method"]] ?? "" ?><?= $a["scanner_name"] ? " โดย " . e($a["scanner_name"]) : "" ?></span>
          </div>
          <?php if ($isOwner): ?>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="event_id" value="<?= $eventId ?>">
            <input type="hidden" name="action" value="remove_attendance">
            <input type="hidden" name="user_id" value="<?= $a["user_id"] ?>">
            <button type="submit" class="icon-btn" title="ลบ" onclick="return confirm('ลบรายชื่อนี้ออกจากกิจกรรม?')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
            </button>
          </form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
      </div>
    </div>

    <?php if ($isOwner): ?>
    <div class="ev-section">ผู้ช่วยสแกน (สมาชิกสภา)</div>
    <div class="pg-card">
      <form method="post" class="adm-form" style="grid-template-columns:1fr auto">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="event_id" value="<?= $eventId ?>">
        <input type="hidden" name="action" value="add_staff">
        <input type="email" name="email" placeholder="อีเมลสมาชิกสภา" required>
        <button class="adm-btn primary" type="submit">เพิ่ม</button>
      </form>
      <?php foreach ($staff as $s): ?>
        <div class="row-item">
          <div class="row-info">
            <strong><?= e($s["name"]) ?></strong>
            <span><?= e($s["email"]) ?></span>
          </div>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="event_id" value="<?= $eventId ?>">
            <input type="hidden" name="action" value="remove_staff">
            <input type="hidden" name="user_id" value="<?= $s["id"] ?>">
            <button type="submit" class="icon-btn" title="ลบ" onclick="return confirm('ลบผู้ช่วยคนนี้?')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
          </form>
        </div>
      <?php endforeach; ?>
      <?php if (!$staff): ?>
        <div class="empty" style="padding-top:4px">ผู้ช่วยสแกนได้และดูรายชื่อได้ แต่แก้ไขกิจกรรมไม่ได้</div>
      <?php endif; ?>
    </div>

    <form method="post" style="margin:6px 0 30px">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="event_id" value="<?= $eventId ?>">
      <input type="hidden" name="action" value="delete_event">
      <button type="submit" class="adm-btn danger block" onclick="return confirm('ลบกิจกรรมนี้และรายชื่อทั้งหมด? (คะแนนที่แจกไปแล้วจะไม่ถูกหักคืน)')">ลบกิจกรรม</button>
    </form>
    <?php endif; ?>
  </main>
  <script>
  function toggle(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  function filterList(q) {
    q = q.trim().toLowerCase();
    document.querySelectorAll('#att-list .row-item').forEach(row => {
      row.style.display = !q || row.dataset.search.includes(q) ? 'flex' : 'none';
    });
  }
  </script>
</body>
</html>
