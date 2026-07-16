<?php
require_once "_auth.php";

$placeId = intval($_GET["id"] ?? 1);
$userId  = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT * FROM places WHERE id=?");
$stmt->bind_param("i", $placeId);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) redirect("places.php");

$dailyRefresh = isDailyRefreshQuest($place["owner_user_id"] !== null ? intval($place["owner_user_id"]) : null);
$dateFilter   = $dailyRefresh ? "AND uq.completed_date = CURDATE()" : "";

$stmt = $conn->prepare("
    SELECT q.*,
           CASE WHEN uq.id IS NULL THEN 0 ELSE 1 END AS done
    FROM quests q
    LEFT JOIN user_quests uq ON uq.quest_id=q.id AND uq.user_id=? $dateFilter
    WHERE q.place_id=?
    ORDER BY q.id
");
$stmt->bind_param("ii", $userId, $placeId);
$stmt->execute();
$questRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$total = count($questRows);
$done  = array_sum(array_column($questRows, "done"));
$pct   = $total > 0 ? round($done / $total * 100) : 0;

$locParts = array_filter([
    $place["district"] ? "อ." . $place["district"] : null,
    $place["province"] ? "จ." . $place["province"] : null,
    $place["location_text"]
]);
$locText = implode(" ", $locParts) ?: "ไม่ระบุพื้นที่";
$hasGps  = $place["lat"] !== null && $place["lng"] !== null;

$shopPoints  = 0;
$shopHasRewards = false;
if ($dailyRefresh) {
    $spStmt = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=?");
    $spStmt->bind_param("ii", $userId, $placeId);
    $spStmt->execute();
    $spRow = $spStmt->get_result()->fetch_assoc();
    $shopPoints = $spRow ? intval($spRow["points"]) : 0;

    $shopHasRewards = (bool) $conn->query("SELECT id FROM rewards WHERE place_id=$placeId LIMIT 1")->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($place["name"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9 !important; background-image:none !important; }

    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      color: #2563eb;
      font-size: 14px;
      font-weight: 500;
      text-decoration: none;
      margin-bottom: 14px;
    }

    .back-btn svg { width: 18px; height: 18px; fill: currentColor; }

    .place-hero {
      background: #fff;
      border-radius: 24px;
      padding: 20px;
      margin-bottom: 16px;
      box-shadow: 0 2px 10px rgba(15,23,42,.06);
    }

    .place-hero h1 { font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 6px; }

    .place-loc {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 13px;
      color: #64748b;
      margin-bottom: 14px;
    }

    .place-loc svg { width: 14px; height: 14px; fill: #94a3b8; }

    .gps-check {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-size: 12px;
      font-weight: 500;
      padding: 5px 10px;
      border-radius: 99px;
      margin-bottom: 14px;
    }

    .gps-check.ok    { background: #f0fdf4; color: #16a34a; }
    .gps-check.far   { background: #fff1f2; color: #e11d48; }
    .gps-check.wait  { background: #eff6ff; color: #2563eb; }

    .progress-bar {
      height: 8px;
      background: #e2e8f0;
      border-radius: 99px;
      overflow: hidden;
      margin-bottom: 6px;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #2563eb, #22c55e);
      border-radius: 99px;
    }

    .progress-meta { font-size: 13px; color: #64748b; }

    .shop-pts-chip {
      display: flex; align-items: center; justify-content: space-between;
      background: linear-gradient(135deg, #eff6ff, #f5f3ff);
      border-radius: 16px; padding: 12px 16px; margin-top: 12px;
      text-decoration: none;
    }
    .shop-pts-chip .lbl { font-size: 12px; color: #64748b; }
    .shop-pts-chip .val { font-size: 20px; font-weight: 700; color: #2563eb; }
    .shop-pts-chip .cta {
      font-size: 12.5px; font-weight: 600; color: #fff;
      background: #2563eb; padding: 8px 14px; border-radius: 999px;
    }

    .quest-section h2 {
      font-size: 17px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 14px;
    }

    .quest-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
    }

    .quest-icon-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      padding: 16px 8px;
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
      text-align: center;
      transition: box-shadow .2s, opacity .2s;
    }

    .quest-icon-card.done { background: linear-gradient(135deg, #f0fdf4, #dcfce7); }
    .quest-icon-card.locked { opacity: .45; pointer-events: none; }

    .qi-circle {
      width: 56px;
      height: 56px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .qi-circle.gray    { background: #e2e8f0; }
    .qi-circle.blue    { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
    .qi-circle.green   { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .qi-circle.purple  { background: linear-gradient(135deg, #8b5cf6, #6d28d9); }
    .qi-circle.orange  { background: linear-gradient(135deg, #f59e0b, #d97706); }

    .qi-circle svg { width: 26px; height: 26px; fill: #fff; }
    .qi-circle.gray svg { fill: #94a3b8; }

    .quest-icon-card span {
      font-size: 12px;
      font-weight: 600;
      color: #0f172a;
      line-height: 1.3;
    }

    .quest-icon-card.done span { color: #16a34a; }

    .quest-pts {
      font-size: 11px;
      color: #64748b;
      font-weight: 400;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="places.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="place-hero">
      <h1><?= e($place["name"]) ?></h1>

      <div class="place-loc">
        <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
        <?= e($locText) ?>
      </div>

      <?php if ($hasGps): ?>
        <div class="gps-check wait" id="gps-check">
          กำลังตรวจสอบ GPS...
        </div>
      <?php endif; ?>

      <div class="progress-bar">
        <div class="progress-fill" style="width:<?= $pct ?>%"></div>
      </div>
      <p class="progress-meta">สำเร็จแล้ว <?= $done ?>/<?= $total ?> ภารกิจ (<?= $pct ?>%)</p>
      <?php if ($dailyRefresh): ?>
        <p class="progress-meta" style="color:#2563eb">ภารกิจร้านนี้ทำซ้ำได้ทุกวัน</p>
      <?php endif; ?>

      <?php if ($dailyRefresh && $shopHasRewards): ?>
        <a href="shop_redeem.php?place_id=<?= $placeId ?>" class="shop-pts-chip">
          <div>
            <div class="lbl">แต้มร้านนี้ของคุณ</div>
            <div class="val">★ <?= number_format($shopPoints) ?></div>
          </div>
          <span class="cta">แลกของรางวัล</span>
        </a>
      <?php endif; ?>
    </div>

    <div class="quest-section">
      <h2>เลือกภารกิจ</h2>
      <div class="quest-grid" id="quest-grid">
        <?php
        $typeColors = ['checkin'=>'blue','qr'=>'green','quiz'=>'purple','photo'=>'orange'];
        $typeSvg = [
          'checkin' => '<path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/>',
          'qr'      => '<path d="M4 4h6v2H6v4H4V4zm10 0h6v6h-2V6h-4V4zM4 14h2v4h4v2H4v-6zm14 0h2v6h-6v-2h4v-4zM9 9h6v6H9V9z"/>',
          'quiz'    => '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 17h-2v-2h2v2zm2.07-7.75l-.9.92C13.45 12.9 13 13.5 13 15h-2v-.5c0-1.1.45-2.1 1.17-2.83l1.24-1.26c.37-.36.59-.86.59-1.41 0-1.1-.9-2-2-2s-2 .9-2 2H8c0-2.21 1.79-4 4-4s4 1.79 4 4c0 .88-.36 1.68-.93 2.25z"/>',
          'photo'   => '<path d="M12 15.2A3.2 3.2 0 1 1 12 8.8a3.2 3.2 0 0 1 0 6.4zM9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/>',
        ];
        foreach ($questRows as $q):
          $type  = $q["quest_type"] ?? "checkin";
          $color = $q["done"] ? ($typeColors[$type] ?? "blue") : "gray";
          $svg   = $typeSvg[$type] ?? $typeSvg["checkin"];
          $cardClass = "quest-icon-card" . ($q["done"] ? " done" : "");
        ?>
          <a href="quest.php?id=<?= $q["id"] ?>" class="<?= $cardClass ?>" data-quest="<?= $q["id"] ?>">
            <div class="qi-circle <?= $color ?>">
              <?php if ($q["done"]): ?>
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
              <?php else: ?>
                <svg viewBox="0 0 24 24"><?= $svg ?></svg>
              <?php endif; ?>
            </div>
            <span><?= e($q["title"]) ?></span>
            <span class="quest-pts">+<?= $q["reward_points"] ?> คะแนน</span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

  </main>

  <?php if ($hasGps): ?>
  <script>
  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2-lat1)*Math.PI/180;
    const dLng = (lng2-lng1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  const PLACE_LAT = <?= floatval($place["lat"]) ?>;
  const PLACE_LNG = <?= floatval($place["lng"]) ?>;

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      const dist = haversine(pos.coords.latitude, pos.coords.longitude, PLACE_LAT, PLACE_LNG);
      const el = document.getElementById('gps-check');
      if (dist <= 0.1) {
        el.className = 'gps-check ok';
        el.textContent = 'คุณอยู่ในรัศมี · ' + Math.round(dist * 1000) + ' ม.';
      } else {
        el.className = 'gps-check far';
        el.textContent = 'ต้องอยู่ในรัศมี 100 ม. (ห่าง ' + Math.round(dist * 1000) + ' ม.)';
        document.querySelectorAll('.quest-icon-card:not(.done)').forEach(c => c.classList.add('locked'));
      }
    }, () => {
      document.getElementById('gps-check').remove();
    }, { timeout: 8000, maximumAge: 60000 });
  }
  </script>
  <?php endif; ?>
</body>
</html>
