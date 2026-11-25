<?php
// Shop owner: the check-in QR to print and put on tables. Customers scan it once
// a day to get points (shop_checkin.php). "สร้าง QR ใหม่" invalidates old prints.
require_once "_shop.php";
require_once "_shop_points.php";

$placeId = intval($shopPlace["id"]);
$msg = "";

try {
    if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_verify() && ($_POST["action"] ?? "") === "regenerate") {
        regenerateShopCheckinToken($conn, $placeId);
        $msg = "สร้าง QR ใหม่แล้ว QR เก่าที่พิมพ์ไว้ใช้ไม่ได้อีก อย่าลืมพิมพ์อันใหม่ไปติดแทน";
        $shopPlace = getShopPlace($conn, $placeId);
    }
    $token = ensureShopCheckinToken($conn, $shopPlace);
} catch (mysqli_sql_exception $e) {
    $token = null; // sql/add_shop_checkins.sql not run yet
}
$url = $token ? shopCheckinUrl($token) : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR เช็คอินร้าน · <?= e($shopPlace["name"]) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing:border-box; }
    body { font-family:'Kanit',sans-serif; margin:0; padding:20px; background:#f1f5f9; color:#0f172a; }
    .toolbar { max-width:520px; margin:0 auto 16px; display:flex; gap:10px; flex-wrap:wrap; }
    .toolbar a, .toolbar button { font-family:inherit; font-size:14px; font-weight:600; border:none; border-radius:12px; padding:11px 16px; text-decoration:none; cursor:pointer; }
    .back { background:#fff; color:#334155; }
    .print { background:#2563eb; color:#fff; flex:1; }
    .regen { background:#fff1f2; color:#e11d48; }
    .msg { max-width:520px; margin:0 auto 14px; padding:12px 14px; border-radius:14px; background:#fef3c7; color:#92400e; font-size:14px; }
    .poster { max-width:520px; margin:0 auto; background:#fff; border-radius:28px; padding:34px 26px 30px; text-align:center; box-shadow:0 10px 30px rgba(15,23,42,.08); }
    .brand { font-size:14px; font-weight:600; color:#2563eb; letter-spacing:.5px; }
    .poster h1 { font-size:28px; margin:8px 0 2px; }
    .poster h2 { font-size:19px; font-weight:500; color:#475569; margin:0 0 18px; }
    .poster img { width:320px; max-width:100%; }
    .big { font-size:24px; font-weight:700; color:#b45309; margin-top:12px; }
    .small { font-size:14px; color:#64748b; margin-top:6px; line-height:1.6; }
    @media print {
      body { background:#fff; padding:0; }
      .toolbar, .msg { display:none; }
      .poster { box-shadow:none; border:3px solid #0f172a; }
      @page { size:A5; margin:10mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a class="back" href="shop.php">กลับ</a>
    <button class="print" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
    <?php if ($url): ?>
    <form method="post" onsubmit="return confirm('สร้าง QR ใหม่? QR เก่าที่พิมพ์ติดไว้จะใช้ไม่ได้ทันที')">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="action" value="regenerate">
      <button class="regen" type="submit">สร้าง QR ใหม่</button>
    </form>
    <?php endif; ?>
  </div>

  <?php if ($msg): ?><div class="msg"><?= e($msg) ?></div><?php endif; ?>

  <div class="poster">
    <?php if (!$url): ?>
      <p>ระบบเช็คอินร้านค้ายังไม่พร้อมใช้งาน (ผู้ดูแลระบบต้องรัน sql/add_shop_checkins.sql ก่อน)</p>
    <?php else: ?>
      <div class="brand">ล่าพิกัด.com</div>
      <h1><?= e($shopPlace["name"]) ?></h1>
      <h2>สแกนเช็คอิน สะสมแต้ม</h2>
      <img src="<?= e(qrImageUrl($url, 500)) ?>" alt="QR เช็คอิน">
      <div class="big">รับ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนน</div>
      <div class="small">สแกนด้วยกล้องมือถือหรือแอปล่าพิกัด · วันละ 1 ครั้ง<br>สะสมครบแลกรางวัลของร้านได้เลย</div>
    <?php endif; ?>
  </div>
</body>
</html>
