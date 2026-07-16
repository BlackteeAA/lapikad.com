<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

$stmt_p = $conn->prepare("
    SELECT p.*,
           COUNT(DISTINCT q.id) AS quest_count,
           COUNT(DISTINCT CASE WHEN uq.id IS NOT NULL THEN q.id END) AS done_count
    FROM places p
    LEFT JOIN quests q  ON q.place_id = p.id
    LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ?
        AND (p.owner_user_id IS NULL OR uq.completed_date = CURDATE())
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY p.id
");
$stmt_p->bind_param("i", $userId);
$stmt_p->execute();
$placeRows = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สถานที่ | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family: 'Kanit', sans-serif; background: #f1f5f9 !important; background-image: none !important; }

    .places-header { margin-bottom: 18px; }
    .places-header h1 { font-size: 26px; font-weight: 700; color: #0f172a; margin: 0 0 4px; }
    .places-header p  { font-size: 14px; color: #64748b; margin: 0; }

    .gps-status {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 500;
      margin-bottom: 16px;
    }

    .gps-status.loading { background: #eff6ff; color: #2563eb; }
    .gps-status.ok      { background: #f0fdf4; color: #16a34a; }
    .gps-status.error   { background: #fff7ed; color: #ea580c; }
    .gps-status.nocoord { background: #f8fafc; color: #64748b; }

    .gps-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: currentColor;
      flex-shrink: 0;
    }

    .place-list { display: grid; gap: 12px; }

    .place-card {
      background: #fff;
      border-radius: 20px;
      padding: 18px;
      box-shadow: 0 2px 10px rgba(15,23,42,.06);
      display: flex;
      gap: 14px;
      align-items: flex-start;
      text-decoration: none;
      color: inherit;
      transition: box-shadow .2s;
      position: relative;
    }

    .place-card:hover { box-shadow: 0 6px 20px rgba(15,23,42,.12); }

    .place-card.locked {
      opacity: .55;
      pointer-events: none;
    }

    .place-icon {
      width: 70px;
      height: 70px;
      border-radius: 16px;
      background: linear-gradient(135deg, #dbeafe, #eff6ff);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
    }

    .place-icon img { width:100%;height:100%;object-fit:cover; }
    .place-icon svg { width: 28px; height: 28px; fill: #2563eb; }

    .cat-badge {
      display: inline-block;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 9px;
      border-radius: 99px;
      margin-bottom: 4px;
      background: #f1f5f9;
      color: #475569;
    }

    .cat-วัด\/ศาสนา, .cat-ชุมชน, .cat-พิพิธภัณฑ์ { background:#dcfce7; color:#15803d; }
    .cat-ธรรมชาติ, .cat-แหล่งท่องเที่ยว        { background:#dcfce7; color:#15803d; }
    .cat-ร้านค้า\/ร้านอาหาร                         { background:#fff7ed; color:#c2410c; }
    .cat-อื่นๆ                                    { background:#f1f5f9; color:#475569; }

    .place-body { flex: 1; min-width: 0; }

    .place-body h3 {
      font-size: 16px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 4px;
    }

    .place-location {
      font-size: 12px;
      color: #64748b;
      margin: 0 0 8px;
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .place-location svg { width: 12px; height: 12px; fill: #94a3b8; flex-shrink: 0; }

    .place-progress {
      height: 6px;
      background: #e2e8f0;
      border-radius: 99px;
      overflow: hidden;
      margin-bottom: 6px;
    }

    .place-progress-bar {
      height: 100%;
      background: linear-gradient(90deg, #2563eb, #22c55e);
      border-radius: 99px;
      transition: width .4s;
    }

    .place-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 12px;
      color: #64748b;
    }

    .dist-badge {
      position: absolute;
      top: 14px;
      right: 14px;
      background: #f0fdf4;
      color: #16a34a;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 99px;
    }

    .dist-badge.far  { background: #fff1f2; color: #e11d48; }
    .dist-badge.none { background: #f8fafc; color: #94a3b8; }

    .out-of-range-msg {
      text-align: center;
      padding: 20px;
      background: #fff7ed;
      border-radius: 16px;
      font-size: 14px;
      color: #92400e;
    }

    #no-places-msg {
      display: none;
      text-align: center;
      padding: 32px 16px;
      color: #94a3b8;
      font-size: 14px;
      background: #fff;
      border-radius: 20px;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="places-header">
      <h1>สถานที่ภารกิจ</h1>
      <p>เลือกสถานที่ที่คุณอยู่ในรัศมี 20 กม.</p>
    </div>

    <div class="gps-status loading" id="gps-status">
      <span class="gps-dot"></span>
      <span>กำลังระบุตำแหน่ง GPS...</span>
    </div>

    <div class="place-list" id="place-list">
      <?php foreach ($placeRows as $p):
        $pct = $p["quest_count"] > 0 ? round(($p["done_count"] / $p["quest_count"]) * 100) : 0;
        $locParts = array_filter([$p["district"] ? "อ." . $p["district"] : null, $p["province"] ? "จ." . $p["province"] : null, $p["location_text"]]);
        $locText = implode(" ", $locParts) ?: "ไม่ระบุพื้นที่";
        $hasGps = $p["lat"] && $p["lng"];
      ?>
        <a href="place.php?id=<?= $p["id"] ?>" class="place-card"
           data-id="<?= $p["id"] ?>"
           data-lat="<?= $p["lat"] ?: '' ?>"
           data-lng="<?= $p["lng"] ?: '' ?>"
           data-hasgps="<?= $hasGps ? '1' : '0' ?>">

          <div class="place-icon">
            <?php if ($p["image_url"]): ?>
              <img src="<?= e($p["image_url"]) ?>" alt="<?= e($p["name"]) ?>">
            <?php else: ?>
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            <?php endif; ?>
          </div>

          <div class="place-body">
            <?php if ($p["category"]): ?>
              <span class="cat-badge cat-<?= e($p["category"]) ?>"><?= e($p["category"]) ?></span>
            <?php endif; ?>
            <h3><?= e($p["name"]) ?></h3>
            <p class="place-location">
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
              <?= e($locText) ?>
            </p>
            <div class="place-progress">
              <div class="place-progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="place-meta">
              <span>ภารกิจ <?= $p["done_count"] ?>/<?= $p["quest_count"] ?> รายการ</span>
              <span><?= $pct ?>%</span>
            </div>
          </div>

          <span class="dist-badge none" data-dist>
            <?= $hasGps ? '...' : 'ไม่ทราบตำแหน่ง' ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>

    <div id="no-places-msg">
      ไม่พบสถานที่ในรัศมี 20 กม.<br>
      <small style="color:#94a3b8">ลองเปลี่ยนสถานที่หรือตรวจสอบ GPS</small>
    </div>

  </main>

  <script>
  const MAX_KM = 20;

  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 +
              Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function setStatus(type, msg) {
    const el = document.getElementById('gps-status');
    el.className = 'gps-status ' + type;
    el.innerHTML = '<span class="gps-dot"></span><span>' + msg + '</span>';
  }

  function applyGps(userLat, userLng) {
    const cards = document.querySelectorAll('.place-card');
    let visible = 0;

    cards.forEach(card => {
      const hasGps = card.dataset.hasgps === '1';
      const badge = card.querySelector('[data-dist]');
      if (!badge) return;

      if (!hasGps) {
        badge.className = 'dist-badge none';
        badge.textContent = 'ไม่ทราบตำแหน่ง';
        card.classList.remove('locked');
        visible++;
        return;
      }

      const dist = haversine(userLat, userLng, parseFloat(card.dataset.lat), parseFloat(card.dataset.lng));
      const km = dist.toFixed(1);

      if (dist <= MAX_KM) {
        badge.className = 'dist-badge';
        badge.textContent = km + ' กม.';
        card.classList.remove('locked');
        visible++;
      } else {
        badge.className = 'dist-badge far';
        badge.textContent = km + ' กม.';
        card.classList.add('locked');
      }
    });

    document.getElementById('no-places-msg').style.display = visible === 0 ? 'block' : 'none';
    setStatus('ok', 'ระบุตำแหน่งสำเร็จ · แสดงสถานที่ในรัศมี ' + MAX_KM + ' กม.');
  }

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => applyGps(pos.coords.latitude, pos.coords.longitude),
      err => {
        setStatus('error', 'ไม่สามารถระบุตำแหน่งได้ — แสดงสถานที่ทั้งหมด');
        document.querySelectorAll('.place-card').forEach(c => c.classList.remove('locked'));
        document.querySelectorAll('[data-dist]').forEach(b => {
          if (b.textContent === '...') { b.className = 'dist-badge none'; b.textContent = 'ไม่ทราบตำแหน่ง'; }
        });
      },
      { timeout: 8000, maximumAge: 60000 }
    );
  } else {
    setStatus('nocoord', 'เบราว์เซอร์ไม่รองรับ GPS — แสดงสถานที่ทั้งหมด');
    document.querySelectorAll('.place-card').forEach(c => c.classList.remove('locked'));
  }
  </script>
</body>
</html>
