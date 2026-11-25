<?php
require_once "_shop.php";
require_once "_shop_points.php";

$placeId = intval($shopPlace["id"]);

// Check-in points: popularity score and rank (last SHOP_RANK_DAYS days),
// customers today, and the latest check-ins / redemptions.
$rank = getShopRank($conn, $placeId);
$todayCount = 0;
$recent = [];
try {
    $today = shopToday();
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM shop_point_txns WHERE place_id=? AND kind='checkin' AND checkin_date=?");
    $stmt->bind_param("is", $placeId, $today);
    $stmt->execute();
    $todayCount = intval($stmt->get_result()->fetch_assoc()["c"]);

    $stmt = $conn->prepare("
        SELECT t.kind, t.points, t.note, t.created_at, u.name, u.avatar_url
        FROM shop_point_txns t JOIN users u ON u.id = t.user_id
        WHERE t.place_id=? ORDER BY t.id DESC LIMIT 15
    ");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    // sql/add_shop_checkins.sql not run yet
}

$shopName = $_SESSION["name"] ?? "ร้านค้า";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ร้านค้าของฉัน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .adm-hero {
      border-radius:20px;overflow:hidden;
      background:
        linear-gradient(rgba(15,30,60,.35),rgba(15,30,60,.55)),
        <?= $shopPlace["image_url"] ? 'url("' . e($shopPlace["image_url"]) . '") center/cover no-repeat' : 'url("assets/images/lapikadbg.png") center/cover no-repeat' ?>;
      padding:26px 20px 46px;
    }
    .adm-hero h1 { font-size:21px;font-weight:700;color:#fff;margin:0 0 4px; }
    .adm-hero p  { font-size:13px;color:rgba(255,255,255,.85);margin:0;font-weight:300; }

    .adm-stat-card {
      background:#fff;border-radius:20px;box-shadow:0 8px 24px rgba(15,23,42,.1);
      padding:18px 8px;margin:-30px 4px 20px;position:relative;z-index:2;
      display:grid;grid-template-columns:repeat(3,1fr);gap:4px;
    }
    .adm-stat { text-align:center; }
    .adm-stat .icon-circle {
      width:36px;height:36px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;margin:0 auto 8px;
    }
    .adm-stat .icon-circle svg { width:17px;height:17px; }
    .adm-stat .num   { font-size:19px;font-weight:700;color:#0f172a;line-height:1.1; }
    .adm-stat .label { font-size:10.5px;color:#64748b;margin-top:3px;line-height:1.3; }
    .adm-stat .unit  { font-size:10px;color:#94a3b8; }

    .adm-quick-title { font-size:15px;font-weight:700;color:#0f172a;margin:4px 0 12px; }
    .adm-quick-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:10px; }
    .adm-quick-tile {
      background:#fff;border-radius:16px;padding:18px 6px;text-align:center;
      text-decoration:none;box-shadow:0 2px 8px rgba(15,23,42,.05);
      border:none;font-family:inherit;cursor:pointer;
    }
    .adm-quick-tile .icon-circle {
      width:42px;height:42px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;margin:0 auto 8px;
    }
    .adm-quick-tile .icon-circle svg { width:19px;height:19px; }
    .adm-quick-tile span { font-size:12.5px;font-weight:600;color:#0f172a;display:block;line-height:1.3; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="adm-hero">
      <h1>สวัสดีครับ <?= e($shopPlace["name"]) ?>!</h1>
      <p>ลูกค้าสแกน QR ที่โต๊ะ ได้ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนน ร้านได้ <?= SHOP_CHECKIN_SHOP_POINTS ?> คะแนนความดัง · ยิ่งดังยิ่งขึ้นเป็นร้านแรกๆ</p>
    </div>

    <div class="adm-stat-card">
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fef3c7">
          <svg viewBox="0 0 24 24" style="fill:#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        </div>
        <div class="num"><?= $rank["rank"] ? "#" . $rank["rank"] : "–" ?></div>
        <div class="label">อันดับร้านดัง</div>
        <div class="unit">จาก <?= number_format($rank["total"]) ?> ร้าน</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#ef4444"><path d="M13.5.67s.74 2.65.74 4.8c0 2.06-1.35 3.73-3.41 3.73-2.07 0-3.63-1.67-3.63-3.73l.03-.36C5.21 7.51 4 10.62 4 14c0 4.42 3.58 8 8 8s8-3.58 8-8C20 8.61 17.41 3.8 13.5.67z"/></svg>
        </div>
        <div class="num"><?= number_format($rank["score"]) ?></div>
        <div class="label">คะแนนความดัง</div>
        <div class="unit"><?= SHOP_RANK_DAYS ?> วันล่าสุด</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="num"><?= number_format($todayCount) ?></div>
        <div class="label">เช็คอินวันนี้</div>
        <div class="unit">คน</div>
      </div>
    </div>

    <div class="adm-quick-title">เมนูจัดการร้านค้า</div>
    <div class="adm-quick-grid">
      <a class="adm-quick-tile" href="shop_qr.php">
        <div class="icon-circle" style="background:#e0e7ff">
          <svg viewBox="0 0 24 24" style="fill:#4f46e5"><path d="M3 3h8v8H3V3zm2 2v4h4V5H5zm8-2h8v8h-8V3zm2 2v4h4V5h-4zM3 13h8v8H3v-8zm2 2v4h4v-4H5zm11-2h2v2h-2v-2zm-3 0h2v3h-2v-3zm3 3h2v2h-2v-2zm2 2h3v3h-3v-3zm-5 1h3v2h-3v-2zm7-6h1v4h-1v-4z"/></svg>
        </div>
        <span>QR เช็คอิน<br>(พิมพ์ติดโต๊ะ)</span>
      </a>
      <a class="adm-quick-tile" href="shop_deduct_scan.php">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#ef4444"><path d="M4 4h4V2H2v6h2V4zm16 0v4h2V2h-6v2h4zM4 20v-4H2v6h6v-2H4zm16 0h-4v2h6v-6h-2v4zM2 11h20v2H2v-2z"/></svg>
        </div>
        <span>สแกนหักคะแนน<br>(แลกรางวัล)</span>
      </a>
      <a class="adm-quick-tile" href="shop_rewards.php">
        <div class="icon-circle" style="background:#fef3c7">
          <svg viewBox="0 0 24 24" style="fill:#d97706"><path d="M20 6h-2.18c.11-.31.18-.65.18-1a2.996 2.996 0 0 0-5.5-1.65l-.5.67-.5-.68C10.96 2.54 10.05 2 9 2 7.34 2 6 3.34 6 5c0 .35.07.69.18 1H4c-1.11 0-1.99.89-1.99 2L2 19c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V8c0-1.11-.89-2-2-2zm-5-2c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zM9 4c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm11 15H4v-2h16v2zm0-5H4V8h5.08L7 10.83 8.62 12 12 7.4l3.38 4.6L17 10.83 14.92 8H20v6z"/></svg>
        </div>
        <span>รางวัลของร้าน</span>
      </a>
      <a class="adm-quick-tile" href="shop_quests.php">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.6" fill="#2563eb"/></svg>
        </div>
        <span>จัดการภารกิจ</span>
      </a>
      <a class="adm-quick-tile" href="shop_location.php">
        <div class="icon-circle" style="background:#fce7f3">
          <svg viewBox="0 0 24 24" style="fill:#db2777"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <span>ที่ตั้งร้าน<br>(จังหวัด/อำเภอ)</span>
      </a>
      <a class="adm-quick-tile" href="admin_report_retention.php">
        <div class="icon-circle" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M4 4v6h6M20 20v-6h-6"/><path d="M4.5 15a8 8 0 0 0 13.9 3.4L20 20M20 9A8 8 0 0 0 6.1 5.6L4 6"/></svg>
        </div>
        <span>รายงานลูกค้ากลับมาซ้ำ</span>
      </a>
    </div>

    <div class="adm-quick-title" style="margin-top:20px">ความเคลื่อนไหวล่าสุด</div>
    <div style="background:#fff;border-radius:18px;padding:4px 16px;box-shadow:0 2px 8px rgba(15,23,42,.05);margin-bottom:24px">
      <?php if (!$recent): ?>
        <div style="text-align:center;color:#94a3b8;font-size:13.5px;padding:18px 0">ยังไม่มีลูกค้าเช็คอิน พิมพ์ QR ไปติดที่โต๊ะได้เลย</div>
      <?php endif; ?>
      <?php foreach ($recent as $t): $plus = intval($t["points"]) >= 0; ?>
        <div style="display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid #f1f5f9">
          <div style="flex:1;min-width:0">
            <strong style="display:block;font-size:14px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($t["name"]) ?></strong>
            <span style="font-size:12px;color:#94a3b8"><?= e(shopTxnLabel($t)) ?> · <?= e(thaiTimestampText(strtotime($t["created_at"]))) ?></span>
          </div>
          <b style="color:<?= $plus ? "#16a34a" : "#dc2626" ?>"><?= $plus ? "+" : "" ?><?= intval($t["points"]) ?></b>
        </div>
      <?php endforeach; ?>
    </div>

  </main>
</body>
</html>
