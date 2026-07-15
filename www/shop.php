<?php
require_once "_shop.php";

$placeId = intval($shopPlace["id"]);

$totalQuests  = $conn->query("SELECT COUNT(*) c FROM quests WHERE place_id=$placeId")->fetch_assoc()["c"];
$totalRewards = $conn->query("SELECT COUNT(*) c FROM rewards WHERE place_id=$placeId")->fetch_assoc()["c"];
$pointsGiven  = $conn->query("
    SELECT COALESCE(SUM(q.reward_points),0) c
    FROM user_quests uq JOIN quests q ON q.id = uq.quest_id
    WHERE q.place_id=$placeId
")->fetch_assoc()["c"];
$participants = $conn->query("
    SELECT COUNT(DISTINCT uq.user_id) c
    FROM user_quests uq JOIN quests q ON q.id = uq.quest_id
    WHERE q.place_id=$placeId
")->fetch_assoc()["c"];

$shopName = $_SESSION["name"] ?? "ร้านค้า";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
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
      display:grid;grid-template-columns:repeat(4,1fr);gap:4px;
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
      <p>จัดการร้านค้าและดูสถิติได้ที่นี่</p>
    </div>

    <div class="adm-stat-card">
      <div class="adm-stat">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.6" fill="#2563eb"/></svg>
        </div>
        <div class="num"><?= number_format($totalQuests) ?></div>
        <div class="label">ภารกิจทั้งหมด</div>
        <div class="unit">ภารกิจ</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#dc2626"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
        </div>
        <div class="num"><?= number_format($totalRewards) ?></div>
        <div class="label">ของรางวัลทั้งหมด</div>
        <div class="unit">รายการ</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fef9c3">
          <svg viewBox="0 0 24 24" style="fill:#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        </div>
        <div class="num"><?= number_format($pointsGiven) ?></div>
        <div class="label">คะแนนที่แจกไป</div>
        <div class="unit">คะแนน</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="num"><?= number_format($participants) ?></div>
        <div class="label">ผู้ร่วมภารกิจ</div>
        <div class="unit">คน</div>
      </div>
    </div>

    <div class="adm-quick-title">เมนูจัดการร้านค้า</div>
    <div class="adm-quick-grid">
      <a class="adm-quick-tile" href="shop_quests.php">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.6" fill="#2563eb"/></svg>
        </div>
        <span>จัดการภารกิจ</span>
      </a>
      <a class="adm-quick-tile" href="shop_rewards.php">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#dc2626"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
        </div>
        <span>จัดการของรางวัล</span>
      </a>
    </div>

  </main>
</body>
</html>
