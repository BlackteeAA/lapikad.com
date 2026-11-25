<?php
// Customer's shop points ("แต้มร้านค้าของฉัน"): my personal QR for shops to scan
// when I redeem a reward, my balance at each shop, that shop's rewards and history.
require_once "_shop_points.php";

$userId = intval($_SESSION["user_id"]);

// Newest point change of mine; the page reloads when it changes (shop deducted, etc.).
$lastTxnId = 0;
$ready = true;
try {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(id),0) m FROM shop_point_txns WHERE user_id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $lastTxnId = intval($stmt->get_result()->fetch_assoc()["m"]);
} catch (mysqli_sql_exception $e) {
    $ready = false; // sql/add_shop_checkins.sql not run yet
}
if (isset($_GET["poll"])) {
    session_write_close();
    header("Content-Type: application/json");
    echo json_encode(["last" => $lastTxnId]);
    exit;
}

$stmt = $conn->prepare("SELECT name, avatar_url FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

$qrData = customerQrData($conn, $userId);
$shops  = [];
$txnsByShop = [];
if ($ready) try {
    // Every shop I have points at or ever checked in to, highest balance first.
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.image_url, p.location_text, COALESCE(usp.points, 0) AS balance,
               (SELECT MAX(t.created_at) FROM shop_point_txns t WHERE t.user_id = ? AND t.place_id = p.id) AS last_at
        FROM places p
        LEFT JOIN user_shop_points usp ON usp.place_id = p.id AND usp.user_id = ?
        WHERE p.owner_user_id IS NOT NULL
          AND (usp.id IS NOT NULL OR EXISTS (SELECT 1 FROM shop_point_txns t2 WHERE t2.user_id = ? AND t2.place_id = p.id))
        ORDER BY balance DESC, last_at DESC
    ");
    $stmt->bind_param("iii", $userId, $userId, $userId);
    $stmt->execute();
    $shops = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmt = $conn->prepare("
        SELECT place_id, kind, points, note, created_at
        FROM shop_point_txns WHERE user_id = ?
        ORDER BY id DESC LIMIT 200
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $t) {
        if (count($txnsByShop[$t["place_id"]] ?? []) < 10) $txnsByShop[$t["place_id"]][] = $t;
    }
} catch (mysqli_sql_exception $e) {
    $ready = false; // sql/add_shop_checkins.sql not run yet
}
$totalBalance = array_sum(array_map(fn($s) => intval($s["balance"]), $shops));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>แต้มร้านค้าของฉัน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/shop_points.css?v=<?= @filemtime(__DIR__ . '/css/shop_points.css') ?>">
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div class="sp-wrap">
      <section class="sp-card sp-myqr">
        <?php if ($qrData): ?>
          <img src="<?= e(qrImageUrl($qrData, 360)) ?>" alt="QR ของฉัน">
        <?php endif; ?>
        <strong><?= e($me["name"]) ?></strong>
        <span>แลกรางวัล: ให้ร้านสแกน QR นี้เพื่อหักคะแนน<br>ห้ามส่ง QR นี้ให้ผู้อื่น</span>
      </section>

      <?php if (!$ready): ?>
        <div class="sp-card"><div class="sp-empty">ระบบแต้มร้านค้ายังไม่พร้อมใช้งาน</div></div>
      <?php else: ?>
        <div class="sp-section">
          <span>แต้มแต่ละร้าน</span>
          <span style="font-size:12.5px;color:#64748b;font-weight:500">รวม <?= number_format($totalBalance) ?> คะแนน</span>
        </div>
        <section class="sp-card" style="padding-top:6px;padding-bottom:6px">
          <?php if (!$shops): ?>
            <div class="sp-empty">ยังไม่มีแต้ม<br>กินหรือซื้อของที่ร้านที่ร่วมกับเรา แล้วสแกน QR ที่โต๊ะเพื่อรับ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนน</div>
          <?php endif; ?>
          <?php foreach ($shops as $s): $rewards = getShopRewards($conn, intval($s["id"])); $bal = intval($s["balance"]); ?>
            <details class="sp-shoprow">
              <summary>
                <div class="sp-thumb" style='<?= shopImageStyle($s) ?>'></div>
                <div class="info">
                  <strong><?= e($s["name"]) ?></strong>
                  <span><?= $rewards ? count($rewards) . " รางวัล" : "ยังไม่มีรางวัล" ?><?= $s["last_at"] ? " · ล่าสุด " . e(thaiTimestampText(strtotime($s["last_at"]))) : "" ?></span>
                </div>
                <div class="pts"><?= number_format($bal) ?> <small>คะแนน</small></div>
              </summary>
              <div class="body">
                <?php foreach ($rewards as $rw): $enough = $bal >= intval($rw["cost_points"]); ?>
                  <div class="sp-reward <?= $enough ? "ready" : "" ?>">
                    <?= rewardThumbHtml($rw, 64) ?>
                    <div>
                      <strong><?= e($rw["name"]) ?></strong>
                      <?php if (!empty($rw["description"])): ?><span><?= e($rw["description"]) ?></span><?php endif; ?>
                    </div>
                    <div class="sp-cost">
                      <b><?= number_format($rw["cost_points"]) ?></b>
                      <small><?= $enough ? "แลกได้แล้ว" : "ขาดอีก " . number_format(intval($rw["cost_points"]) - $bal) ?></small>
                    </div>
                  </div>
                <?php endforeach; ?>
                <?php foreach ($txnsByShop[$s["id"]] ?? [] as $t): ?>
                  <div class="sp-txn">
                    <span>
                      <?= e(shopTxnLabel($t)) ?>
                      <small><?= e(thaiTimestampText(strtotime($t["created_at"]))) ?></small>
                    </span>
                    <b class="<?= intval($t["points"]) >= 0 ? "plus" : "minus" ?>"><?= intval($t["points"]) >= 0 ? "+" : "" ?><?= intval($t["points"]) ?></b>
                  </div>
                <?php endforeach; ?>
              </div>
            </details>
          <?php endforeach; ?>
        </section>
      <?php endif; ?>

      <a class="sp-btn ghost" href="scan_any.php">สแกน QR ที่ร้าน</a>
    </div>
  </main>
  <?php if ($ready): ?>
  <script>
  // Reload when a shop deducts points (or I check in elsewhere) while this is open.
  (function () {
    const last = <?= $lastTxnId ?>;
    setInterval(async () => {
      if (document.hidden) return;
      try {
        const r = await fetch('my_points.php?poll=1', { cache: 'no-store' });
        const d = await r.json();
        if (d.last !== last) location.reload();
      } catch (e) {}
    }, 4000);
  })();
  </script>
  <?php endif; ?>
</body>
</html>
