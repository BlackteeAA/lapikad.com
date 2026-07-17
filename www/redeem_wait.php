<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$code   = strtoupper(trim($_GET["code"] ?? ""));

$stmt = $conn->prepare("
    SELECT sr.*, r.name AS reward_name, r.image_url, p.name AS place_name
    FROM shop_redemptions sr
    JOIN rewards r ON r.id = sr.reward_id
    JOIN places p ON p.id = sr.place_id
    WHERE sr.code=?
");
$stmt->bind_param("s", $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || intval($row["user_id"]) !== $userId) {
    redirect("dashboard.php");
}

$status      = expireIfNeeded($conn, $row);
$secondsLeft = max(0, strtotime($row["expires_at"]) - time());
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>QR แลกของรางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Kanit', sans-serif;
      min-height: 100vh;
      background:
        linear-gradient(rgba(10,20,50,.65), rgba(10,20,50,.65)),
        url("assets/images/lapikadbg.png") center/cover no-repeat fixed;
      display: flex; align-items: center; justify-content: center; padding: 20px;
    }

    .rc-wrap {
      width: 100%; max-width: 390px; background: #fff; border-radius: 28px;
      padding: 28px 22px 22px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }

    .rc-close {
      position: absolute; top: 16px; right: 16px; width: 30px; height: 30px;
      background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center;
      text-decoration: none; color: #64748b; font-size: 16px; font-weight: 700;
    }

    .rc-title { font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; margin-bottom: 4px; }
    .rc-sub   { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 18px; font-weight: 400; }

    .rc-icon-wrap { text-align: center; margin-bottom: 14px; }
    .rc-icon {
      width: 72px; height: 72px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
    }
    .rc-icon.success { background: #22c55e; }
    .rc-icon.fail    { background: #ef4444; }
    .rc-icon svg { width: 36px; height: 36px; fill: #fff; }

    .qr-box { text-align: center; margin-bottom: 14px; }
    .qr-box img { width: 220px; height: 220px; border-radius: 16px; border: 1px solid #f1f5f9; }

    .code-text {
      display: block; text-align: center; font-size: 20px; font-weight: 700;
      letter-spacing: 2px; color: #0f172a; background: #f8fafc; border-radius: 12px;
      padding: 10px; margin-bottom: 14px; font-family: monospace;
    }

    .countdown {
      text-align: center; font-size: 14px; color: #2563eb; font-weight: 600; margin-bottom: 18px;
    }
    .countdown span { font-size: 22px; }

    .rc-btn-primary {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 15px; background: #2563eb; color: #fff;
      border: none; border-radius: 999px; font-family: 'Kanit',sans-serif; font-size: 15px; font-weight: 600;
      cursor: pointer; text-decoration: none; margin-bottom: 10px;
    }
    .rc-btn-ghost {
      display: block; width: 100%; padding: 14px; background: #fff; color: #374151;
      border: 1.5px solid #e2e8f0; border-radius: 999px; font-family: 'Kanit',sans-serif;
      font-size: 15px; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center;
    }
  </style>
</head>
<body>
  <div class="rc-wrap">
    <a href="dashboard.php" class="rc-close">✕</a>

    <div id="state-pending" style="<?= $status !== 'pending' ? 'display:none' : '' ?>">
      <h1 class="rc-title">สแกนที่ร้านเพื่อยืนยัน</h1>
      <p class="rc-sub"><?= e($row["reward_name"]) ?> · ร้าน <?= e($row["place_name"]) ?></p>

      <div class="qr-box">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=260x260&data=<?= urlencode("https://xn--12c2b2a1a6ddp1n.com/shop_redeem_confirm.php?code=" . $row["code"]) ?>" alt="QR Code">
      </div>

      <code class="code-text"><?= e($row["code"]) ?></code>

      <div class="countdown">
        เหลือเวลา <span id="timer"><?= gmdate("i:s", $secondsLeft) ?></span>
      </div>

      <form method="post" action="redeem_cancel.php" style="margin-bottom:10px">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="code" value="<?= e($row["code"]) ?>">
        <button type="submit" class="rc-btn-ghost" style="width:100%;font-family:inherit"
                onclick="return confirm('ยกเลิกการแลกและคืนแต้ม?')">ยกเลิก</button>
      </form>

      <a href="dashboard.php" class="rc-btn-ghost">กลับหน้าหลัก</a>
    </div>

    <div id="state-completed" style="<?= $status !== 'completed' ? 'display:none' : '' ?>">
      <div class="rc-icon-wrap">
        <div class="rc-icon success">
          <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">แลกสำเร็จ!</h1>
      <p class="rc-sub">หักแต้มร้านเรียบร้อยแล้ว</p>
      <a href="place.php?id=<?= $row["place_id"] ?>" class="rc-btn-primary">กลับหน้าร้าน</a>
      <a href="dashboard.php" class="rc-btn-ghost">กลับหน้าหลัก</a>
    </div>

    <div id="state-expired" style="<?= !in_array($status, ['expired','cancelled']) ? 'display:none' : '' ?>">
      <div class="rc-icon-wrap">
        <div class="rc-icon fail">
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </div>
      </div>
      <h1 class="rc-title"><?= $status === 'cancelled' ? 'ยกเลิกแล้ว' : 'หมดเวลา' ?></h1>
      <p class="rc-sub">แต้มร้านถูกคืนให้แล้ว ลองแลกใหม่ได้เลย</p>
      <a href="shop_redeem.php?place_id=<?= $row["place_id"] ?>" class="rc-btn-primary">ลองแลกใหม่</a>
      <a href="dashboard.php" class="rc-btn-ghost">กลับหน้าหลัก</a>
    </div>
  </div>

  <?php if ($status === "pending"): ?>
  <script>
  let secondsLeft = <?= $secondsLeft ?>;
  const timerEl = document.getElementById('timer');

  function tick() {
    if (secondsLeft <= 0) return;
    secondsLeft--;
    const m = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
    const s = String(secondsLeft % 60).padStart(2, '0');
    timerEl.textContent = m + ':' + s;
  }
  setInterval(tick, 1000);

  function showState(state) {
    document.getElementById('state-pending').style.display = state === 'pending' ? 'block' : 'none';
    document.getElementById('state-completed').style.display = state === 'completed' ? 'block' : 'none';
    document.getElementById('state-expired').style.display = (state === 'expired' || state === 'cancelled' || state === 'not_found') ? 'block' : 'none';
  }

  async function poll() {
    try {
      const res = await fetch('redeem_status.php?code=<?= urlencode($row["code"]) ?>');
      const data = await res.json();
      if (data.status !== 'pending') {
        showState(data.status);
        clearInterval(pollTimer);
      }
    } catch (e) { /* ignore transient network errors, keep polling */ }
  }
  const pollTimer = setInterval(poll, 2500);
  </script>
  <?php endif; ?>
</body>
</html>
