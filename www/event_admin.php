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
            elseif ($r === "other_school") { $msg = $target["name"] . " ไม่ได้อยู่โรงเรียนของกิจกรรมนี้"; $msgType = "bad"; }
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
            $conn->commit();
            $msg = "ลบรายชื่อแล้ว";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "ลบไม่สำเร็จ"; $msgType = "bad";
        }
        // Also take them out of the event ("เข้างาน"), so they'd need a link again.
        try {
            $stmt = $conn->prepare("DELETE FROM event_joins WHERE event_id=? AND user_id=?");
            $stmt->bind_param("ii", $eventId, $targetId);
            $stmt->execute();
        } catch (mysqli_sql_exception $e) {
            // sql/add_event_joins.sql not run yet
        }
    }

    if ($action === "add_staff" && $isOwner) {
        $target = findUserByEmail($conn, trim($_POST["email"] ?? ""));
        if (!$target) {
            $msg = "ไม่พบผู้ใช้อีเมลนี้"; $msgType = "bad";
        } elseif ($event["school_id"] !== null && getUserSchool($conn, $target["id"])["school_id"] !== intval($event["school_id"])) {
            $msg = $target["name"] . " ไม่ได้อยู่โรงเรียนของกิจกรรมนี้"; $msgType = "bad";
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

    if ($action === "create_link" && $isOwner) {
        $label   = mb_substr(trim($_POST["label"] ?? ""), 0, 100, "UTF-8");
        $expires = eventLinkExpiryFromForm($_POST["expiry"] ?? "", $_POST["expires_custom"] ?? "");
        if ($expires === false) {
            $msg = "เวลาหมดอายุไม่ถูกต้อง (ต้องเป็นเวลาในอนาคต)"; $msgType = "bad";
        } else {
            $labelOrNull = $label !== "" ? $label : null;
            $created = false;
            for ($i = 0; $i < 5 && !$created; $i++) {
                $token = strtoupper(bin2hex(random_bytes(10)));
                try {
                    $stmt = $conn->prepare("INSERT INTO event_links (event_id, token, label, expires_at, created_by) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("issii", $eventId, $token, $labelOrNull, $expires, $userId);
                    $created = $stmt->execute();
                } catch (mysqli_sql_exception $e) {
                    if ($e->getCode() !== 1062) break; // not a token collision
                }
            }
            if ($created) {
                $msg = "สร้างลิงก์แล้ว ส่งลิงก์หรือให้นักเรียนสแกน QR ได้เลย";
            } else {
                $msg = "สร้างลิงก์ไม่สำเร็จ (ผู้ดูแลระบบต้องรัน sql/add_event_links.sql ก่อน)"; $msgType = "bad";
            }
        }
    }

    if ($action === "expire_link" && $isOwner) {
        $linkId = intval($_POST["link_id"] ?? 0);
        $now = time();
        $stmt = $conn->prepare("UPDATE event_links SET expires_at=? WHERE id=? AND event_id=? AND (expires_at IS NULL OR expires_at > ?)");
        $stmt->bind_param("iiii", $now, $linkId, $eventId, $now);
        $stmt->execute();
        $msg = "ปิดลิงก์แล้ว นักเรียนจะใช้ลิงก์นี้ไม่ได้อีก";
    }

    if ($action === "delete_link" && $isOwner) {
        $linkId = intval($_POST["link_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM event_links WHERE id=? AND event_id=?");
        $stmt->bind_param("ii", $linkId, $eventId);
        $stmt->execute();
        $msg = "ลบลิงก์แล้ว (รายชื่อที่เช็คชื่อไปแล้วยังอยู่)";
    }

    if ($action === "delete_event" && $isOwner) {
        $stmt = $conn->prepare("DELETE FROM events WHERE id=?");
        $stmt->bind_param("i", $eventId);
        $stmt->execute();
        redirect("event_manage.php");
    }

    $event = getEvent($conn, $eventId);
}

// Everyone who entered (เข้างาน) and/or was checked in, newest activity first.
$participants = getEventParticipants($conn, $eventId);
$counts       = eventParticipantCounts($conn, $eventId);
// Newest join / attendance ids on the page; the live feed only sends what changed after them.
$lastJoinId = $participants ? max(array_map(fn($p) => intval($p["join_id"] ?? 0), $participants)) : 0;
$lastAttId  = $participants ? max(array_map(fn($p) => intval($p["att_id"] ?? 0), $participants)) : 0;

// School students who haven't come (no entry, no check-in), grouped by class.
// All-school events (school_id NULL) have no single roster, so no list.
$absent = $event["school_id"] !== null ? getEventAbsentStudents($conn, $eventId, intval($event["school_id"])) : null;
$absentGroups = $absent !== null ? groupByClassRoom($absent, $absentNoClass) : [];

// Check-in links / QR for this event, newest first, with how many students used each.
$links = [];
$linksReady = true;
try {
    $stmt = $conn->prepare("
        SELECT l.*, (SELECT COUNT(*) FROM event_joins j WHERE j.link_id = l.id) AS used
        FROM event_links l
        WHERE l.event_id=?
        ORDER BY l.id DESC
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    $linksReady = false; // sql/add_event_links.sql not run yet
}

$stmt = $conn->prepare("SELECT u.id, u.name, u.email FROM event_staff es JOIN users u ON u.id = es.user_id WHERE es.event_id=? ORDER BY u.name");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$staff = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
      </p>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat"><b id="stat-attended" style="color:#16a34a"><?= $counts["attended"] ?></b><span>มาแล้ว (เช็คชื่อ)</span></div>
      <div class="stat"><b id="stat-waiting" style="color:#a16207"><?= $counts["waiting"] ?></b><span>เข้างาน รอเช็คชื่อ</span></div>
      <div class="stat"><b id="stat-absent" style="color:#dc2626"><?= $counts["absent"] === null ? "–" : $counts["absent"] ?></b><span>ยังไม่มา</span></div>
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
      <a class="adm-btn ghost" href="event_export_pdf.php?id=<?= $eventId ?>" target="_blank">พิมพ์ / PDF</a>
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

    <div class="ev-section">ลิงก์ / QR เข้างาน</div>
    <div class="pg-card">
      <?php if (!$linksReady): ?>
        <div class="empty">ยังใช้ไม่ได้ ผู้ดูแลระบบต้องรัน sql/add_event_links.sql และ sql/add_event_joins.sql ก่อน</div>
      <?php else: ?>
        <?php if ($isOwner): ?>
        <form method="post" class="adm-form" style="margin-top:6px">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="event_id" value="<?= $eventId ?>">
          <input type="hidden" name="action" value="create_link">
          <label>นักเรียนกดลิงก์หรือสแกน QR นี้เพื่อเข้างาน แล้วจึงสแกนเช็คชื่อจากหน้ากิจกรรมได้</label>
          <input type="text" name="label" maxlength="100" placeholder="ชื่อลิงก์ (ไม่ใส่ก็ได้) เช่น ม.5 คาบเช้า">
          <select name="expiry" onchange="document.getElementById('exp-custom').style.display = this.value === 'custom' ? 'block' : 'none'">
            <option value="15m">หมดอายุใน 15 นาที</option>
            <option value="30m">หมดอายุใน 30 นาที</option>
            <option value="1h" selected>หมดอายุใน 1 ชั่วโมง</option>
            <option value="3h">หมดอายุใน 3 ชั่วโมง</option>
            <option value="today">หมดอายุสิ้นวันนี้ (23:59 น.)</option>
            <option value="1d">หมดอายุใน 24 ชั่วโมง</option>
            <option value="custom">กำหนดวันเวลาเอง…</option>
            <option value="none">ไม่หมดอายุ</option>
          </select>
          <input type="datetime-local" name="expires_custom" id="exp-custom" style="display:none">
          <button class="adm-btn primary" type="submit">+ สร้างลิงก์ / QR</button>
        </form>
        <?php endif; ?>

        <?php if (!$event["is_open"] && $links): ?>
          <div class="adm-alert bad" style="margin:12px 0 0">นักเรียนเข้างานได้แล้ว แต่ตอนนี้ปิดเช็คชื่ออยู่ จะเช็คชื่อได้เมื่อเปิดเช็คชื่อ</div>
        <?php endif; ?>

        <?php foreach ($links as $l):
          $url     = eventLinkUrl($l["token"]);
          $expired = eventLinkExpired($l);
          $name    = $l["label"] !== null && $l["label"] !== "" ? $l["label"] : "ลิงก์ #" . intval($l["id"]);
        ?>
          <div class="row-item" style="flex-wrap:wrap;align-items:flex-start">
            <div class="row-info" style="min-width:0">
              <strong><?= e($name) ?></strong>
              <div class="tag-row" style="flex-wrap:wrap">
                <span class="badge <?= $expired ? "closed" : "open" ?>"><?= $expired ? "หมดอายุแล้ว" : "● ใช้ได้" ?></span>
                <span class="tag">ใช้แล้ว <span data-link-used="<?= intval($l["id"]) ?>"><?= intval($l["used"]) ?></span> คน</span>
              </div>
              <span>
                <?php if ($l["expires_at"] === null): ?>
                  ไม่หมดอายุ
                <?php else: ?>
                  <?= $expired ? "หมดอายุเมื่อ" : "หมดอายุ" ?> <?= e(thaiTimestampText($l["expires_at"])) ?>
                <?php endif; ?>
              </span>
            </div>
            <?php if ($isOwner): ?>
            <div style="display:flex;gap:6px">
              <?php if (!$expired): ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <input type="hidden" name="action" value="expire_link">
                <input type="hidden" name="link_id" value="<?= intval($l["id"]) ?>">
                <button type="submit" class="adm-btn ghost sm" onclick="return confirm('ปิดลิงก์นี้ตอนนี้เลย?')">ปิดลิงก์</button>
              </form>
              <?php endif; ?>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="event_id" value="<?= $eventId ?>">
                <input type="hidden" name="action" value="delete_link">
                <input type="hidden" name="link_id" value="<?= intval($l["id"]) ?>">
                <button type="submit" class="icon-btn" title="ลบลิงก์" onclick="return confirm('ลบลิงก์นี้? (รายชื่อที่เช็คชื่อไปแล้วยังอยู่)')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
                </button>
              </form>
            </div>
            <?php endif; ?>

            <?php if (!$expired): ?>
            <div style="flex-basis:100%;display:flex;gap:8px;margin-top:8px">
              <input type="text" readonly value="<?= e($url) ?>" onclick="this.select()" style="flex:1;min-width:0;font-size:12.5px;padding:9px 12px">
              <button type="button" class="adm-btn ghost sm" onclick="copyLink(this, <?= e(json_encode($url)) ?>)">คัดลอก</button>
              <button type="button" class="adm-btn primary sm" onclick="toggle('qr-<?= intval($l["id"]) ?>')">QR</button>
            </div>
            <div id="qr-<?= intval($l["id"]) ?>" style="display:none;flex-basis:100%;text-align:center;padding:14px 0 4px">
              <img src="<?= e(qrImageUrl($url, 320)) ?>" alt="QR เช็คชื่อ" loading="lazy" style="width:240px;max-width:100%;border-radius:16px;background:#fff">
              <div style="font-size:12.5px;color:#64748b;margin-top:6px">ให้นักเรียนสแกนด้วยปุ่มกล้องในแอป หรือกล้องมือถือก็ได้</div>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if (!$links): ?>
          <div class="empty" style="padding-bottom:8px">ยังไม่มีลิงก์ <?= $isOwner ? "สร้างลิงก์แล้วส่งให้นักเรียนในกลุ่มแชท หรือเปิด QR ให้สแกน" : "" ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="ev-section">
      <span>รายชื่อผู้เข้าร่วม (<span id="att-count"><?= count($participants) ?></span>)</span>
      <span class="live-dot" id="live-dot" title="อัปเดตอัตโนมัติ">● สด</span>
    </div>
    <div class="pg-card">
      <div class="search-box" id="att-search" <?= $participants ? "" : 'style="display:none"' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="att-q" placeholder="ค้นหาชื่อ" oninput="filterList(this.value)">
      </div>
      <div class="empty" id="att-empty" <?= $participants ? 'style="display:none"' : "" ?>>ยังไม่มีใครเข้างาน รายชื่อจะขึ้นที่นี่ทันทีเมื่อมีคนสแกน</div>
      <div id="att-list">
      <?php foreach ($participants as $p): ?>
        <?= eventParticipantRowHtml($p, $isOwner, $eventId) ?>
      <?php endforeach; ?>
      </div>
    </div>

    <?php if ($absent !== null): ?>
    <div class="ev-section">
      <span>ยังไม่มา (<?= count($absent) ?> คน)</span>
      <a href="event_admin.php?id=<?= $eventId ?>#absent">รีเฟรช</a>
    </div>
    <div class="pg-card" id="absent">
      <?php if (!$absent): ?>
        <div class="empty">นักเรียนในโรงเรียนมาครบทุกคน 🎉</div>
      <?php else: ?>
        <div style="font-size:12px;color:#64748b;padding:10px 0 2px">นักเรียนและสภาในโรงเรียนที่ยังไม่ได้เข้างานหรือเช็คชื่อ (รายการนี้ไม่อัปเดตสด กดรีเฟรช)</div>
        <?php foreach ($absentGroups as $grade => $rooms): foreach ($rooms as $room => $list): ?>
          <details class="absent-room">
            <summary><span><?= e($room !== "" ? $room : $grade) ?></span><span class="badge closed"><?= count($list) ?> คน</span></summary>
            <?php foreach ($list as $u): ?>
              <div class="absent-row"><span class="no"><?= e($u["student_no"] ?? "") ?></span><?= e($u["display_name"]) ?><?= $u["school_role"] === "council" ? ' <small>(สภา)</small>' : "" ?></div>
            <?php endforeach; ?>
          </details>
        <?php endforeach; endforeach; ?>
        <?php if ($absentNoClass): ?>
          <details class="absent-room">
            <summary><span>ไม่ระบุชั้น</span><span class="badge closed"><?= count($absentNoClass) ?> คน</span></summary>
            <?php foreach ($absentNoClass as $u): ?>
              <div class="absent-row"><span class="no"></span><?= e($u["display_name"]) ?></div>
            <?php endforeach; ?>
          </details>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

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
      <button type="submit" class="adm-btn danger block" onclick="return confirm('ลบกิจกรรมนี้และรายชื่อทั้งหมด? (ประวัติการมาของกิจกรรมนี้จะหายไปด้วย)')">ลบกิจกรรม</button>
    </form>
    <?php endif; ?>
  </main>
  <script>
  function toggle(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  // ── Live participant list: ask the server every 3 s for new joins / check-ins ──
  (function () {
    const EVENT_ID = <?= $eventId ?>;
    let lastJoin = <?= $lastJoinId ?>;
    let lastAtt  = <?= $lastAttId ?>;
    let busy = false;
    const list = document.getElementById('att-list');
    const dot  = document.getElementById('live-dot');
    const setText = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };

    async function poll() {
      if (busy || document.hidden) return;
      busy = true;
      try {
        const res = await fetch('event_attendees_feed.php?id=' + EVENT_ID + '&join=' + lastJoin + '&att=' + lastAtt, { cache: 'no-store' });
        if (!res.ok) throw new Error(res.status);
        const data = await res.json();

        // Changed rows arrive newest first; handle oldest first so the newest ends on top.
        // A student who was just checked in replaces their "waiting" row and moves to the top.
        data.rows.slice().reverse().forEach(r => {
          const old = list.querySelector('.row-item[data-user="' + r.user + '"]');
          if (old) old.remove();
          const tpl = document.createElement('template');
          tpl.innerHTML = r.html.trim();
          const row = tpl.content.firstElementChild;
          row.classList.add('row-new');
          list.prepend(row);
          lastJoin = Math.max(lastJoin, r.join);
          lastAtt  = Math.max(lastAtt, r.att);
        });

        // Drop rows that were removed (e.g. by another council member).
        const alive = new Set(data.users);
        list.querySelectorAll('.row-item[data-user]').forEach(row => {
          if (!alive.has(Number(row.dataset.user))) row.remove();
        });

        setText('att-count', data.users.length);
        setText('stat-joined', data.counts.joined);
        setText('stat-attended', data.counts.attended);
        setText('stat-waiting', data.counts.waiting);
        if (data.counts.absent !== null) setText('stat-absent', data.counts.absent);
        Object.entries(data.linkUsed).forEach(([id, n]) => {
          const el = document.querySelector('[data-link-used="' + id + '"]');
          if (el) el.textContent = n;
        });
        document.getElementById('att-empty').style.display = data.users.length ? 'none' : '';
        document.getElementById('att-search').style.display = data.users.length ? '' : 'none';
        if (data.rows.length) filterList(document.getElementById('att-q').value);
        dot.classList.remove('off');
      } catch (e) {
        dot.classList.add('off');
      } finally {
        busy = false;
      }
    }

    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
  })();

  function copyLink(btn, url) {
    const done = () => { const t = btn.textContent; btn.textContent = 'คัดลอกแล้ว ✓'; setTimeout(() => btn.textContent = t, 1600); };
    if (navigator.clipboard) {
      navigator.clipboard.writeText(url).then(done).catch(() => prompt('คัดลอกลิงก์นี้', url));
    } else {
      prompt('คัดลอกลิงก์นี้', url);
    }
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
