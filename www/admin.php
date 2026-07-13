<?php
require_once "_admin.php";

$totalPlaces      = $conn->query("SELECT COUNT(*) c FROM places")->fetch_assoc()["c"];
$totalUsers       = $conn->query("SELECT COUNT(*) c FROM users")->fetch_assoc()["c"];
$totalRewards     = $conn->query("SELECT COUNT(*) c FROM rewards")->fetch_assoc()["c"];
$totalPointsGiven = $conn->query("SELECT COALESCE(SUM(points),0) c FROM point_logs WHERE points > 0")->fetch_assoc()["c"];

$adminName = $_SESSION["name"] ?? "Admin";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แอดมิน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    /* Hero */
    .adm-hero {
      border-radius:20px;overflow:hidden;
      background:
        linear-gradient(rgba(15,30,60,.35),rgba(15,30,60,.55)),
        url("assets/images/lapikadbg.png") center/cover no-repeat;
      padding:26px 20px 46px;
    }
    .adm-hero h1 { font-size:21px;font-weight:700;color:#fff;margin:0 0 4px; }
    .adm-hero p  { font-size:13px;color:rgba(255,255,255,.85);margin:0;font-weight:300; }

    /* Floating stat card */
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

    /* Quick menu */
    .adm-quick-title { font-size:15px;font-weight:700;color:#0f172a;margin:4px 0 12px; }
    .adm-quick-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:10px; }
    .adm-quick-tile {
      background:#fff;border-radius:16px;padding:16px 6px;text-align:center;
      text-decoration:none;box-shadow:0 2px 8px rgba(15,23,42,.05);
      border:none;font-family:inherit;cursor:pointer;
    }
    .adm-quick-tile .icon-circle {
      width:42px;height:42px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;margin:0 auto 8px;
    }
    .adm-quick-tile .icon-circle svg { width:19px;height:19px; }
    .adm-quick-tile span { font-size:11.5px;font-weight:600;color:#0f172a;display:block;line-height:1.3; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <!-- Hero -->
    <div class="adm-hero">
      <h1>สวัสดี <?= e($adminName) ?>!</h1>
      <p>จัดการระบบและดูภาพรวมได้ที่นี่</p>
    </div>

    <!-- Stats -->
    <div class="adm-stat-card">
      <div class="adm-stat">
        <div class="icon-circle" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <div class="num"><?= number_format($totalPlaces) ?></div>
        <div class="label">สถานที่ทั้งหมด</div>
        <div class="unit">แห่ง</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="num"><?= number_format($totalUsers) ?></div>
        <div class="label">ผู้ใช้งานทั้งหมด</div>
        <div class="unit">คน</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fef9c3">
          <svg viewBox="0 0 24 24" style="fill:#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        </div>
        <div class="num"><?= number_format($totalPointsGiven) ?></div>
        <div class="label">คะแนนที่แจก</div>
        <div class="unit">คะแนน</div>
      </div>
      <div class="adm-stat">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#dc2626"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
        </div>
        <div class="num"><?= number_format($totalRewards) ?></div>
        <div class="label">รางวัลทั้งหมด</div>
        <div class="unit">รายการ</div>
      </div>
    </div>

    <!-- Quick menu -->
    <div class="adm-quick-title">เมนูลัดสำหรับแอดมิน</div>
    <div class="adm-quick-grid">
      <a class="adm-quick-tile" href="admin_places.php">
        <div class="icon-circle" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <span>จัดการสถานที่</span>
      </a>
      <a class="adm-quick-tile" href="admin_places_add.php">
        <div class="icon-circle" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M12 2C8.13 2 5 5.13 5 9c0 3.87 2.5 8.25 5 11.09V15h2v7.5c.34-.4.67-.8 1-1.23V15h2v3.68C21.5 15.71 19 11.5 19 9c0-3.87-3.13-7-7-7zM8 9a4 4 0 1 1 8 0 4 4 0 0 1-8 0z"/></svg>
        </div>
        <span>เพิ่มสถานที่</span>
      </a>
      <a class="adm-quick-tile" href="admin_quests.php">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" style="fill:#2563eb"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="3" height="3" rx="0.5"/><rect x="18" y="14" width="3" height="3" rx="0.5"/><rect x="14" y="18" width="3" height="3" rx="0.5"/><rect x="18" y="18" width="3" height="3" rx="0.5"/></svg>
        </div>
        <span>จัดการ QR Code</span>
      </a>
      <a class="adm-quick-tile" href="admin_rewards.php">
        <div class="icon-circle" style="background:#fee2e2">
          <svg viewBox="0 0 24 24" style="fill:#dc2626"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
        </div>
        <span>จัดการรางวัล</span>
      </a>
      <a class="adm-quick-tile" href="admin_users.php">
        <div class="icon-circle" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <span>จัดการผู้ใช้</span>
      </a>
      <a class="adm-quick-tile" href="admin_quests.php">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><circle cx="12" cy="12" r="8"/><circle cx="12" cy="12" r="4"/><circle cx="12" cy="12" r="0.6" fill="#2563eb"/></svg>
        </div>
        <span>กิจกรรม/ภารกิจ</span>
      </a>
    </div>

  </main>
</body>
</html>
