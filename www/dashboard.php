<?php
require_once "_auth.php";
require_once "_shop_points.php";

$userId = intval($_SESSION["user_id"]);
$role   = $_SESSION["role"] ?? "user";

$stmt = $conn->prepare("SELECT name, avatar_url, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Small "open a shop" ad: regular users who haven't applied (or were rejected).
$showShopPromo = false;
if ($role === "user") {
    $spStmt = $conn->prepare("SELECT status FROM shop_requests WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $spStmt->bind_param("i", $userId);
    $spStmt->execute();
    $spRow = $spStmt->get_result()->fetch_assoc();
    $showShopPromo = !$spRow || $spRow["status"] === "rejected";
}

// ── Provincial Top 3: popular shops (last SHOP_RANK_DAYS days) grouped by province ──
$allShops = getPopularShops($conn);
$topByProvince = ["" => array_slice($allShops, 0, 3)]; // "" = every province
foreach ($allShops as $s) {
    $prov = trim($s["province"] ?? "");
    if ($prov === "") continue;
    if (count($topByProvince[$prov] ?? []) < 3) $topByProvince[$prov][] = $s;
}
$provinces = array_keys($topByProvince);
array_shift($provinces);
sort($provinces);

// ── Nearby map / list (unchanged behaviour) ──
$placesAll = $conn->query("
    SELECT p.*,
           COUNT(DISTINCT q.id) AS quest_count,
           COUNT(DISTINCT CASE WHEN uq.id IS NOT NULL THEN q.id END) AS done_count
    FROM places p
    LEFT JOIN quests q ON q.place_id = p.id
    LEFT JOIN user_quests uq ON uq.quest_id = q.id AND uq.user_id = $userId
        AND (p.owner_user_id IS NULL OR uq.completed_date = CURDATE())
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

$currentEvents = getCurrentEvents($conn, $userId);

// Rank badge: a clear frosted circle with the number.
function rankBadge($rank, $size = 34) {
    $s = intval($size);
    return '<span class="rank-glass" style="width:' . $s . 'px;height:' . $s . 'px;font-size:' . round($s * 0.42) . 'px" aria-label="อันดับ ' . intval($rank) . '">' . intval($rank) . '</span>';
}
$locOf = fn($s) => trim(($s["district"] ? "อ." . $s["district"] . " " : "") . ($s["province"] ? "จ." . $s["province"] : "")) ?: "ไม่ระบุจังหวัด";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>หน้าหลัก | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    :root {
      --blue: #0755E8;
      --blue-ink: #0B2A5B;
      --green: #159B55;
      --sky: #EAF6FF;
      --white: #FFFFFF;
      --gold: #C9972B;
      --gold-soft: #F6E7C1;
      --ink: #0F1B2D;
      --muted: #64748B;
      --line: #E7EEF6;
      --shadow: 0 6px 24px rgba(11, 42, 91, .07);
      --shadow-lg: 0 14px 34px rgba(11, 42, 91, .12);
    }
    body {
      font-family: 'LINE Seed Sans TH', 'Kanit', sans-serif;
      background: linear-gradient(180deg, var(--sky) 0, #F7FBFF 420px, #F7FBFF 100%) !important;
      background-image: none;
      color: var(--ink);
    }
    .db { max-width: 100%; background: transparent; }
    .db section { margin-bottom: 22px; }
    .db-h2 { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 0 2px 12px; }
    .db-h2 h2 { font-size: 17px; font-weight: 600; color: var(--ink); margin: 0; }
    .db-h2 a, .db-h2 .sub { font-size: 12.5px; color: var(--blue); text-decoration: none; font-weight: 500; white-space: nowrap; }
    .db-h2 .sub { color: var(--muted); font-weight: 400; }
    .ic { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; }

    /* ── Greeting header ── */
    .db-head { display: flex; align-items: center; gap: 12px; margin: 2px 2px 14px; }
    .db-avatar {
      width: 46px; height: 46px; border-radius: 50%; overflow: hidden; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, var(--blue), var(--green)); color: #fff; font-weight: 600; font-size: 19px;
      box-shadow: 0 0 0 3px #fff, var(--shadow);
      text-decoration: none;
    }
    .db-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .db-hello { flex: 1; min-width: 0; }
    .db-hello small { display: block; font-size: 12.5px; color: var(--muted); }
    .db-hello strong { display: block; font-size: 18px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Hero ── */
    .db-hero {
      position: relative; overflow: hidden; border-radius: 28px; min-height: 290px;
      background: linear-gradient(180deg, #DDEFFF 0%, #F3FAFF 58%, #FFFFFF 100%);
      box-shadow: var(--shadow-lg); padding: 22px 20px 0;
    }
    .db-hero-art { position: absolute; left: 0; right: 0; bottom: 0; width: 100%; height: 175px; display: block; }
    .db-hero-copy { position: relative; z-index: 2; max-width: 240px; }
    .db-hero-copy small { display: block; font-size: 13px; color: var(--blue-ink); opacity: .75; }
    .db-hero-copy h1 { font-size: 30px; line-height: 1.15; font-weight: 700; color: var(--blue); margin: 2px 0 6px; letter-spacing: -.3px; }
    .db-hero-copy p { font-size: 13px; color: var(--blue-ink); opacity: .8; margin: 0 0 14px; font-weight: 400; }
    .db-cta {
      display: inline-flex; align-items: center; gap: 8px; text-decoration: none; border: none; cursor: pointer;
      background: var(--blue); color: #fff; font-family: inherit; font-size: 14px; font-weight: 600;
      padding: 11px 18px; border-radius: 999px; box-shadow: 0 8px 18px rgba(7,85,232,.28);
    }
    .db-cta .ic { width: 18px; height: 18px; }
    .db-weather {
      position: absolute; top: 16px; right: 16px; z-index: 3; width: 112px;
      padding: 10px 12px; border-radius: 18px; box-sizing: border-box;
      background: rgba(255,255,255,.6); border: 1px solid rgba(255,255,255,.85);
      backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
      box-shadow: 0 6px 16px rgba(11,42,91,.06);
    }
    .db-weather { text-align: center; }
    .db-weather-main { display: flex; align-items: center; justify-content: center; gap: 6px; }
    .db-weather-icon { width: 28px; height: 28px; color: #E0A526; stroke-width: 1.7; flex-shrink: 0; }
    .db-weather-icon.cloudy { color: #6B8BB8; }
    .db-weather-icon.night { color: #5B6FA8; }
    .db-weather-icon.rain { color: var(--blue); }
    .db-weather-temp { font-size: 20px; font-weight: 600; color: var(--blue-ink); line-height: 1; }
    .db-weather-desc { display: block; font-size: 12px; font-weight: 500; color: var(--blue-ink); margin-top: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-weather-loc { display: block; width: 100%; font-size: 10.5px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Live event / school cards ── */
    .db-live {
      display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff;
      padding: 14px 16px; border-radius: 22px; margin-bottom: 10px;
      background: linear-gradient(135deg, var(--blue), #2C74F0); box-shadow: 0 10px 24px rgba(7,85,232,.22);
    }
    .db-live .dot { width: 9px; height: 9px; border-radius: 50%; background: #5EE29A; flex-shrink: 0; box-shadow: 0 0 0 4px rgba(94,226,154,.25); }
    .db-live .t { flex: 1; min-width: 0; }
    .db-live small { display: block; font-size: 11.5px; opacity: .8; }
    .db-live strong { display: block; font-size: 15px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-live span { font-size: 12px; opacity: .9; }
    .db-row-card {
      display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--ink);
      background: #fff; border-radius: 22px; padding: 14px 16px; box-shadow: var(--shadow); margin-bottom: 10px;
    }
    .db-row-card .ico { width: 42px; height: 42px; border-radius: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; background: var(--sky); color: var(--blue); }
    .db-row-card .t { flex: 1; min-width: 0; }
    .db-row-card strong { display: block; font-size: 14.5px; font-weight: 600; }
    .db-row-card span { font-size: 12px; color: var(--muted); }
    .chev { width: 18px; height: 18px; color: #A8B6C8; flex-shrink: 0; }

    /* ── Quick actions ── */
    .db-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
    .db-action {
      display: flex; flex-direction: column; align-items: center; gap: 8px; text-decoration: none;
      padding: 14px 4px 12px; border-radius: 22px; background: #fff; box-shadow: var(--shadow);
      color: var(--ink); font-size: 12px; font-weight: 500; text-align: center; line-height: 1.25;
    }
    .db-action .ico { width: 46px; height: 46px; border-radius: 16px; display: flex; align-items: center; justify-content: center; }
    .db-action .ico.blue  { background: var(--sky); color: var(--blue); }
    .db-action .ico.green { background: #E6F6ED; color: var(--green); }
    .db-action .ico.gold  { background: #FBF3DF; color: var(--gold); }
    .db-action .ico.ink   { background: #EEF2F7; color: var(--blue-ink); }

    /* ── Small shop ad ── */
    .db-ad {
      position: relative; display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff;
      margin-bottom: 22px; padding: 12px 40px 12px 12px; border-radius: 20px;
      background: linear-gradient(135deg, var(--green), #12844A); box-shadow: 0 8px 20px rgba(21,155,85,.22);
    }
    .db-ad .ico { width: 40px; height: 40px; border-radius: 13px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,.18); }
    .db-ad .t { flex: 1; min-width: 0; }
    .db-ad strong { display: block; font-size: 14px; font-weight: 600; }
    .db-ad .t span { display: block; font-size: 11.5px; opacity: .88; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-ad .go { flex-shrink: 0; background: #fff; color: var(--green); font-size: 12.5px; font-weight: 600; padding: 7px 13px; border-radius: 999px; }
    .db-ad .x { position: absolute; top: 6px; right: 6px; width: 26px; height: 26px; border: none; border-radius: 50%; background: rgba(255,255,255,.18); color: #fff; display: flex; align-items: center; justify-content: center; cursor: pointer; padding: 0; }
    .db-ad .x .ic { width: 14px; height: 14px; }

    /* ── Provincial Top 3 ── */
    .db-prov {
      appearance: none; -webkit-appearance: none; font-family: inherit; font-size: 12.5px; font-weight: 500; color: var(--blue-ink);
      padding: 7px 30px 7px 12px; border-radius: 999px; border: 1px solid var(--line); background: #fff
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center / 14px;
      max-width: 160px;
    }
    .db-top { display: grid; gap: 10px; }
    .db-top[hidden] { display: none; }
    .db-top-first {
      position: relative; display: block; text-decoration: none; color: #fff; height: 170px;
      border-radius: 26px; overflow: hidden; box-shadow: var(--shadow-lg);
      background: linear-gradient(160deg, #3B6FD8, var(--blue-ink)) center / cover no-repeat;
    }
    .db-top-first::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(11,42,91,0) 35%, rgba(11,42,91,.78)); }
    .db-top-first .info { position: absolute; left: 16px; right: 16px; bottom: 14px; z-index: 2; }
    .db-top-first strong { display: block; font-size: 18px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-top-first .info > span { display: block; font-size: 12.5px; opacity: .88; }
    .db-top-first .medal-wrap { position: absolute; top: 12px; left: 12px; z-index: 2; display: block; }
    .db-top-row {
      display: flex; align-items: center; gap: 12px; text-decoration: none; color: var(--ink);
      background: #fff; border-radius: 22px; padding: 10px 14px 10px 10px; box-shadow: var(--shadow);
    }
    .db-top-row .thumb { position: relative; width: 58px; height: 58px; border-radius: 16px; flex-shrink: 0; background: linear-gradient(160deg, #C9DBF5, #8FB1E6) center / cover no-repeat; }
    .db-top-row .thumb .medal-wrap { position: absolute; top: 5px; left: 5px; display: block; }
    .db-top-row .t { flex: 1; min-width: 0; }
    .db-top-row strong { display: block; font-size: 14.5px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-top-row .t > span { display: block; font-size: 12px; color: var(--muted); }
    .db-stat-pill { display: inline-flex; align-items: center; gap: 4px; font-size: 11.5px !important; color: var(--blue-ink) !important; margin-top: 3px; }
    .db-top-first .db-stat-pill { color: #fff !important; opacity: .95; }
    .rank-glass {
      display: flex; align-items: center; justify-content: center; box-sizing: border-box;
      border-radius: 50%; color: #fff; font-weight: 600; line-height: 1;
      background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.7);
      backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
      text-shadow: 0 1px 2px rgba(0,0,0,.35);
    }
    .db-empty { background: #fff; border-radius: 22px; padding: 22px 16px; text-align: center; color: var(--muted); font-size: 13px; box-shadow: var(--shadow); }

    /* ── Nearby ── */
    #db-map { width: 100%; height: 190px; border-radius: 24px; overflow: hidden; box-shadow: var(--shadow); isolation: isolate; position: relative; z-index: 0; margin-bottom: 10px; }
    .db-place-list { display: flex; flex-direction: column; gap: 8px; }
    .db-place-item { background: #fff; border-radius: 20px; padding: 10px 14px 10px 10px; display: flex; gap: 12px; align-items: center; text-decoration: none; color: inherit; box-shadow: var(--shadow); }
    .db-place-thumb { width: 52px; height: 52px; border-radius: 14px; background: var(--sky); flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; color: var(--blue); }
    .db-place-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .db-place-info { flex: 1; min-width: 0; }
    .db-place-info strong { display: block; font-size: 14px; font-weight: 600; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .db-place-info span { display: block; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
    .db-place-dist { text-align: right; flex-shrink: 0; }
    .db-place-dist strong { display: block; font-size: 14px; font-weight: 600; }
    .db-place-dist small { font-size: 11px; color: var(--muted); }
  </style>
</head>
<body>
  <main class="app db" style="max-width:100%;padding-bottom:96px">
    <?php include "includes/topbar.php"; ?>

    <!-- Greeting · profile · points -->
    <div class="db-head">
      <a class="db-avatar" href="profile.php" aria-label="โปรไฟล์">
        <?php if (!empty($user["avatar_url"])): ?>
          <img src="<?= e($user["avatar_url"]) ?>" alt="">
        <?php else: ?>
          <?= e(mb_substr($user["name"] ?? "?", 0, 1, "UTF-8")) ?>
        <?php endif; ?>
      </a>
      <div class="db-hello">
        <small>สวัสดี!</small>
        <strong><?= e($user["name"]) ?></strong>
      </div>
    </div>

    <!-- Hero -->
    <section class="db-hero">
      <div class="db-weather" id="db-weather">
        <div class="db-weather-main">
          <svg class="ic db-weather-icon" id="weather-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
          <span class="db-weather-temp" id="weather-temp">--°</span>
        </div>
        <span class="db-weather-desc" id="weather-desc">อากาศ</span>
        <span class="db-weather-loc" id="weather-loc">กำลังระบุ...</span>
      </div>
      <div class="db-hero-copy">
        <small>ออกเดินทางไปกับ</small>
        <h1>ล่าพิกัด</h1>
        <p>เที่ยว เช็คอิน สะสมคะแนน<br>จากสถานที่และร้านค้าชุมชน</p>
        <a class="db-cta" href="#nearby-section">
          เริ่มต้นใช้งาน
          <svg class="ic" viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
        </a>
      </div>
      <!-- Illustration: sky, mountains, river, Thai temple, community shop -->
      <svg class="db-hero-art" viewBox="0 0 390 175" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
        <defs>
          <linearGradient id="hRiver" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#8CC4FF"/><stop offset="1" stop-color="#4E96F2"/></linearGradient>
          <linearGradient id="hHillFront" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1FAE62"/><stop offset="1" stop-color="#128A4B"/></linearGradient>
          <linearGradient id="hGold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#F0C766"/><stop offset="1" stop-color="#C9972B"/></linearGradient>
        </defs>
        <circle cx="300" cy="30" r="20" fill="#FFE6A8" opacity=".8"/>
        <path d="M0 92 L48 52 L86 78 L132 34 L182 80 L214 60 L256 88 L300 50 L350 84 L390 66 V175 H0Z" fill="#C7DEF8"/>
        <path d="M120 36 L132 34 L140 44 L128 46Z M292 54 L300 50 L308 58 L298 60Z" fill="#fff" opacity=".85"/>
        <path d="M0 112 C40 92 80 98 120 106 C170 116 210 92 260 96 C310 100 350 112 390 100 V175 H0Z" fill="#8FD0AE"/>
        <path d="M0 132 C60 118 110 128 160 126 C220 124 270 112 330 120 C360 124 378 128 390 126 V175 H0Z" fill="url(#hHillFront)"/>
        <path d="M150 175 C160 160 186 150 214 146 C246 141 262 132 300 128 C330 125 360 127 390 124 V134 C356 136 330 136 304 140 C270 145 254 154 226 160 C206 165 194 170 190 175Z" fill="url(#hRiver)"/>
        <path d="M206 160 C222 155 236 150 252 147 M290 136 C306 133 322 132 336 132" stroke="#fff" stroke-width="2" stroke-linecap="round" opacity=".7" fill="none"/>
        <!-- temple -->
        <g transform="translate(300 78)">
          <rect x="-2" y="44" width="64" height="8" rx="2" fill="#F4F7FB"/>
          <rect x="6" y="26" width="48" height="20" fill="#FFFFFF"/>
          <rect x="24" y="32" width="12" height="14" rx="6" fill="#D9E6F5"/>
          <path d="M0 28 L30 10 L60 28Z" fill="url(#hGold)"/>
          <path d="M10 18 L30 2 L50 18Z" fill="url(#hGold)" opacity=".92"/>
          <path d="M28 2 L30 -10 L32 2Z" fill="#C9972B"/>
          <path d="M0 28 L-4 24 M60 28 L64 24" stroke="#C9972B" stroke-width="2" stroke-linecap="round"/>
        </g>
        <!-- chedi -->
        <g transform="translate(276 92)" fill="#FFFFFF">
          <rect x="-8" y="30" width="16" height="8" rx="2"/>
          <path d="M-7 30 C-7 18 7 18 7 30Z"/>
          <path d="M-2 18 L0 2 L2 18Z" fill="url(#hGold)"/>
        </g>
        <!-- community shop -->
        <g transform="translate(36 104)">
          <rect x="0" y="18" width="56" height="26" rx="3" fill="#FFFFFF"/>
          <path d="M-4 18 H60 L54 6 H2Z" fill="#0755E8"/>
          <path d="M6 6 L4 18 M18 6 L17 18 M30 6 V18 M42 6 L43 18 M54 6 L56 18" stroke="#FFFFFF" stroke-width="3" opacity=".9"/>
          <rect x="8" y="26" width="18" height="18" rx="2" fill="#D9E6F5"/>
          <rect x="32" y="26" width="16" height="10" rx="2" fill="#FFE6A8"/>
        </g>
        <!-- trees -->
        <g fill="#128A4B">
          <circle cx="16" cy="128" r="10"/><circle cx="104" cy="124" r="8"/><circle cx="118" cy="128" r="10"/>
          <circle cx="250" cy="118" r="9" fill="#1FAE62"/><circle cx="372" cy="120" r="10" fill="#1FAE62"/>
        </g>
        <g stroke="#0E6E3B" stroke-width="2"><path d="M16 138 V146 M118 138 V146"/></g>
      </svg>
    </section>

    <!-- Events I'm in right now (school check-in) -->
    <?php foreach ($currentEvents as $ce): ?>
      <a class="db-live" href="event_room.php?id=<?= intval($ce["id"]) ?>">
        <span class="dot"></span>
        <span class="t">
          <small>คุณอยู่ในกิจกรรม</small>
          <strong><?= e($ce["title"]) ?></strong>
          <span><?= $ce["attended"] ? "เช็คชื่อแล้ว" : "แตะเพื่อสแกนเช็คชื่อ" ?></span>
        </span>
        <svg class="ic chev" viewBox="0 0 24 24" style="color:#fff"><path d="M9 6l6 6-6 6"/></svg>
      </a>
    <?php endforeach; ?>

    <!-- Quick actions -->
    <section>
      <div class="db-actions" style="grid-template-columns:repeat(<?= $role === "shop" ? 3 : 2 ?>, 1fr)">
        <a class="db-action" href="places.php">
          <span class="ico blue"><svg class="ic" viewBox="0 0 24 24"><path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/></svg></span>
          เช็คอิน<br>ถ่ายรูป
        </a>
        <a class="db-action" href="scan_any.php">
          <span class="ico green"><svg class="ic" viewBox="0 0 24 24"><path d="M4 8V5a1 1 0 0 1 1-1h3M16 4h3a1 1 0 0 1 1 1v3M20 16v3a1 1 0 0 1-1 1h-3M8 20H5a1 1 0 0 1-1-1v-3M7 12h10"/></svg></span>
          สแกน QR<br>รับรางวัล
        </a>
        <?php if ($role === "shop"): ?>
        <a class="db-action" href="shop.php">
          <span class="ico ink"><svg class="ic" viewBox="0 0 24 24"><path d="M4 10l1.5-5h13L20 10M4 10v9h16v-9M4 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3M10 19v-4h4v4"/></svg></span>
          จัดการ<br>ร้านค้า
        </a>
        <?php endif; ?>
      </div>
    </section>

    <?php if (!empty($navShowSchool)): ?>
      <a class="db-row-card" href="events.php" style="margin-top:-8px;margin-bottom:22px">
        <span class="ico"><svg class="ic" viewBox="0 0 24 24"><path d="M12 4L2 9l10 5 10-5-10-5z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5"/></svg></span>
        <span class="t"><strong>กิจกรรมโรงเรียน</strong><span>เช็คชื่อเข้าร่วมกิจกรรมด้วย QR</span></span>
        <svg class="ic chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
      </a>
    <?php endif; ?>

    <?php if ($showShopPromo): ?>
    <!-- Small ad: open a shop -->
    <a class="db-ad" id="shop-promo" href="shop_apply.php">
      <span class="ico"><svg class="ic" viewBox="0 0 24 24"><path d="M4 10l1.5-5h13L20 10M4 10v9h16v-9M4 10c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3s3-1.3 3-3c0 1.7 1.3 3 3 3M10 19v-4h4v4"/></svg></span>
      <span class="t">
        <strong>เป็นเจ้าของร้านค้า?</strong>
        <span>เปิดร้านฟรี ให้ลูกค้าเช็คอินสะสมแต้ม</span>
      </span>
      <span class="go">สมัคร</span>
      <button type="button" class="x" aria-label="ปิด" onclick="event.preventDefault();event.stopPropagation();dismissShopPromo()">
        <svg class="ic" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg>
      </button>
    </a>
    <script>
      if (localStorage.getItem('shop_promo_dismissed') === '1') document.getElementById('shop-promo').style.display = 'none';
      function dismissShopPromo() {
        localStorage.setItem('shop_promo_dismissed', '1');
        document.getElementById('shop-promo').style.display = 'none';
      }
    </script>
    <?php endif; ?>

    <!-- Provincial Top 3 -->
    <section>
      <div class="db-h2">
        <h2>ร้านยอดนิยมประจำจังหวัด</h2>
        <?php if ($provinces): ?>
          <select class="db-prov" id="prov-select" aria-label="เลือกจังหวัด" onchange="showProvince(this.value)">
            <option value="">ทุกจังหวัด</option>
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>"><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        <?php endif; ?>
      </div>
      <?php if (!$allShops): ?>
        <div class="db-empty">ยังไม่มีร้านค้าที่ร่วมกับเรา</div>
      <?php endif; ?>
      <?php foreach ($topByProvince as $pv => $list): if (!$list) continue; ?>
        <div class="db-top" data-prov="<?= e($pv) ?>" <?= $pv === "" ? "" : 'hidden' ?>>
          <?php foreach ($list as $i => $s): $rank = $i + 1;
            $bg = !empty($s["image_url"]) ? 'background-image:url(&quot;' . e($s["image_url"]) . '&quot;)' : "";
            $stat = intval($s["recent_checkins"]) > 0 ? number_format($s["recent_checkins"]) . " เช็คอิน · " . SHOP_RANK_DAYS . " วัน" : "ร้านใหม่";
          ?>
            <?php if ($rank === 1): ?>
              <a class="db-top-first" href="place.php?id=<?= intval($s["id"]) ?>" style="<?= $bg ?>">
                <span class="medal-wrap"><?= rankBadge(1, 36) ?></span>
                <div class="info">
                  <strong><?= e($s["name"]) ?></strong>
                  <span><?= e($locOf($s)) ?></span>
                  <span class="db-stat-pill"><?= $stat ?></span>
                </div>
              </a>
            <?php else: ?>
              <a class="db-top-row" href="place.php?id=<?= intval($s["id"]) ?>">
                <span class="thumb" style="<?= $bg ?>"><span class="medal-wrap"><?= rankBadge($rank, 26) ?></span></span>
                <span class="t">
                  <strong><?= e($s["name"]) ?></strong>
                  <span><?= e($locOf($s)) ?></span>
                  <span class="db-stat-pill"><?= $stat ?></span>
                </span>
                <svg class="ic chev" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg>
              </a>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </section>

    <!-- Nearby places -->
    <section id="nearby-section">
      <div class="db-h2">
        <h2>สถานที่ใกล้คุณ</h2>
        <a href="places.php">ดูทั้งหมด</a>
      </div>
      <div id="db-map"></div>
      <div class="db-place-list" id="db-place-list">
        <div style="text-align:center;padding:20px;color:#94a3b8;font-size:13px">กำลังโหลด...</div>
      </div>
    </section>
  </main>

  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  const PLACES = <?= $placesJson ?>;
  const GPS_NEAR_KM = 1;

  // Provincial Top 3: switch the shown province.
  function showProvince(v) {
    document.querySelectorAll('.db-top[data-prov]').forEach(el => { el.hidden = el.dataset.prov !== v; });
    const sel = document.getElementById('prov-select');
    if (sel && sel.value !== v) sel.value = v;
  }
  // Pick the user's province automatically once we know where they are.
  function autoProvince(stateName) {
    const sel = document.getElementById('prov-select');
    if (!sel || !stateName || sel.dataset.touched) return;
    const opt = [...sel.options].find(o => o.value && stateName.includes(o.value));
    if (opt) showProvince(opt.value);
  }
  document.getElementById('prov-select')?.addEventListener('change', e => { e.target.dataset.touched = '1'; });

  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2-lat1)*Math.PI/180;
    const dLng = (lng2-lng1)*Math.PI/180;
    const a = Math.sin(dLat/2)**2 + Math.cos(lat1*Math.PI/180)*Math.cos(lat2*Math.PI/180)*Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  // Line icons for the weather box.
  const WEATHER_ICONS = {
    sun:   '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    moon:  '<path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/>',
    part:  '<path d="M8 3v1.5M3.5 8H2M4.3 4.3l1 1M12.3 4.3l-1 1"/><path d="M5.2 10.4A3.5 3.5 0 0 1 11.4 6.6"/><path d="M9 20h8.5a3.5 3.5 0 0 0 .4-7A5 5 0 0 0 8.2 12.6 3.7 3.7 0 0 0 9 20z"/>',
    partNight: '<path d="M11.5 8.2A4.5 4.5 0 0 1 5 3.6a4.6 4.6 0 0 0-1.6 6.6"/><path d="M9 20h8.5a3.5 3.5 0 0 0 .4-7A5 5 0 0 0 8.2 12.6 3.7 3.7 0 0 0 9 20z"/>',
    cloud: '<path d="M7 18h10.5a4 4 0 0 0 .5-8 6 6 0 0 0-11.6 1.6A3.3 3.3 0 0 0 7 18z"/>',
    fog:   '<path d="M7 13h10.5a3.6 3.6 0 0 0 .4-7.1 5.5 5.5 0 0 0-10.6 1.4A3 3 0 0 0 7 13z"/><path d="M4 17h16M7 21h10"/>',
    rain:  '<path d="M7 14h10.5a3.6 3.6 0 0 0 .4-7.1 5.5 5.5 0 0 0-10.6 1.4A3 3 0 0 0 7 14z"/><path d="M8 17l-1 3M12 17l-1 3M16 17l-1 3"/>',
    storm: '<path d="M7 13h10.5a3.6 3.6 0 0 0 .4-7.1 5.5 5.5 0 0 0-10.6 1.4A3 3 0 0 0 7 13z"/><path d="M12.5 14l-2.5 4h3.5l-2 4"/>'
  };

  // Turn Open-Meteo "current" data into [text, icon, colour class]. Uses the
  // measured cloud cover % and rain rather than the coarse weather code alone,
  // and night-time icons after dark.
  function describeWeather(c) {
    const code = c.weather_code, clouds = c.cloud_cover ?? 0, rain = c.precipitation ?? 0, day = c.is_day !== 0;
    if (code >= 95) return ['ฝนฟ้าคะนอง', 'storm', 'rain'];
    if (code >= 63 && code <= 67 || code >= 81 && code <= 82 || rain >= 2.5) return ['ฝนตกหนัก', 'rain', 'rain'];
    if (code >= 51 && code <= 82 || rain > 0) return ['ฝนตกเล็กน้อย', 'rain', 'rain'];
    if (code === 45 || code === 48) return ['มีหมอก', 'fog', 'cloudy'];
    if (clouds < 20) return day ? ['ท้องฟ้าแจ่มใส', 'sun', ''] : ['ท้องฟ้าโปร่ง', 'moon', 'night'];
    if (clouds < 60) return [day ? 'มีเมฆบางส่วน' : 'มีเมฆบางส่วน', day ? 'part' : 'partNight', day ? '' : 'night'];
    if (clouds < 90) return ['เมฆเป็นส่วนมาก', 'cloud', 'cloudy'];
    return ['เมฆครึ้ม', 'cloud', 'cloudy'];
  }

  function showWeather(c) {
    const [text, icon, tone] = describeWeather(c);
    document.getElementById('weather-temp').textContent = Math.round(c.temperature_2m) + '°';
    document.getElementById('weather-desc').textContent = text;
    const el = document.getElementById('weather-icon');
    el.innerHTML = WEATHER_ICONS[icon];
    el.classList.remove('cloudy', 'rain', 'night');
    if (tone) el.classList.add(tone);
  }

  function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }

  const map = L.map('db-map', { zoomControl: false, attributionControl: false }).setView([15.87, 100.99], 6);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

  const userIcon = L.divIcon({
    className: '',
    html: '<div style="width:16px;height:16px;background:#0755E8;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 6px rgba(0,0,0,.3)"></div>',
    iconSize: [16,16], iconAnchor: [8,8]
  });
  const placeIcon = L.divIcon({
    className: '',
    html: '<div style="width:12px;height:12px;background:#159B55;border:2px solid #fff;border-radius:50%;box-shadow:0 1px 4px rgba(0,0,0,.3)"></div>',
    iconSize: [12,12], iconAnchor: [6,6]
  });

  PLACES.forEach(p => {
    if (p.lat && p.lng) L.marker([p.lat, p.lng], {icon: placeIcon}).addTo(map).bindPopup(esc(p.name));
  });

  function renderPlaces(places) {
    const list = document.getElementById('db-place-list');
    if (!places.length) {
      list.innerHTML = '<div style="text-align:center;padding:16px;color:#94a3b8;font-size:13px">ไม่พบสถานที่ใกล้คุณ</div>';
      return;
    }
    list.innerHTML = places.slice(0, 4).map(p => {
      const hasDist = p.dist !== undefined && isFinite(p.dist);
      const dist = hasDist ? p.dist.toFixed(1) + ' กม.' : '-- กม.';
      const near = hasDist && p.dist <= GPS_NEAR_KM;
      return `
        <a href="place.php?id=${p.id}" class="db-place-item">
          <div class="db-place-thumb">
            ${p.img
              ? `<img src="${esc(p.img)}" alt="">`
              : `<svg class="ic" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>`}
          </div>
          <div class="db-place-info">
            <strong>${esc(p.name)}</strong>
            <span>${esc(p.loc || 'ไม่ระบุพื้นที่')}</span>
          </div>
          <div class="db-place-dist">
            <strong style="color:${near ? '#159B55' : '#0755E8'}">${dist}</strong>
            <small>${near ? 'ใกล้มาก' : ''}</small>
          </div>
        </a>`;
    }).join('');
  }

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      const lat = pos.coords.latitude;
      const lng = pos.coords.longitude;

      map.setView([lat, lng], 13);
      L.marker([lat, lng], {icon: userIcon}).addTo(map);
      L.circle([lat, lng], {radius: GPS_NEAR_KM * 1000, color:'#0755E8', fillOpacity:.08, weight:1}).addTo(map);

      const withDist = PLACES.map(p => ({
        ...p,
        dist: (p.lat && p.lng) ? haversine(lat, lng, p.lat, p.lng) : Infinity
      })).sort((a,b) => a.dist - b.dist);
      renderPlaces(withDist);

      fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lng}&current=temperature_2m,weather_code,cloud_cover,precipitation,is_day&timezone=auto`)
        .then(r => r.json())
        .then(data => {
          showWeather(data.current);
        }).catch(() => {});

      fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&accept-language=th`)
        .then(r => r.json())
        .then(data => {
          const addr = data.address || {};
          const loc = addr.county || addr.city_district || addr.suburb || addr.town || addr.city || '';
          const prov = addr.state || addr.province || '';
          document.getElementById('weather-loc').textContent = loc || prov || 'ตำแหน่งปัจจุบัน';
          autoProvince(prov);
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
