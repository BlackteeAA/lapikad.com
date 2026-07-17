<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$points = intval(($stmt->get_result()->fetch_assoc() ?: ["points" => 0])["points"]);

$totalRow = $conn->query("SELECT COUNT(*) AS total FROM quests q JOIN places p ON p.id = q.place_id WHERE p.is_active = 1")->fetch_assoc();
$totalQuests = intval($totalRow["total"] ?? 0);

$doneStmt = $conn->prepare("
    SELECT COUNT(DISTINCT q.id) AS done
    FROM quests q
    JOIN places p ON p.id = q.place_id
    JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = ?
        AND (p.owner_user_id IS NULL OR uq.completed_date = CURDATE())
    WHERE p.is_active = 1
");
$doneStmt->bind_param("i", $userId);
$doneStmt->execute();
$doneQuests = intval($doneStmt->get_result()->fetch_assoc()["done"] ?? 0);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>AI แนะนำภารกิจถัดไป | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .aq-head {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 14px;
    }
    .aq-head-icon {
      width: 44px; height: 44px; flex-shrink: 0;
      border-radius: 14px;
      background: #fee2e2;
      display: flex; align-items: center; justify-content: center;
    }
    .aq-head-icon svg { width: 22px; height: 22px; fill: #ef4444; }
    .aq-head h1 { font-size: 17px; font-weight: 700; color: #0f172a; margin: 0 0 2px; }
    .aq-head p { font-size: 12px; color: #64748b; margin: 0; font-weight: 300; line-height: 1.4; }

    /* ── Stats card ── */
    .aq-stats {
      background: linear-gradient(135deg, #1d4ed8, #2563eb);
      border-radius: 20px;
      padding: 16px 18px;
      margin-bottom: 16px;
      color: #fff;
      box-shadow: 0 8px 22px rgba(37,99,235,.3);
    }
    .aq-stats-row {
      display: flex;
      margin-bottom: 14px;
    }
    .aq-stats-col { flex: 1; }
    .aq-stats-col + .aq-stats-col { border-left: 1px solid rgba(255,255,255,.25); padding-left: 16px; margin-left: 16px; }
    .aq-stats-lbl { display: flex; align-items: center; gap: 6px; font-size: 12px; opacity: .85; margin-bottom: 6px; }
    .aq-stats-lbl svg { width: 15px; height: 15px; }
    .aq-stats-val { font-size: 20px; font-weight: 700; }

    .aq-loc-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      padding-top: 12px;
      border-top: 1px solid rgba(255,255,255,.2);
    }
    .aq-loc-text { font-size: 11.5px; opacity: .8; }
    .aq-loc-text strong { display: block; font-size: 13px; font-weight: 600; opacity: 1; margin-top: 2px; }
    .aq-loc-btn {
      display: flex; align-items: center; gap: 5px;
      background: rgba(255,255,255,.18);
      border: none;
      color: #fff;
      font-family: 'Kanit', sans-serif;
      font-size: 11.5px;
      font-weight: 600;
      padding: 8px 12px;
      border-radius: 999px;
      cursor: pointer;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .aq-loc-btn svg { width: 13px; height: 13px; fill: #fff; }

    .aq-section-title { font-size: 15px; font-weight: 700; color: #0f172a; margin: 0 0 12px; }

    .aq-empty {
      text-align: center;
      padding: 30px 16px;
      color: #94a3b8;
      font-size: 13px;
      background: #fff;
      border-radius: 18px;
    }

    /* ── Top pick card ── */
    .aq-top-card {
      background: #fff;
      border-radius: 22px;
      padding: 16px;
      margin-bottom: 20px;
      box-shadow: 0 4px 16px rgba(15,23,42,.08);
    }
    .aq-top-head { display: flex; gap: 12px; margin-bottom: 12px; }
    .aq-top-thumb {
      position: relative;
      width: 76px; height: 76px; flex-shrink: 0;
      border-radius: 16px;
      background: linear-gradient(135deg, #dbeafe, #eff6ff);
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .aq-top-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .aq-top-thumb svg.placeholder { width: 30px; height: 30px; fill: #2563eb; }
    .aq-rank-badge {
      position: absolute;
      top: -6px; left: -6px;
      width: 22px; height: 22px;
      border-radius: 50%;
      background: #2563eb;
      color: #fff;
      font-size: 11px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid #fff;
    }
    .aq-top-info { flex: 1; min-width: 0; }
    .aq-top-name-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; }
    .aq-top-name-row strong { font-size: 15px; font-weight: 700; color: #0f172a; line-height: 1.3; }
    .aq-top-name-row svg.star { width: 20px; height: 20px; fill: #fbbf24; flex-shrink: 0; }
    .aq-top-dist { display: flex; align-items: center; gap: 4px; font-size: 12px; color: #64748b; margin: 4px 0; }
    .aq-top-dist svg { width: 13px; height: 13px; fill: #94a3b8; }
    .aq-top-desc { font-size: 12px; color: #64748b; line-height: 1.4; margin: 4px 0 0; }

    .aq-reward-row {
      display: flex; align-items: center; gap: 6px;
      font-size: 13px; color: #64748b; font-weight: 500;
      margin-bottom: 12px;
    }
    .aq-reward-row strong { color: #16a34a; font-size: 14px; }

    .aq-nav-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 14px;
      font-family: 'Kanit', sans-serif;
      font-size: 14.5px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
      margin-bottom: 14px;
    }
    .aq-nav-btn svg { width: 17px; height: 17px; fill: #fff; }

    .aq-route-label { font-size: 12.5px; font-weight: 600; color: #0f172a; margin-bottom: 8px; }
    .aq-route-map {
      width: 100%; height: 150px;
      border-radius: 14px;
      overflow: hidden;
      background: #e2e8f0;
      margin-bottom: 8px;
    }
    .aq-route-meta {
      display: flex; align-items: center; gap: 6px;
      font-size: 12px; color: #64748b;
    }
    .aq-route-meta svg { width: 14px; height: 14px; fill: #64748b; }

    /* ── Compact list cards (rank 2+) ── */
    .aq-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px; }
    .aq-list-card {
      display: flex;
      align-items: center;
      gap: 10px;
      background: #fff;
      border-radius: 16px;
      padding: 10px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
    }
    .aq-list-thumb {
      position: relative;
      width: 52px; height: 52px; flex-shrink: 0;
      border-radius: 12px;
      background: linear-gradient(135deg, #dbeafe, #eff6ff);
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .aq-list-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .aq-list-thumb svg.placeholder { width: 22px; height: 22px; fill: #2563eb; }
    .aq-list-thumb .aq-rank-badge { width: 18px; height: 18px; font-size: 10px; }
    .aq-list-info { flex: 1; min-width: 0; }
    .aq-list-info strong { display: block; font-size: 13.5px; font-weight: 700; color: #0f172a; }
    .aq-list-meta { display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
    .aq-list-meta .dist { font-size: 11px; color: #64748b; }
    .aq-list-meta .pts { font-size: 11px; color: #16a34a; font-weight: 600; }
    .aq-go-btn {
      flex-shrink: 0;
      background: #eff6ff;
      color: #2563eb;
      border: none;
      border-radius: 999px;
      padding: 9px 16px;
      font-family: 'Kanit', sans-serif;
      font-size: 12.5px;
      font-weight: 600;
      text-decoration: none;
      cursor: pointer;
    }

    .cat-badge {
      display: inline-block;
      font-size: 10.5px; font-weight: 600;
      padding: 2px 9px; border-radius: 99px;
      margin-bottom: 4px;
      background: #f1f5f9; color: #475569;
    }
    .cat-วัด\/ศาสนา, .cat-ชุมชน, .cat-พิพิธภัณฑ์ { background:#dcfce7; color:#15803d; }
    .cat-ธรรมชาติ, .cat-แหล่งท่องเที่ยว        { background:#dcfce7; color:#15803d; }
    .cat-ร้านค้า\/ร้านอาหาร                         { background:#fff7ed; color:#c2410c; }
    .cat-อื่นๆ                                    { background:#f1f5f9; color:#475569; }

    .aq-more-btn {
      display: block;
      width: 100%;
      text-align: center;
      background: #fff;
      color: #2563eb;
      border: 1.5px solid #e2e8f0;
      border-radius: 999px;
      padding: 13px;
      font-size: 13.5px;
      font-weight: 600;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="aq-head">
      <div class="aq-head-icon">
        <svg viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16zm0-14a6 6 0 1 0 0 12 6 6 0 0 0 0-12zm0 10a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>
      </div>
      <div>
        <h1>AI แนะนำภารกิจถัดไป</h1>
        <p>ภารกิจที่เหมาะกับคุณ ณ ตอนนี้ อัปเดตตามตำแหน่งและความคืบหน้าของคุณ</p>
      </div>
    </div>

    <div class="aq-stats">
      <div class="aq-stats-row">
        <div class="aq-stats-col">
          <div class="aq-stats-lbl">
            <svg viewBox="0 0 24 24" fill="#fbbf24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
            คะแนนรวมของคุณ
          </div>
          <div class="aq-stats-val" id="aq-points"><?= number_format($points) ?> คะแนน</div>
        </div>
        <div class="aq-stats-col">
          <div class="aq-stats-lbl">
            <svg viewBox="0 0 24 24" fill="#fff"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
            ภารกิจที่ทำแล้ว
          </div>
          <div class="aq-stats-val" id="aq-done"><?= $doneQuests ?>/<?= $totalQuests ?> ภารกิจ</div>
        </div>
      </div>
      <div class="aq-loc-row">
        <div class="aq-loc-text">
          ตำแหน่งปัจจุบัน
          <strong id="aq-loc-text">กำลังระบุตำแหน่ง...</strong>
        </div>
        <button type="button" class="aq-loc-btn" id="aq-update-loc">
          <svg viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-8.94 3A8.001 8.001 0 0 1 11 3.06V1h2v2.06A8.001 8.001 0 0 1 20.94 11H23v2h-2.06A8.001 8.001 0 0 1 13 20.94V23h-2v-2.06A8.001 8.001 0 0 1 3.06 13H1v-2h2.06zM12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12z"/></svg>
          อัปเดตตำแหน่ง
        </button>
      </div>
    </div>

    <h2 class="aq-section-title">แนะนำให้ไปที่นี่ถัดไป</h2>
    <div id="aq-results">
      <div class="aq-empty" id="aq-loading">กำลังวิเคราะห์ตำแหน่งและภารกิจที่เหมาะกับคุณ...</div>
    </div>

    <a href="places.php" class="aq-more-btn">ดูภารกิจทั้งหมดใกล้คุณ →</a>
  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  (function () {
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;
    const results = document.getElementById('aq-results');
    const locText = document.getElementById('aq-loc-text');
    const updateBtn = document.getElementById('aq-update-loc');
    const pointsEl = document.getElementById('aq-points');
    const doneEl = document.getElementById('aq-done');

    function catClass(cat) { return cat ? 'cat-' + cat : ''; }

    function esc(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    function placeholderSvg() {
      return '<svg class="placeholder" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>';
    }

    function mapsDirUrl(lat, lng) {
      return `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
    }

    function renderTopCard(q, rank) {
      const thumb = q.image_url ? `<img src="${esc(q.image_url)}" alt="${esc(q.place_name)}">` : placeholderSvg();
      const distText = q.distance_km !== null ? q.distance_km + ' กม. จากคุณ' : 'ไม่ทราบระยะทาง';
      const hasCoords = q.lat !== null && q.lng !== null;
      const hasUserLoc = window.__aqUserLat !== undefined && window.__aqUserLng !== undefined;
      const showRouteMap = hasCoords && hasUserLoc;

      const el = document.createElement('div');
      el.className = 'aq-top-card';
      el.innerHTML = `
        <div class="aq-top-head">
          <div class="aq-top-thumb">${thumb}<span class="aq-rank-badge">${rank}</span></div>
          <div class="aq-top-info">
            <div class="aq-top-name-row">
              <strong>${esc(q.place_name)}</strong>
              <svg class="star" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
            </div>
            ${q.category ? `<span class="cat-badge ${catClass(q.category)}">${esc(q.category)}</span>` : ''}
            <div class="aq-top-dist">
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
              ${distText}
            </div>
            ${q.description ? `<p class="aq-top-desc">${esc(q.description)}</p>` : ''}
          </div>
        </div>
        <div class="aq-reward-row">รางวัล <strong>+${q.reward_points} คะแนน</strong></div>
        ${hasCoords ? `<a href="${mapsDirUrl(q.lat, q.lng)}" target="_blank" rel="noopener" class="aq-nav-btn">
          <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          นำทางไปที่นี่
        </a>` : ''}
        ${showRouteMap ? `
        <div class="aq-route-label">เส้นทางแนะนำ</div>
        <div class="aq-route-map" id="aq-route-map"></div>
        <div class="aq-route-meta" id="aq-route-meta">
          <svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-8l-2.08-5.99zM6.5 16a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm11 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM5 11l1.5-4.5h11L19 11H5z"/></svg>
          กำลังคำนวณระยะทาง...
        </div>` : ''}
      `;
      return el;
    }

    function renderListCard(q, rank) {
      const thumb = q.image_url ? `<img src="${esc(q.image_url)}" alt="${esc(q.place_name)}">` : placeholderSvg();
      const distText = q.distance_km !== null ? q.distance_km + ' กม.' : '-- กม.';
      const hasCoords = q.lat !== null && q.lng !== null;

      const el = document.createElement('div');
      el.className = 'aq-list-card';
      el.innerHTML = `
        <div class="aq-list-thumb">${thumb}<span class="aq-rank-badge">${rank}</span></div>
        <div class="aq-list-info">
          <strong>${esc(q.place_name)}</strong>
          <div class="aq-list-meta">
            ${q.category ? `<span class="cat-badge ${catClass(q.category)}">${esc(q.category)}</span>` : ''}
            <span class="dist">${distText}</span>
          </div>
          <div class="aq-list-meta"><span class="pts">รางวัล +${q.reward_points} คะแนน</span></div>
        </div>
        ${hasCoords ? `<a href="${mapsDirUrl(q.lat, q.lng)}" target="_blank" rel="noopener" class="aq-go-btn">นำทาง</a>` : ''}
      `;
      return el;
    }

    function drawRoute(userLat, userLng, destLat, destLng) {
      const mapEl = document.getElementById('aq-route-map');
      const metaEl = document.getElementById('aq-route-meta');
      if (!mapEl) return;

      const map = L.map(mapEl, { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false });
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

      const userIcon = L.divIcon({ className: '', html: '<div style="width:14px;height:14px;background:#2563eb;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>', iconSize: [14,14], iconAnchor: [7,7] });
      const destIcon = L.divIcon({ className: '', html: '<div style="width:26px;height:26px;transform:translate(-50%,-100%)"><svg viewBox="0 0 24 24" style="fill:#ef4444"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg></div>', iconSize: [26,26], iconAnchor: [0,0] });

      L.marker([userLat, userLng], { icon: userIcon }).addTo(map);
      L.marker([destLat, destLng], { icon: destIcon }).addTo(map);

      function fitAndFallback() {
        map.fitBounds([[userLat, userLng], [destLat, destLng]], { padding: [24, 24] });
        L.polyline([[userLat, userLng], [destLat, destLng]], { color: '#2563eb', weight: 3, dashArray: '6,6' }).addTo(map);
      }

      fetch(`https://router.project-osrm.org/route/v1/driving/${userLng},${userLat};${destLng},${destLat}?overview=full&geometries=geojson`)
        .then(r => r.json())
        .then(data => {
          const route = data.routes && data.routes[0];
          if (!route) throw new Error('no route');
          const latlngs = route.geometry.coordinates.map(c => [c[1], c[0]]);
          L.polyline(latlngs, { color: '#2563eb', weight: 4 }).addTo(map);
          map.fitBounds(latlngs, { padding: [24, 24] });

          const km = (route.distance / 1000).toFixed(1);
          const mins = Math.max(1, Math.round(route.duration / 60));
          metaEl.innerHTML = `<svg viewBox="0 0 24 24"><path d="M18.92 6.01C18.72 5.42 18.16 5 17.5 5h-11c-.66 0-1.21.42-1.42 1.01L3 12v8a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-1h12v1a1 1 0 0 0 1 1h1a1 1 0 0 0 1-1v-8l-2.08-5.99zM6.5 16a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm11 0a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM5 11l1.5-4.5h11L19 11H5z"/></svg> ${km} กม. · ประมาณ ${mins} นาที`;
        })
        .catch(() => {
          fitAndFallback();
          metaEl.textContent = '';
        });
    }

    function renderResults(data) {
      results.innerHTML = '';

      if (!data.quests || !data.quests.length) {
        const empty = document.createElement('div');
        empty.className = 'aq-empty';
        empty.textContent = data.reply || 'ตอนนี้คุณทำภารกิจครบทุกที่ที่มีแล้วครับ';
        results.appendChild(empty);
        return;
      }

      const [top, ...rest] = data.quests;
      results.appendChild(renderTopCard(top, 1));

      if (rest.length) {
        const list = document.createElement('div');
        list.className = 'aq-list';
        rest.forEach((q, i) => list.appendChild(renderListCard(q, i + 2)));
        results.appendChild(list);
      }

      if (top.lat !== null && top.lng !== null && window.__aqUserLat !== undefined) {
        drawRoute(window.__aqUserLat, window.__aqUserLng, top.lat, top.lng);
      }
    }

    function fetchRecommend(lat, lng) {
      const params = { csrf_token: CSRF_TOKEN };
      if (lat !== undefined && lng !== undefined) {
        params.lat = lat;
        params.lng = lng;
      }
      fetch('quest_recommend.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(params),
      })
        .then(r => r.json())
        .then(data => {
          if (typeof data.points === 'number') pointsEl.textContent = data.points.toLocaleString('th-TH') + ' คะแนน';
          if (typeof data.done_quests === 'number') doneEl.textContent = data.done_quests + '/' + data.total_quests + ' ภารกิจ';
          renderResults(data);
        })
        .catch(() => {
          results.innerHTML = '<div class="aq-empty">ไม่สามารถโหลดคำแนะนำได้ในตอนนี้ กรุณาลองใหม่อีกครั้ง</div>';
        });
    }

    function locateAndFetch() {
      locText.textContent = 'กำลังระบุตำแหน่ง...';
      if (!navigator.geolocation) {
        locText.textContent = 'ไม่รองรับ GPS';
        fetchRecommend();
        return;
      }
      navigator.geolocation.getCurrentPosition(pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        window.__aqUserLat = lat;
        window.__aqUserLng = lng;

        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=th`)
          .then(r => r.json())
          .then(data => {
            const addr = data.address || {};
            const district = addr.county || addr.city_district || addr.suburb || addr.town || '';
            const province = addr.state || '';
            const loc = [district ? 'ใกล้ ' + district : '', province].filter(Boolean).join(' ');
            locText.textContent = loc || 'ระบุตำแหน่งสำเร็จ';
          })
          .catch(() => { locText.textContent = 'ระบุตำแหน่งสำเร็จ'; });

        fetchRecommend(lat, lng);
      }, () => {
        locText.textContent = 'ไม่สามารถระบุตำแหน่งได้';
        fetchRecommend();
      }, { timeout: 10000, maximumAge: 60000 });
    }

    updateBtn.addEventListener('click', locateAndFetch);
    locateAndFetch();
  })();
  </script>
</body>
</html>
