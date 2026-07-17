<?php
require_once "_admin.php";

$lat = (isset($_GET["lat"]) && $_GET["lat"] !== "") ? floatval($_GET["lat"]) : null;
$lng = (isset($_GET["lng"]) && $_GET["lng"] !== "") ? floatval($_GET["lng"]) : null;
$radius = intval($_GET["radius"] ?? 300);
if (!in_array($radius, [300, 500, 1000, 2000], true)) $radius = 300;
$hasCoords = $lat !== null && $lng !== null;

$pendingShopRequests = $conn->query("
    SELECT id, shop_name, lat, lng, created_at
    FROM shop_requests
    WHERE status='pending' AND lat IS NOT NULL AND lng IS NOT NULL
    ORDER BY created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$nearby = [];
$categoryCounts = [];
if ($hasCoords) {
    $places = $conn->query("SELECT id, name, category, lat, lng FROM places WHERE lat IS NOT NULL AND lng IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
    foreach ($places as $p) {
        $dist = haversineMeters($lat, $lng, (float)$p["lat"], (float)$p["lng"]);
        if ($dist <= $radius) {
            $cat = ($p["category"] !== null && $p["category"] !== "") ? $p["category"] : "อื่นๆ";
            $nearby[] = ["name" => $p["name"], "category" => $cat, "distance" => $dist];
            $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
        }
    }
    usort($nearby, fn($a, $b) => $a["distance"] <=> $b["distance"]);
    arsort($categoryCounts);
}

function fmtDistance(float $m): string {
    return $m >= 1000 ? number_format($m / 1000, 2) . " กม." : number_format($m, 0) . " ม.";
}

$pdfHref = $hasCoords ? "admin_report_location_pdf.php?lat=" . urlencode($lat) . "&lng=" . urlencode($lng) . "&radius=" . $radius : "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>วิเคราะห์ทำเลก่อนเปิดร้าน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px;flex-wrap:wrap; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }

    .pg-card { background:#fff;border-radius:20px;padding:16px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:14px; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:10px 16px;border:none;border-radius:999px;text-decoration:none;
      font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn[disabled], a.adm-btn.disabled { opacity:.4;pointer-events:none; }

    .form-label { display:block;font-size:11.5px;color:#64748b;margin-bottom:4px;font-weight:600; }
    .adm-row { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
    .pg-card input[type=number], .pg-card select {
      width:100%;border:1.5px solid #e2e8f0;border-radius:10px;padding:10px 12px;font-family:inherit;font-size:13px;box-sizing:border-box;
    }
    .geo-btn {
      display:flex;align-items:center;gap:8px;background:#eff6ff;color:#2563eb;border:none;border-radius:10px;
      padding:10px 14px;font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;margin-top:10px;
    }
    #geo-status { font-size:11.5px;color:#94a3b8;margin-left:6px; }

    .radius-group { display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px; }
    .radius-opt { display:flex;align-items:center;gap:6px;font-size:13px;background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 10px;cursor:pointer; }
    .radius-opt input { margin:0; }

    .sec-head { display:flex;align-items:center;justify-content:space-between;margin:20px 0 10px; }
    .sec-head h2 { font-size:15px;font-weight:700;color:#0f172a;margin:0; }

    .cat-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px; }
    @media (min-width:480px) { .cat-grid { grid-template-columns:repeat(4,1fr); } }
    .cat-tile { background:#fff;border-radius:16px;padding:14px 6px;text-align:center;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .cat-tile .num { font-size:19px;font-weight:700;color:#2563eb;line-height:1.1; }
    .cat-tile .label { font-size:11px;color:#64748b;margin-top:4px;line-height:1.3;overflow-wrap:anywhere; }
    .cat-total { text-align:right;font-size:12px;color:#2563eb;font-weight:600;margin:-6px 0 12px; }

    table.rpt-table { width:100%;border-collapse:collapse; }
    table.rpt-table th, table.rpt-table td { padding:10px 8px;font-size:12.5px;text-align:center;border-bottom:1px solid #f1f5f9; }
    table.rpt-table th { color:#64748b;font-weight:600;font-size:11.5px; }
    table.rpt-table td:first-child, table.rpt-table th:first-child { text-align:left; }
    table.rpt-table tbody tr:last-child td { border-bottom:none; }

    .empty-note { text-align:center;padding:20px;color:#94a3b8;font-size:13px; }
    .show-more-btn {
      display:block;width:100%;background:none;border:none;color:#2563eb;font-family:inherit;
      font-size:13px;font-weight:600;cursor:pointer;padding:12px 0;
    }
    .note-box { background:#eff6ff;color:#1d4ed8;font-size:12px;border-radius:12px;padding:10px 14px;margin-top:10px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="admin.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>วิเคราะห์ทำเลก่อนเปิดร้าน</h1>
      <a href="<?= e($pdfHref) ?>" class="adm-btn primary <?= $hasCoords ? '' : 'disabled' ?>">ดาวน์โหลด PDF</a>
    </div>

    <form method="get" class="pg-card">
      <label class="form-label">พิกัดที่เลือก</label>
      <div class="adm-row">
        <input type="number" step="any" name="lat" id="lat-input" placeholder="ละติจูด (Latitude)" value="<?= $lat !== null ? e($lat) : '' ?>">
        <input type="number" step="any" name="lng" id="lng-input" placeholder="ลองจิจูด (Longitude)" value="<?= $lng !== null ? e($lng) : '' ?>">
      </div>
      <button type="button" class="geo-btn" onclick="useCurrentLocation()">
        <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        ตำแหน่งปัจจุบันของคุณ
      </button>
      <span id="geo-status"></span>

      <?php if (!empty($pendingShopRequests)): ?>
        <label class="form-label" style="margin-top:14px">หรือเลือกจากคำขอร้านค้าที่รอตรวจสอบ</label>
        <select onchange="fillFromRequest(this)">
          <option value="">-- เลือกคำขอร้านค้า --</option>
          <?php foreach ($pendingShopRequests as $sr): ?>
            <option value="<?= $sr['id'] ?>" data-lat="<?= e($sr['lat']) ?>" data-lng="<?= e($sr['lng']) ?>">
              <?= e($sr['shop_name']) ?> (<?= date('j M Y', strtotime($sr['created_at'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <label class="form-label" style="margin-top:14px">เลือกรัศมี</label>
      <div class="radius-group">
        <label class="radius-opt"><input type="radio" name="radius" value="300" <?= $radius === 300 ? 'checked' : '' ?>> 300 เมตร</label>
        <label class="radius-opt"><input type="radio" name="radius" value="500" <?= $radius === 500 ? 'checked' : '' ?>> 500 เมตร</label>
        <label class="radius-opt"><input type="radio" name="radius" value="1000" <?= $radius === 1000 ? 'checked' : '' ?>> 1 กิโลเมตร</label>
        <label class="radius-opt"><input type="radio" name="radius" value="2000" <?= $radius === 2000 ? 'checked' : '' ?>> 2 กิโลเมตร</label>
      </div>

      <button type="submit" class="adm-btn primary" style="width:100%;margin-top:14px">วิเคราะห์ทำเล</button>
    </form>

    <?php if (!$hasCoords): ?>
      <div class="empty-note">กรุณาระบุพิกัดเพื่อเริ่มวิเคราะห์</div>
    <?php else: ?>
      <div class="sec-head"><h2>สรุปตามประเภท (ในรัศมี <?= $radius >= 1000 ? number_format($radius / 1000, 0) . ' กม.' : $radius . ' ม.' ?>)</h2></div>
      <?php if (empty($categoryCounts)): ?>
        <div class="empty-note">ไม่พบสถานที่ในรัศมีนี้</div>
      <?php else: ?>
        <div class="cat-grid">
          <?php foreach ($categoryCounts as $cat => $cnt): ?>
            <div class="cat-tile">
              <div class="num"><?= $cnt ?></div>
              <div class="label"><?= e($cat) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="cat-total">รวมทั้งหมด <?= count($nearby) ?> แห่ง</div>
      <?php endif; ?>

      <div class="sec-head"><h2>สถานที่ใกล้เคียง (รัศมี <?= $radius >= 1000 ? number_format($radius / 1000, 0) . ' กม.' : $radius . ' ม.' ?>)</h2></div>
      <div class="pg-card" style="padding:8px 18px">
        <table class="rpt-table">
          <thead><tr><th>ชื่อร้าน</th><th>ประเภท</th><th>ระยะทาง</th></tr></thead>
          <tbody>
            <?php if (empty($nearby)): ?>
              <tr><td colspan="3" class="empty-note">ไม่พบสถานที่ในรัศมีนี้</td></tr>
            <?php else: foreach ($nearby as $i => $n): ?>
              <tr <?= $i >= 8 ? 'class="extra-row" style="display:none"' : '' ?>>
                <td><?= e($n['name']) ?></td>
                <td><?= e($n['category']) ?></td>
                <td><?= fmtDistance($n['distance']) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <?php if (count($nearby) > 8): ?>
          <button type="button" class="show-more-btn" id="show-more-btn" onclick="showMoreNearby()">ดูเพิ่มเติม ▾</button>
        <?php endif; ?>
        <div class="note-box">หมายเหตุ: ระยะทางคำนวณแบบเส้นตรง (Haversine) อาจคลาดเคลื่อนจากเส้นทางจริง</div>
      </div>
    <?php endif; ?>

  </main>
  <script>
  function useCurrentLocation() {
    const status = document.getElementById('geo-status');
    if (!navigator.geolocation) {
      status.textContent = 'อุปกรณ์นี้ไม่รองรับการระบุพิกัด';
      return;
    }
    status.textContent = 'กำลังค้นหาพิกัด...';
    navigator.geolocation.getCurrentPosition(
      pos => {
        document.getElementById('lat-input').value = pos.coords.latitude.toFixed(7);
        document.getElementById('lng-input').value = pos.coords.longitude.toFixed(7);
        status.textContent = 'ระบุพิกัดสำเร็จ';
      },
      () => { status.textContent = 'ไม่สามารถระบุพิกัดได้ กรุณากรอกเอง'; },
      { timeout: 8000 }
    );
  }

  function fillFromRequest(select) {
    const opt = select.options[select.selectedIndex];
    if (!opt.dataset.lat || !opt.dataset.lng) return;
    document.getElementById('lat-input').value = opt.dataset.lat;
    document.getElementById('lng-input').value = opt.dataset.lng;
  }

  function showMoreNearby() {
    document.querySelectorAll('.extra-row').forEach(row => row.style.display = '');
    document.getElementById('show-more-btn').style.display = 'none';
  }
  </script>
</body>
</html>
