<?php
// Full-screen rotating check-in QR for a projector / TV. ?json=1 returns the
// current QR + live count for the page's own refresh loop.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!eventRole($conn, $event, $userId)) redirect("event_manage.php");

function displayState($conn, $event) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM event_attendances WHERE event_id=?");
    $stmt->bind_param("i", $event["id"]);
    $stmt->execute();
    $count = intval($stmt->get_result()->fetch_assoc()["c"]);

    $stmt = $conn->prepare("
        SELECT u.name FROM event_attendances a JOIN users u ON u.id = a.user_id
        WHERE a.event_id=? ORDER BY a.id DESC LIMIT 6
    ");
    $stmt->bind_param("i", $event["id"]);
    $stmt->execute();
    $recent = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), "name");

    $slot = eventQrSlot();
    return [
        "open"     => (bool)$event["is_open"],
        "self"     => (bool)$event["allow_self_checkin"],
        "qr"       => qrImageUrl(eventCheckinUrl($event, $slot), 600),
        "slot"     => $slot,
        "next_in"  => ($slot + 1) * EVENT_QR_SLOT_SECONDS - time(),
        "count"    => $count,
        "recent"   => $recent,
    ];
}

$state = displayState($conn, $event);

if (!empty($_GET["json"])) {
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($state, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR เช็คชื่อ · <?= e($event["title"]) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing:border-box;margin:0;padding:0; }
    body {
      font-family:'Kanit',sans-serif;min-height:100vh;color:#fff;padding:3vh 4vw;
      background:linear-gradient(135deg,#0ea5e9,#6366f1);
      display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2.5vh;
    }
    h1 { font-size:clamp(24px,4.2vw,56px);font-weight:700;text-align:center;line-height:1.2; }
    .sub { font-size:clamp(14px,1.8vw,24px);opacity:.9;text-align:center; }
    .wrap { display:flex;gap:4vw;align-items:center;justify-content:center;flex-wrap:wrap; }
    .qr-card { background:#fff;border-radius:28px;padding:18px;box-shadow:0 24px 60px rgba(0,0,0,.25);text-align:center; }
    .qr-card img { width:min(56vh,80vw);height:min(56vh,80vw);display:block; }
    .timer { color:#4f46e5;font-size:clamp(13px,1.4vw,18px);margin-top:8px;font-weight:500; }
    .side { min-width:240px;max-width:360px; }
    .count { font-size:clamp(48px,8vw,110px);font-weight:700;line-height:1; }
    .count-lbl { font-size:clamp(14px,1.6vw,22px);opacity:.9;margin-bottom:18px; }
    .recent { list-style:none;display:grid;gap:8px; }
    .recent li { background:rgba(255,255,255,.18);border-radius:12px;padding:8px 14px;font-size:clamp(14px,1.5vw,20px);animation:pop .4s ease; }
    @keyframes pop { from { transform:scale(.9);opacity:0; } }
    .closed { background:#fff;color:#dc2626;border-radius:20px;padding:30px 40px;font-size:clamp(20px,3vw,40px);font-weight:700;text-align:center; }
    .fs { position:fixed;top:14px;right:14px;background:rgba(0,0,0,.2);color:#fff;border:none;border-radius:999px;padding:8px 14px;font-family:inherit;cursor:pointer; }
  </style>
</head>
<body>
  <button class="fs" onclick="document.documentElement.requestFullscreen && document.documentElement.requestFullscreen()">เต็มจอ</button>

  <h1><?= e($event["title"]) ?></h1>
  <div class="sub">เปิดแอป ล่าพิกัด.com → กิจกรรมโรงเรียน → สแกนเช็คชื่อ</div>

  <div class="wrap">
    <div id="qr-area"></div>
    <div class="side">
      <div class="count" id="count"><?= $state["count"] ?></div>
      <div class="count-lbl">คนเช็คชื่อแล้ว</div>
      <ul class="recent" id="recent"></ul>
    </div>
  </div>

  <script>
  let state = <?= json_encode($state, JSON_UNESCAPED_UNICODE) ?>;
  let nextIn = state.next_in;
  let lastSlot = null;

  function esc(s) { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

  function render() {
    const area = document.getElementById('qr-area');
    if (!state.open || !state.self) {
      area.innerHTML = '<div class="closed">' + (state.open ? 'กิจกรรมนี้ปิดการสแกนเอง' : 'ยังไม่เปิดเช็คชื่อ') + '</div>';
      lastSlot = null;
    } else if (state.slot !== lastSlot) {
      // Preload so the QR never flashes blank while rotating.
      const img = new Image();
      img.onload = () => {
        area.innerHTML = '<div class="qr-card"></div>';
        const card = area.firstChild;
        card.appendChild(img);
        const t = document.createElement('div');
        t.className = 'timer'; t.id = 'timer';
        card.appendChild(t);
        tickTimer();
      };
      img.src = state.qr;
      img.alt = 'QR เช็คชื่อ';
      lastSlot = state.slot;
    }
    document.getElementById('count').textContent = state.count;
    document.getElementById('recent').innerHTML = state.recent.map(n => '<li>✓ ' + esc(n) + '</li>').join('');
  }

  function tickTimer() {
    const t = document.getElementById('timer');
    if (t) t.textContent = 'QR เปลี่ยนใน ' + Math.max(0, nextIn) + ' วินาที';
  }

  async function refresh() {
    try {
      const res = await fetch('event_display.php?id=<?= $eventId ?>&json=1', { cache: 'no-store' });
      state = await res.json();
      nextIn = state.next_in;
      render();
    } catch (e) {}
  }

  setInterval(() => {
    nextIn--;
    tickTimer();
    if (nextIn <= 0) refresh();
  }, 1000);
  setInterval(refresh, 4000);
  render();
  </script>
</body>
</html>
