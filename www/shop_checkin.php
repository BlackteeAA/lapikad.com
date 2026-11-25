<?php
// Customer scans the QR on a partner shop's table (printed from shop_qr.php).
// Once per shop per day: the customer gets points at that shop, the shop gets
// popularity points. Opened from the app's scanner (scan_route.php) or straight
// from the phone camera.
require_once "_db.php";

$token   = strtoupper(trim($_GET["s"] ?? ""));
$token   = preg_match('/^[0-9A-F]{20}$/', $token) ? $token : "";
$selfUrl = "shop_checkin.php?s=" . $token;

// Not logged in: remember the QR so login.php brings the customer straight back.
if (!isset($_SESSION["user_id"])) tryRememberLogin($conn);
if (!isset($_SESSION["user_id"])) {
    if ($token !== "") $_SESSION["after_login"] = $selfUrl;
    redirect("login.php");
}

require_once "_shop_points.php";

$userId = intval($_SESSION["user_id"]);
$state  = "bad"; // good | info | bad
$title  = "QR ไม่ถูกต้อง";
$message = "QR นี้ไม่ใช่ QR เช็คอินของร้านค้าที่ร่วมกับล่าพิกัด หรือร้านเปลี่ยน QR ใหม่แล้ว";
$gained = 0;

$shop = null;
if ($token !== "") {
    try {
        $shop = getShopByCheckinToken($conn, $token);
    } catch (mysqli_sql_exception $e) {
        $shop = null; // sql/add_shop_checkins.sql not run yet
        $message = "ระบบเช็คอินร้านค้ายังไม่พร้อมใช้งาน";
    }
}

if ($shop) {
    $r = shopCheckin($conn, $userId, $shop);
    if ($r === "ok") {
        $state   = "good";
        $title   = "เช็คอินสำเร็จ";
        $message = "ขอบคุณที่อุดหนุน " . $shop["name"];
        $gained  = SHOP_CHECKIN_CUSTOMER_POINTS;
    } elseif ($r === "already") {
        $state   = "info";
        $title   = "วันนี้เช็คอินแล้ว";
        $message = "เช็คอินร้านนี้ได้วันละ 1 ครั้ง พรุ่งนี้มาใหม่นะ";
    } elseif ($r === "own_shop") {
        $state   = "info";
        $title   = "นี่คือร้านของคุณ";
        $message = "QR นี้ให้ลูกค้าสแกน เจ้าของร้านสแกนเองไม่ได้รับคะแนน";
    } elseif ($r === "inactive") {
        $title   = "ร้านปิดใช้งานอยู่";
        $message = "ร้านนี้ยังไม่เปิดให้เช็คอินในตอนนี้";
    } else {
        $title   = "เกิดข้อผิดพลาด";
        $message = "บันทึกไม่สำเร็จ กรุณาสแกนใหม่อีกครั้ง";
    }
}

$balance = $shop ? getShopBalance($conn, $userId, intval($shop["id"])) : 0;
$rewards = $shop ? getShopRewards($conn, intval($shop["id"])) : [];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
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
      <?php if ($shop): ?>
        <div class="sp-shop-banner" style='<?= shopImageStyle($shop) ?>'>
          <span><?= e($shop["name"]) ?></span>
        </div>
      <?php endif; ?>

      <section class="sp-card sp-result">
        <div class="sp-mark <?= $state ?>"><?= $state === "bad" ? "!" : "✓" ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>
        <?php if ($gained > 0): ?>
          <div class="sp-gain">+<?= $gained ?> คะแนน</div>
        <?php endif; ?>
      </section>

      <?php if ($shop && $state !== "bad"): ?>
        <section class="sp-card sp-balance">
          <span>คะแนนของคุณที่ร้านนี้</span>
          <b><?= number_format($balance) ?></b>
          <small>ใช้แลกรางวัลได้เฉพาะที่ <?= e($shop["name"]) ?></small>
        </section>

        <div class="sp-section">รางวัลของร้าน</div>
        <section class="sp-card">
          <?php if (!$rewards): ?>
            <div class="sp-empty">ร้านยังไม่ได้ตั้งรางวัล สะสมคะแนนไว้ก่อนได้เลย</div>
          <?php endif; ?>
          <?php foreach ($rewards as $rw): $enough = $balance >= intval($rw["cost_points"]); ?>
            <div class="sp-reward <?= $enough ? "ready" : "" ?>">
              <?= rewardThumbHtml($rw, 72) ?>
              <div>
                <strong><?= e($rw["name"]) ?></strong>
                <?php if (!empty($rw["description"])): ?><span><?= e($rw["description"]) ?></span><?php endif; ?>
              </div>
              <div class="sp-cost">
                <b><?= number_format($rw["cost_points"]) ?></b>
                <small><?= $enough ? "แลกได้แล้ว" : "ขาดอีก " . number_format(intval($rw["cost_points"]) - $balance) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if ($rewards): ?>
            <div class="sp-hint">จะแลกรางวัล บอกพนักงานแล้วเปิด "QR ของฉัน" ให้ร้านสแกนหักคะแนน</div>
          <?php endif; ?>
        </section>
      <?php endif; ?>

      <a class="sp-btn" href="my_points.php">แต้มร้านค้าของฉัน / QR ของฉัน</a>
      <a class="sp-btn ghost" href="dashboard.php">กลับหน้าหลัก</a>
    </div>
  </main>
</body>
</html>
