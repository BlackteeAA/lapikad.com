<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT name, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM user_quests WHERE user_id=?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$completed = $stmt2->get_result()->fetch_assoc()["total"];

$stmt3 = $conn->prepare("SELECT COUNT(DISTINCT q.place_id) AS total FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=?");
$stmt3->bind_param("i", $userId);
$stmt3->execute();
$visitedPlaces = $stmt3->get_result()->fetch_assoc()["total"];

$placesAll = $conn->query("
    SELECT p.*,
           COUNT(DISTINCT q.id)  AS quest_count,
           COUNT(DISTINCT uq.id) AS done_count
    FROM places p
    LEFT JOIN quests q ON q.place_id = p.id
    LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = $userId
    GROUP BY p.id ORDER BY p.id
")->fetch_all(MYSQLI_ASSOC);

$placesJson = json_encode(array_map(fn($p) => [
    'id'    => $p['id'],
    'name'  => $p['name'],
    'img'   => $p['image_url'] ?? '',
    'loc'   => trim(implode(' ', array_filter([
        $p['district'] ? 'อ.'.$p['district'] : null,
        $p['province'] ? 'จ.'.$p['province'] : null,
        $p['location_text']
    ]))),
    'lat'   => $p['lat'] !== null ? floatval($p['lat']) : null,
    'lng'   => $p['lng'] !== null ? floatval($p['lng']) : null,
    'done'  => intval($p['done_count']),
    'total' => intval($p['quest_count']),
], $placesAll));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>หน้าหลัก | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }
    .db-wrap { max-width:100%; background:#f1f5f9; }

    /* ── Hero ── */
    .db-hero {
      border-radius: 24px;
      overflow: hidden;
      margin-bottom: 14px;
      position: relative;
      background:
        linear-gradient(rgba(0,0,0,.25), rgba(0,0,0,.25)),
        url("assets/images/lapikadbg.png") center/cover no-repeat;
      padding: 22px 18px 22px;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 12px;
      min-height: 130px;
    }

    .db-hero-text { flex: 1; }
    .db-hero-text h1 {
      font-size: 22px; font-weight: 700; color: #fff; margin: 0 0 4px; line-height: 1.2;
    }
    .db-hero-text h1 span { color: #60a5fa; }
    .db-hero-text p {
      font-size: 13px; font-weight: 300; color: rgba(255,255,255,.85); margin: 0;
    }

    .db-weather {
      background: rgba(255,255,255,.15);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255,255,255,.25);
      border-radius: 16px;
      padding: 10px 12px;
      text-align: center;
      min-width: 100px;
      flex-shrink: 0;
    }
    .db-weather-loc {
      font-size: 12px; font-weight: 600; color: #fff; display: block; margin-bottom: 4px;
    }
    .db-weather-row {
      display: flex; align-items: center; gap: 6px; justify-content: center;
    }
    .db-weather-row svg { width:20px;height:20px; }
    .db-weather-temp {
      font-size: 15px; font-weight: 700; color: #fff;
    }
    .db-weather-desc {
      font-size: 11px; color: rgba(255,255,255,.75); display: block; margin-top: 2px;
    }

    /* ── GPS button ── */
    .db-gps-btn {
      background: linear-gradient(135deg, #1d4ed8, #2563eb);
      border-radius: 18px;
      padding: 16px 16px;
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 14px;
      cursor: pointer;
      border: none;
      width: 100%;
      text-align: left;
      font-family: inherit;
      color: #fff;
      box-shadow: 0 6px 20px rgba(37,99,235,.35);
    }

    .db-gps-icon {
      width: 48px; height: 48px;
      background: rgba(255,255,255,.18);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .db-gps-icon svg { width:26px;height:26px;fill:#fff; }

    .db-gps-text { flex: 1; }
    .db-gps-text strong { display:block; font-size:15px; font-weight:700; }
    .db-gps-text span   { display:block; font-size:12px; opacity:.8; font-weight:300; margin-top:2px; }

    .db-gps-action {
      background: #fff;
      color: #2563eb;
      border-radius: 999px;
      padding: 9px 16px;
      font-size: 13px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 5px;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .db-gps-action svg { width:14px;height:14px;fill:#2563eb; }

    /* ── Stats ── */
    .db-stats {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 8px;
      margin-bottom: 18px;
    }

    .db-stat {
      background: #fff;
      border-radius: 16px;
      padding: 12px 10px;
      text-align: center;
      box-shadow: 0 2px 8px rgba(15,23,42,.05);
      text-decoration: none;
    }

    .db-stat-icon {
      width: 36px; height: 36px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      margin: 0 auto 6px;
    }
    .db-stat-icon svg { width:20px;height:20px; }

    .db-stat-val { font-size:18px; font-weight:700; color:#0f172a; display:block; line-height:1; }
    .db-stat-lbl { font-size:10px; color:#64748b; display:block; margin-top:3px; line-height:1.2; }

    /* ── Nearby section ── */
    .db-nearby-head {
      display: flex;
      align-items: center;
      gap: 6px;
      margin-bottom: 12px;
    }
    .db-nearby-head h2 { font-size:17px;font-weight:700;color:#0f172a;margin:0; }
    .db-nearby-head svg { width:18px;height:18px;fill:#2563eb; }
    .db-nearby-head a {
      margin-left:auto; font-size:13px; color:#2563eb; font-weight:500; text-decoration:none;
    }

    #db-map {
      width: 100%;
      height: 200px;
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 2px 10px rgba(15,23,42,.08);
      isolation: isolate;
      position: relative;
      z-index: 0;
      margin-bottom: 12px;
    }

    .db-place-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .db-place-item {
      background: #fff;
      border-radius: 16px;
      padding: 12px 14px;
      display: flex;
      gap: 12px;
      align-items: center;
      text-decoration: none;
      color: inherit;
      box-shadow: 0 1px 6px rgba(15,23,42,.05);
    }

    .db-place-thumb {
      width: 52px; height: 52px;
      border-radius: 14px;
      background: #eff6ff;
      flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .db-place-thumb svg { width:26px;height:26px;fill:#2563eb; }
    .db-place-thumb img { width:100%;height:100%;object-fit:cover; }

    .db-place-info { flex:1; min-width:0; }
    .db-place-info strong {
      display:block; font-size:14px; font-weight:600; color:#0f172a;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .db-place-info span {
      font-size:12px; color:#64748b;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis; display:block;
      margin-top:2px;
    }

    .db-place-dist { text-align:right; flex-shrink:0; }
    .db-place-dist strong { display:block; font-size:14px; font-weight:700; color:#2563eb; }
    .db-place-dist small  { font-size:11px; color:#94a3b8; }
  </style>
</head>
<body>
  <main class="app db-wrap" style="max-width:100%;padding-bottom:90px">
    <?php include "includes/topbar.php"; ?>

    <!-- Hero -->
    <div class="db-hero">
      <div class="db-hero-text">
        <h1>สวัสดีครับ, <span><?= e($user["name"]) ?></span></h1>
        <p>ออกไปล่าพิกัด สะสมคะแนน<br>แลกรางวัลกันเถอะ!</p>
      </div>
      <div class="db-weather" id="db-weather">
        <span class="db-weather-loc" id="weather-loc">กำลังระบุ...</span>
        <div class="db-weather-row">
          <svg id="weather-icon" viewBox="0 0 24 24" fill="#fbbf24"><path d="M12 7a5 5 0 1 0 0 10A5 5 0 0 0 12 7zm0-5a1 1 0 0 1 1 1v1a1 1 0 0 1-2 0V3a1 1 0 0 1 1-1zm0 17a1 1 0 0 1 1 1v1a1 1 0 0 1-2 0v-1a1 1 0 0 1 1-1zm-7-9H3a1 1 0 0 1 0-2h2a1 1 0 0 1 0 2zm16 0h-2a1 1 0 0 1 0-2h2a1 1 0 0 1 0 2zM5.64 6.35a1 1 0 0 1 1.41-1.41l.71.7a1 1 0 1 1-1.41 1.42l-.71-.71zm11.31 11.31a1 1 0 0 1 1.41-1.41l.71.7a1 1 0 0 1-1.41 1.42l-.71-.71zM6.34 17.66a1 1 0 0 1-1.4 1.41l-.71-.7a1 1 0 0 1 1.41-1.42l.7.71zm11.32-11.32a1 1 0 0 1-1.41 1.41l-.7-.7a1 1 0 0 1 1.41-1.42l.7.71z"/></svg>
          <span class="db-weather-temp" id="weather-temp">--°C</span>
        </div>
        <span class="db-weather-desc" id="weather-desc">อากาศ</span>
      </div>
    </div>

    <!-- GPS check-in button -->
    <button class="db-gps-btn" onclick="scrollToNearby()">
      <div class="db-gps-icon">
        <svg viewBox="0 0 24 24"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8zm-8.94 3A8.001 8.001 0 0 1 11 3.06V1h2v2.06A8.001 8.001 0 0 1 20.94 11H23v2h-2.06A8.001 8.001 0 0 1 13 20.94V23h-2v-2.06A8.001 8.001 0 0 1 3.06 13H1v-2h2.06zM12 18a6 6 0 1 0 0-12 6 6 0 0 0 0 12z"/></svg>
      </div>
      <div class="db-gps-text">
        <strong>เช็คพิกัดใกล้ฉัน</strong>
        <span>ค้นหาสถานที่ท่องเที่ยวที่ใกล้ที่สุดจากตำแหน่งของคุณ</span>
      </div>
      <div class="db-gps-action">
        เช็คเลย
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </div>
    </button>

    <!-- Stats -->
    <div class="db-stats">
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#fef3c7">
          <svg viewBox="0 0 24 24" style="fill:#f59e0b"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        </div>
        <span class="db-stat-val"><?= number_format($user["points"]) ?></span>
        <span class="db-stat-lbl">คะแนน</span>
      </div>
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" style="fill:#16a34a"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
        </div>
        <span class="db-stat-val"><?= $completed ?></span>
        <span class="db-stat-lbl">ภารกิจ</span>
      </div>
      <div class="db-stat">
        <div class="db-stat-icon" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
        </div>
        <span class="db-stat-val"><?= $visitedPlaces ?></span>
        <span class="db-stat-lbl">สถานที่</span>
      </div>
      <a href="rewards.php" class="db-stat">
        <div class="db-stat-icon" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" style="fill:#2563eb"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
        </div>
        <span class="db-stat-val" style="font-size:13px;color:#2563eb">ดูรางวัล</span>
        <span class="db-stat-lbl">แลกรางวัล</span>
      </a>
    </div>

    <!-- Nearby places -->
    <div class="db-nearby-head" id="nearby-section">
      <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
      <h2>สถานที่ใกล้คุณ</h2>
      <a href="places.php">ดูทั้งหมด</a>
    </div>

    <div id="db-map"></div>
    <div class="db-place-list" id="db-place-list">
      <div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px">กำลังโหลด...</div>
    </div>

  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  const PLACES = <?= $placesJson ?>;
  const GPS_NEAR_KM = 2;

  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2-lat1)*Math.PI/180;
    const dLng = (lng2-lng1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function wmoDesc(code) {
    if (code === 0) return 'แดดจัด';
    if (code <= 3) return 'มีเมฆบ้าง';
    if (code <= 48) return 'หมอก';
    if (code <= 67) return 'ฝนตก';
    if (code <= 77) return 'หิมะ';
    if (code <= 82) return 'ฝนหนัก';
    return 'พายุฝน';
  }

  function scrollToNearby() {
    document.getElementById('nearby-section').scrollIntoView({ behavior: 'smooth' });
  }

  // Init map at Thailand center
  const map = L.map('db-map', { zoomControl: false, attributionControl: false })
               .setView([15.87, 100.99], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

  const userIcon = L.divIcon({
    className: '',
    html: '<div style="width:16px;height:16px;background:#2563eb;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>',
    iconSize: [16,16], iconAnchor: [8,8]
  });

  const placeIcon = L.divIcon({
    className: '',
    html: '<div style="width:12px;height:12px;background:#ef4444;border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.3)"></div>',
    iconSize: [12,12], iconAnchor: [6,6]
  });

  // Add place markers
  PLACES.forEach(p => {
    if (p.lat && p.lng) {
      L.marker([p.lat, p.lng], {icon: placeIcon})
       .addTo(map)
       .bindPopup(p.name);
    }
  });

  function renderPlaces(places, userLat, userLng) {
    const list = document.getElementById('db-place-list');
    if (!places.length) {
      list.innerHTML = '<div style="text-align:center;padding:16px;color:#94a3b8;font-size:13px">ไม่พบสถานที่ใกล้คุณ</div>';
      return;
    }
    list.innerHTML = places.slice(0, 4).map(p => {
      const dist = p.dist !== undefined ? p.dist.toFixed(1) + ' กม.' : '-- กม.';
      const near = p.dist !== undefined && p.dist <= GPS_NEAR_KM;
      return `
        <a href="place.php?id=${p.id}" class="db-place-item">
          <div class="db-place-thumb">
            ${p.img
              ? `<img src="${p.img}" alt="${p.name}" style="width:100%;height:100%;object-fit:cover;border-radius:14px">`
              : `<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>`
            }
          </div>
          <div class="db-place-info">
            <strong>${p.name}</strong>
            <span>${p.loc || 'ไม่ระบุพื้นที่'}</span>
          </div>
          <div class="db-place-dist">
            <strong style="color:${near?'#16a34a':'#2563eb'}">${dist}</strong>
            <small>${near ? 'ใกล้มาก' : ''}</small>
          </div>
        </a>`;
    }).join('');
  }

  // Get GPS
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;

      // Update map
      map.setView([lat, lng], 13);
      L.marker([lat, lng], {icon: userIcon}).addTo(map);
      L.circle([lat, lng], {radius: GPS_NEAR_KM * 1000, color:'#2563eb', fillOpacity:.08, weight:1}).addTo(map);

      // Calculate distances
      const withDist = PLACES.map(p => ({
        ...p,
        dist: (p.lat && p.lng) ? haversine(lat, lng, p.lat, p.lng) : Infinity
      })).sort((a,b) => a.dist - b.dist);

      renderPlaces(withDist, lat, lng);

      // Weather
      fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,weather_code`)
        .then(r => r.json())
        .then(data => {
          const temp = Math.round(data.current.temperature_2m);
          const code = data.current.weather_code;
          document.getElementById('weather-temp').textContent = temp + '°C';
          document.getElementById('weather-desc').textContent = wmoDesc(code);
        }).catch(() => {});

      // Reverse geocode
      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=th`)
        .then(r => r.json())
        .then(data => {
          const addr = data.address || {};
          const loc = addr.county || addr.city_district || addr.suburb || addr.town || addr.city || '';
          const prov = addr.state || '';
          document.getElementById('weather-loc').textContent = loc || prov || 'ตำแหน่งปัจจุบัน';
        }).catch(() => {
          document.getElementById('weather-loc').textContent = 'ตำแหน่งปัจจุบัน';
        });

    }, () => {
      renderPlaces(PLACES.filter(p => p.lat && p.lng));
      document.getElementById('weather-loc').textContent = 'ไม่ทราบตำแหน่ง';
    }, { timeout: 10000, maximumAge: 60000 });
  } else {
    renderPlaces(PLACES.filter(p => p.lat && p.lng));
  }
  </script>
</body>
</html>
