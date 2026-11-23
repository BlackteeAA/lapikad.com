<?php
require_once "_events.php";

$userId = intval($_SESSION["user_id"]);
if (($_SESSION["role"] ?? "") !== "admin" && !hasStudentInfo($conn, $userId)) {
    redirect("event_student_info.php?next=event_my_qr.php");
}
$token  = getEventToken($conn, $userId);

$stmt = $conn->prepare("SELECT name, student_name, avatar_url FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT COALESCE(MAX(id),0) m FROM event_attendances WHERE user_id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$lastAttendanceId = intval($stmt->get_result()->fetch_assoc()["m"]);

$displayName = $me["student_name"] !== "" && $me["student_name"] !== null ? $me["student_name"] : $me["name"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>QR เช็คชื่อของฉัน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box;margin:0;padding:0; }
    body {
      font-family:'Kanit',sans-serif;min-height:100vh;padding:20px;
      background:linear-gradient(135deg,#0ea5e9,#6366f1);
      display:flex;align-items:center;justify-content:center;
    }
    .card {
      width:100%;max-width:380px;background:#fff;border-radius:28px;padding:26px 22px 22px;
      text-align:center;position:relative;box-shadow:0 20px 60px rgba(0,0,0,.25);
    }
    .close {
      position:absolute;top:16px;right:16px;width:30px;height:30px;border-radius:50%;
      background:#f1f5f9;color:#64748b;text-decoration:none;font-weight:700;
      display:flex;align-items:center;justify-content:center;
    }
    .avatar {
      width:64px;height:64px;border-radius:50%;margin:0 auto 8px;overflow:hidden;
      background:linear-gradient(135deg,#0ea5e9,#6366f1);color:#fff;font-size:26px;font-weight:700;
      display:flex;align-items:center;justify-content:center;
    }
    .avatar img { width:100%;height:100%;object-fit:cover; }
    h1 { font-size:20px;color:#0f172a;font-weight:700; }
    .sub { font-size:13px;color:#64748b;margin:2px 0 16px; }
    .qr { width:250px;height:250px;border-radius:16px;border:1px solid #e2e8f0; }
    .code { font-family:monospace;font-size:13px;color:#94a3b8;letter-spacing:1px;margin:8px 0 14px; }
    .hint { font-size:12.5px;color:#475569;background:#f8fafc;border-radius:12px;padding:10px 12px; }
    .ok {
      display:none;position:fixed;inset:0;z-index:10;background:#16a34a;color:#fff;
      flex-direction:column;align-items:center;justify-content:center;padding:24px;text-align:center;
    }
    .ok.show { display:flex; }
    .ok .mark { width:96px;height:96px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:52px;margin-bottom:14px; }
    .ok h2 { font-size:26px; }
    .ok p { font-size:15px;opacity:.95;margin:6px 0 22px; }
    .ok button { background:#fff;color:#16a34a;border:none;border-radius:999px;padding:12px 28px;font-family:inherit;font-size:15px;font-weight:600; }
  </style>
</head>
<body>
  <div class="card">
    <a class="close" href="events.php">✕</a>
    <div class="avatar">
      <?php if (!empty($me["avatar_url"])): ?>
        <img src="<?= e($me["avatar_url"]) ?>" alt="">
      <?php else: ?>
        <?= e(mb_substr($displayName, 0, 1, "UTF-8")) ?>
      <?php endif; ?>
    </div>
    <h1><?= e($displayName) ?></h1>
    <p class="sub">QR เช็คชื่อประจำตัว</p>

    <?php if ($token): ?>
      <img class="qr" src="<?= e(qrImageUrl(EVENT_STUDENT_QR_PREFIX . $token, 320)) ?>" alt="QR Code">
      <div class="code"><?= e(chunk_split($token, 4, " ")) ?></div>
    <?php else: ?>
      <p class="sub" style="color:#e11d48">สร้าง QR ไม่สำเร็จ กรุณาลองใหม่</p>
    <?php endif; ?>

    <div class="hint">เปิดความสว่างหน้าจอให้สูง แล้วยื่นให้สภานักเรียนสแกน<br>ห้ามส่ง QR นี้ให้ผู้อื่น</div>
  </div>

  <div class="ok" id="ok">
    <div class="mark">✓</div>
    <h2>เช็คชื่อสำเร็จ</h2>
    <p id="ok-title"></p>
    <button type="button" onclick="document.getElementById('ok').classList.remove('show')">ตกลง</button>
  </div>

  <script>
  let lastId = <?= $lastAttendanceId ?>;
  async function poll() {
    try {
      const res = await fetch('event_my_status.php?after=' + lastId, { cache: 'no-store' });
      const data = await res.json();
      if (data.id && data.id > lastId) {
        lastId = data.id;
        document.getElementById('ok-title').textContent = data.title + (data.points > 0 ? ' · +' + data.points + ' คะแนน' : '');
        document.getElementById('ok').classList.add('show');
        if (navigator.vibrate) navigator.vibrate([120, 60, 120]);
      }
    } catch (e) {}
  }
  setInterval(poll, 3000);
  </script>
</body>
</html>
