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
$left  = $total - $done;

$locParts = array_filter([
    $place["district"] ? "อ." . $place["district"] : null,
    $place["province"] ? "จ." . $place["province"] : null,
    $place["location_text"]
]);
$locText = implode(" ", $locParts) ?: "ไม่ระบุพื้นที่";
$hasGps  = $place["lat"] !== null && $place["lng"] !== null;

// Partner shop: rank, my points here and the shop's own rewards.
$isShop = $place["owner_user_id"] !== null;
$isOwner = $isShop && intval($place["owner_user_id"]) === $userId;
if ($isShop) {
    require_once "_shop_points.php";
    $shopRank    = getShopRank($conn, $placeId);
    $shopRewards = getShopRewards($conn, $placeId);
    try { $myShopPts = getShopBalance($conn, $userId, $placeId); } catch (mysqli_sql_exception $e) { $myShopPts = 0; }
}

$img = !empty($place["image_url"]) ? $place["image_url"] : "";
$bgStyle = $img !== "" ? 'background-image:url(&quot;' . e($img) . '&quot;)' : "";

// Mission buttons / icons per quest type.
$typeMeta = [
    "checkin" => ["เริ่มภารกิจ", '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'],
    "gps"     => ["เริ่มภารกิจ", '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>'],
    "qr"      => ["สแกนเลย",    '<path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 14h2v2h-2zM14 18h2v2h-2zM18 18h2v2h-2z"/>'],
    "photo"   => ["ถ่ายรูป",     '<path d="M4 8h3l2-3h6l2 3h3a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V9a1 1 0 0 1 1-1z"/><circle cx="12" cy="13" r="3.5"/>'],
    "quiz"    => ["ตอบคำถาม",   '<circle cx="12" cy="12" r="9"/><path d="M9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5h.01"/>'],
];
$defaultTab = ($_GET["tab"] ?? "") === "rewards" && $isShop ? "rewards" : "missions";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($place["name"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <?php if ($isShop): ?><link rel="stylesheet" href="css/shop_points.css?v=<?= @filemtime(__DIR__ . '/css/shop_points.css') ?>"><?php endif; ?>
  <style>
    :root {
      --blue: #0755E8; --blue-ink: #0B2A5B; --green: #159B55; --sky: #EAF6FF;
      --gold: #C9972B; --ink: #0F1B2D; --muted: #64748B; --line: #E7EEF6;
      --shadow: 0 6px 24px rgba(11,42,91,.07);
    }
    body { font-family: 'LINE Seed Sans TH', 'Kanit', sans-serif; background: linear-gradient(180deg, var(--sky) 0, #F7FBFF 360px) !important; background-image: none; color: var(--ink); }
    .ic { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round; flex-shrink: 0; }

    /* ── Cover + info card ── */
    .pl-cover {
      position: relative; height: 190px; border-radius: 28px; overflow: hidden;
      background: linear-gradient(160deg, #7FB3F5, #0755E8) center / cover no-repeat;
      box-shadow: var(--shadow);
    }
    .pl-cover::after { content: ''; position: absolute; inset: 0; background: linear-gradient(180deg, rgba(11,42,91,.25), rgba(11,42,91,0) 45%); }
    .pl-back {
      position: absolute; top: 12px; left: 12px; z-index: 2; width: 38px; height: 38px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center; color: #fff; text-decoration: none;
      background: rgba(255,255,255,.22); border: 1px solid rgba(255,255,255,.55);
      backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);
    }
    .pl-card {
      position: relative; z-index: 2; margin: -64px 10px 14px; padding: 14px;
      background: rgba(255,255,255,.92); border: 1px solid rgba(255,255,255,.9); border-radius: 24px;
      backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px);
      box-shadow: 0 12px 30px rgba(11,42,91,.12);
    }
    .pl-card-top { display: flex; gap: 12px; align-items: flex-start; }
    .pl-thumb {
      width: 84px; height: 84px; border-radius: 18px; flex-shrink: 0; overflow: hidden;
      background: linear-gradient(160deg, #C9DBF5, #8FB1E6) center / cover no-repeat;
      display: flex; align-items: center; justify-content: center; color: #fff;
    }
    .pl-thumb .ic { width: 34px; height: 34px; }
    .pl-info { flex: 1; min-width: 0; }
    .pl-badge {
      display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600;
      padding: 3px 9px; border-radius: 999px; margin-bottom: 4px;
      background: #FBF3DF; color: #8A6413;
    }
    .pl-badge.cat { background: var(--sky); color: var(--blue); }
    .pl-badge .ic { width: 13px; height: 13px; }
    .pl-info h1 { font-size: 19px; font-weight: 600; color: var(--ink); margin: 0 0 3px; line-height: 1.25; }
    .pl-loc { display: flex; align-items: center; gap: 4px; font-size: 12.5px; color: var(--muted); }
    .pl-loc .ic { width: 14px; height: 14px; color: var(--blue); }
    .pl-loc span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pl-meta { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); }
    .pl-stat { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); min-width: 0; }
    .pl-stat b { color: var(--ink); font-size: 15px; font-weight: 600; }
    .pl-stat .ic { width: 18px; height: 18px; color: var(--gold); }
    .pl-action {
      display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0; cursor: pointer;
      font-family: inherit; font-size: 13px; font-weight: 600; color: var(--blue); text-decoration: none;
      background: #fff; border: 1px solid #D5E3F7; border-radius: 999px; padding: 8px 14px;
    }
    .pl-action .ic { width: 16px; height: 16px; }

    .gps-check {
      display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500;
      padding: 5px 10px; border-radius: 99px; margin: 0 0 12px 10px;
    }
    .gps-check.ok   { background: #f0fdf4; color: #16a34a; }
    .gps-check.far  { background: #fff1f2; color: #e11d48; }
    .gps-check.wait { background: #eff6ff; color: #2563eb; }

    /* ── Tabs ── */
    .pl-tabs { display: flex; gap: 4px; padding: 4px; border-radius: 999px; background: #fff; box-shadow: var(--shadow); margin-bottom: 18px; }
    .pl-tab {
      flex: 1; display: flex; align-items: center; justify-content: center; gap: 6px;
      border: none; background: none; cursor: pointer; font-family: inherit; font-size: 13.5px; font-weight: 500; color: var(--muted);
      padding: 10px 6px; border-radius: 999px; transition: background .2s, color .2s;
    }
    .pl-tab .ic { width: 18px; height: 18px; }
    .pl-tab.active { background: var(--sky); color: var(--blue); font-weight: 600; }
    .pl-panel[hidden] { display: none; }

    /* ── Missions ── */
    .pl-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin: 0 4px 12px; }
    .pl-head h2 { display: flex; align-items: center; gap: 8px; font-size: 18px; font-weight: 600; margin: 0; color: var(--ink); }
    .pl-head h2 .ic { color: var(--blue); width: 22px; height: 22px; }
    .pl-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--muted); }
    .pl-pill { flex-shrink: 0; font-size: 12px; font-weight: 600; color: var(--green); background: #E6F6ED; padding: 5px 11px; border-radius: 999px; white-space: nowrap; }
    .pl-pill.done { color: var(--muted); background: #EEF2F7; }
    .pl-progress { height: 6px; border-radius: 99px; background: #E9EFF6; overflow: hidden; margin: 0 4px 14px; }
    .pl-progress i { display: block; height: 100%; border-radius: 99px; background: linear-gradient(90deg, var(--green), #2BC47A); }
    .qrow {
      display: flex; align-items: center; gap: 12px; background: #fff; border-radius: 22px; padding: 10px;
      box-shadow: var(--shadow); margin-bottom: 10px; text-decoration: none; color: inherit; transition: opacity .2s;
    }
    .qrow.locked { opacity: .45; pointer-events: none; }
    .qthumb {
      width: 64px; height: 64px; border-radius: 16px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;
      background: var(--sky) center / cover no-repeat; color: var(--blue);
    }
    .qthumb .ic { width: 26px; height: 26px; }
    .qrow.done .qthumb { background-color: #E6F6ED; color: var(--green); }
    .qbody { flex: 1; min-width: 0; }
    .qbody strong { display: block; font-size: 14.5px; font-weight: 600; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .qbody small { display: block; font-size: 12px; color: var(--muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
    .qpts { display: inline-flex; align-items: center; gap: 4px; font-size: 12.5px; font-weight: 600; color: var(--ink); margin-top: 4px; }
    .qpts svg { width: 15px; height: 15px; }
    .qbtn {
      display: inline-flex; align-items: center; gap: 5px; flex-shrink: 0; white-space: nowrap;
      font-size: 12.5px; font-weight: 600; color: #fff; background: var(--blue); padding: 9px 12px 9px 11px; border-radius: 999px;
      box-shadow: 0 6px 14px rgba(7,85,232,.22);
    }
    .qbtn .ic { width: 16px; height: 16px; }
    .qrow.done .qbtn { background: #E6F6ED; color: var(--green); box-shadow: none; }
    .pl-note { font-size: 12px; color: var(--blue); margin: -4px 4px 12px; }
    .pl-empty { background: #fff; border-radius: 22px; padding: 22px 16px; text-align: center; color: var(--muted); font-size: 13px; box-shadow: var(--shadow); }

    /* ── Rewards ── */
    .pl-mypts { display: flex; align-items: center; justify-content: space-between; gap: 12px; background: #fff; border-radius: 22px; padding: 14px 16px; box-shadow: var(--shadow); margin-bottom: 12px; }
    .pl-mypts small { display: block; font-size: 12px; color: var(--muted); }
    .pl-mypts b { display: block; font-size: 26px; font-weight: 700; color: var(--ink); line-height: 1.1; }
    .pl-mypts .hint { font-size: 11.5px; color: var(--muted); text-align: right; max-width: 150px; }
    .pl-rewards { background: #fff; border-radius: 22px; padding: 4px 14px; box-shadow: var(--shadow); }
    .pl-rewards .sp-cost b { color: var(--gold); }

    /* ── Info ── */
    .pl-info-card { background: #fff; border-radius: 22px; padding: 6px 16px; box-shadow: var(--shadow); }
    .pl-info-row { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--line); font-size: 13.5px; color: var(--ink); }
    .pl-info-row:last-child { border-bottom: none; }
    .pl-info-row .ic { color: var(--blue); margin-top: 1px; }
    .pl-info-row small { display: block; font-size: 12px; color: var(--muted); }
    .pl-info-row a { color: var(--blue); text-decoration: none; font-weight: 500; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:transparent;padding-bottom:96px">
    <?php include "includes/topbar.php"; ?>

    <!-- Cover -->
    <div class="pl-cover" style="<?= $bgStyle ?>">
      <a href="places.php" class="pl-back" aria-label="กลับ">
        <svg class="ic" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg>
      </a>
    </div>

    <!-- Info card -->
    <div class="pl-card">
      <div class="pl-card-top">
        <div class="pl-thumb" style="<?= $bgStyle ?>">
          <?php if ($img === ""): ?>
            <svg class="ic" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
          <?php endif; ?>
        </div>
        <div class="pl-info">
          <?php if ($isShop && $shopRank["rank"] && $shopRank["rank"] <= 10): ?>
            <span class="pl-badge"><svg class="ic" viewBox="0 0 24 24"><path d="M3 8l4 4 5-7 5 7 4-4-2 11H5z"/></svg>ร้านดังอันดับ <?= intval($shopRank["rank"]) ?></span>
          <?php elseif (!empty($place["category"])): ?>
            <span class="pl-badge cat"><?= e($place["category"]) ?></span>
          <?php endif; ?>
          <h1><?= e($place["name"]) ?></h1>
          <div class="pl-loc">
            <svg class="ic" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
            <span><?= e($locText) ?></span>
          </div>
        </div>
      </div>
      <div class="pl-meta">
        <?php if ($isShop): ?>
          <span class="pl-stat">
            <svg class="ic" viewBox="0 0 24 24" style="fill:currentColor;stroke:none"><path d="M12 2.8l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 2.9 1.1-6.3L2.9 9.4l6.3-.9z"/></svg>
            <span><b><?= number_format($shopRank["recent_checkins"]) ?></b> เช็คอิน · <?= SHOP_RANK_DAYS ?> วัน</span>
          </span>
          <?php if ($isOwner): ?>
            <a class="pl-action" href="shop.php"><svg class="ic" viewBox="0 0 24 24"><path d="M4 10l1.5-5h13L20 10M4 10v9h16v-9M10 19v-4h4v4"/></svg>จัดการร้าน</a>
          <?php else: ?>
            <button type="button" class="pl-action" onclick="openTab('rewards')"><svg class="ic" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-2-3-6-3-6-.5S10 8 12 8zm0 0c2-3 6-3 6-.5S14 8 12 8z"/></svg>แลกรางวัล</button>
          <?php endif; ?>
        <?php else: ?>
          <span class="pl-stat">
            <svg class="ic" viewBox="0 0 24 24" style="color:var(--green)"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>
            <span>สำเร็จ <b><?= $done ?>/<?= $total ?></b> ภารกิจ</span>
          </span>
          <span class="pl-stat"><b><?= $pct ?>%</b></span>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($hasGps): ?>
      <div class="gps-check wait" id="gps-check">กำลังตรวจสอบ GPS...</div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="pl-tabs" role="tablist">
      <button type="button" class="pl-tab" data-tab="missions" role="tab">
        <svg class="ic" viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>ภารกิจ
      </button>
      <?php if ($isShop): ?>
      <button type="button" class="pl-tab" data-tab="rewards" role="tab">
        <svg class="ic" viewBox="0 0 24 24"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-2-3-6-3-6-.5S10 8 12 8zm0 0c2-3 6-3 6-.5S14 8 12 8z"/></svg>รางวัล
      </button>
      <?php endif; ?>
      <button type="button" class="pl-tab" data-tab="info" role="tab">
        <svg class="ic" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><?= $isShop ? "ข้อมูลร้าน" : "ข้อมูล" ?>
      </button>
    </div>

    <!-- Missions -->
    <section class="pl-panel" data-panel="missions">
      <div class="pl-head">
        <div>
          <h2><svg class="ic" viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>ภารกิจที่ต้องทำ</h2>
          <p>ทำภารกิจเพื่อรับคะแนนสะสม</p>
        </div>
        <?php if ($total): ?>
          <span class="pl-pill <?= $left ? "" : "done" ?>"><?= $left ? "ทำได้อีก " . $left . " ภารกิจ" : "ทำครบแล้ว" ?></span>
        <?php endif; ?>
      </div>
      <?php if ($total): ?>
        <div class="pl-progress"><i style="width:<?= $pct ?>%"></i></div>
      <?php endif; ?>
      <?php if ($dailyRefresh): ?>
        <p class="pl-note">ภารกิจร้านนี้ทำซ้ำได้ทุกวัน</p>
      <?php endif; ?>

      <?php if (!$questRows): ?>
        <div class="pl-empty">ยังไม่มีภารกิจในที่นี้</div>
      <?php endif; ?>
      <?php foreach ($questRows as $q):
        $type = $q["quest_type"] ?? "checkin";
        [$btnLabel, $btnIcon] = $typeMeta[$type] ?? $typeMeta["checkin"];
      ?>
        <a href="quest.php?id=<?= intval($q["id"]) ?>" class="qrow <?= $q["done"] ? "done" : "" ?>" data-quest="<?= intval($q["id"]) ?>">
          <span class="qthumb">
            <?php if ($q["done"]): ?>
              <svg class="ic" viewBox="0 0 24 24"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>
            <?php else: ?>
              <svg class="ic" viewBox="0 0 24 24"><?= $btnIcon ?></svg>
            <?php endif; ?>
          </span>
          <span class="qbody">
            <strong><?= e($q["title"]) ?></strong>
            <?php if (!empty($q["description"])): ?><small><?= e($q["description"]) ?></small><?php endif; ?>
            <span class="qpts">
              <svg viewBox="0 0 24 24"><path d="M12 2.8l2.8 5.7 6.3.9-4.6 4.4 1.1 6.3L12 17.2l-5.6 2.9 1.1-6.3L2.9 9.4l6.3-.9z" fill="#E0AE3F"/></svg>
              +<?= intval($q["reward_points"]) ?> คะแนน
            </span>
          </span>
          <span class="qbtn">
            <?php if ($q["done"]): ?>
              สำเร็จแล้ว
            <?php else: ?>
              <svg class="ic" viewBox="0 0 24 24"><?= $btnIcon ?></svg><?= $btnLabel ?>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </section>

    <?php if ($isShop): ?>
    <!-- Rewards -->
    <section class="pl-panel" data-panel="rewards" hidden>
      <div class="pl-mypts">
        <div>
          <small>คะแนนของฉันที่ร้านนี้</small>
          <b><?= number_format($myShopPts) ?></b>
        </div>
        <span class="hint">สแกน QR ที่โต๊ะ รับ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนน (วันละครั้ง)</span>
      </div>

      <div class="pl-rewards">
        <?php if ($shopRewards): ?>
          <?php foreach ($shopRewards as $rw): $enough = $myShopPts >= intval($rw["cost_points"]); ?>
            <div class="sp-reward <?= $enough ? "ready" : "" ?>">
              <?= rewardThumbHtml($rw, 76) ?>
              <div>
                <strong><?= e($rw["name"]) ?></strong>
                <?php if (!empty($rw["description"])): ?><span><?= e($rw["description"]) ?></span><?php endif; ?>
              </div>
              <div class="sp-cost">
                <b><?= number_format($rw["cost_points"]) ?></b>
                <small><?= $enough ? "แลกได้แล้ว" : "ขาดอีก " . number_format(intval($rw["cost_points"]) - $myShopPts) ?></small>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="sp-empty">ร้านยังไม่ได้ตั้งรางวัล สะสมคะแนนไว้ก่อนได้เลย</div>
        <?php endif; ?>
      </div>

      <?php if ($isOwner): ?>
        <a class="sp-btn ghost" href="shop_rewards.php" style="margin:14px 0 0;border:1px solid #e2e8f0">จัดการรางวัลของร้าน</a>
      <?php elseif ($shopRewards): $myQr = customerQrData($conn, $userId); ?>
        <!-- Redeem here: show my QR, the shop scans it and deducts points (shop_deduct.php). -->
        <button type="button" class="sp-btn" style="margin:14px 0 0" onclick="toggleRedeemQr(this)">แลกรางวัล</button>
        <div id="redeem-qr" style="display:none;text-align:center;padding-top:14px">
          <?php if ($myQr): ?>
            <img src="<?= e(qrImageUrl($myQr, 320)) ?>" alt="QR ของฉัน" style="width:220px;max-width:100%;border-radius:16px">
          <?php endif; ?>
          <div style="font-size:13px;color:#64748b;margin-top:6px">บอกพนักงานว่าจะแลกรางวัลไหน แล้วให้ร้านสแกน QR นี้เพื่อหักคะแนน</div>
        </div>
        <script>
        function toggleRedeemQr(btn) {
          const box = document.getElementById('redeem-qr');
          const show = box.style.display === 'none';
          box.style.display = show ? 'block' : 'none';
          btn.textContent = show ? 'ซ่อน QR' : 'แลกรางวัล';
          if (show) {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            startRedeemWatch();
          }
        }
        // Once the shop deducts my points, reload so the new balance shows.
        let redeemWatch = null;
        async function startRedeemWatch() {
          if (redeemWatch) return;
          let first = null;
          redeemWatch = setInterval(async () => {
            try {
              const r = await fetch('my_points.php?poll=1', { cache: 'no-store' });
              const d = await r.json();
              if (first === null) first = d.last;
              else if (d.last !== first) location.href = 'place.php?id=<?= $placeId ?>&tab=rewards';
            } catch (e) {}
          }, 3000);
        }
        </script>
      <?php endif; ?>
    </section>
    <?php endif; ?>

    <!-- Info -->
    <section class="pl-panel" data-panel="info" hidden>
      <div class="pl-info-card">
        <?php if (!empty($place["description"])): ?>
          <div class="pl-info-row">
            <svg class="ic" viewBox="0 0 24 24"><path d="M5 5h14M5 10h14M5 15h9"/></svg>
            <div><?= nl2br(e($place["description"])) ?></div>
          </div>
        <?php endif; ?>
        <div class="pl-info-row">
          <svg class="ic" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></svg>
          <div>
            <?= e($locText) ?>
            <?php if ($hasGps): ?>
              <small><a href="https://www.google.com/maps/search/?api=1&query=<?= floatval($place["lat"]) ?>,<?= floatval($place["lng"]) ?>" target="_blank" rel="noopener">เปิดใน Google Maps</a></small>
            <?php endif; ?>
          </div>
        </div>
        <?php if (!empty($place["category"])): ?>
          <div class="pl-info-row">
            <svg class="ic" viewBox="0 0 24 24"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
            <div><small>ประเภท</small><?= e($place["category"]) ?></div>
          </div>
        <?php endif; ?>
        <?php if ($isShop): ?>
          <div class="pl-info-row">
            <svg class="ic" viewBox="0 0 24 24"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h2v2h-2zM18 18h2v2h-2z"/></svg>
            <div><small>สะสมแต้ม</small>มากินแล้วสแกน QR ที่โต๊ะ รับ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนน วันละ 1 ครั้ง</div>
          </div>
        <?php endif; ?>
        <div class="pl-info-row">
          <svg class="ic" viewBox="0 0 24 24"><path d="M5 21V4M5 4h11l-2 4 2 4H5"/></svg>
          <div><small>ภารกิจ</small>สำเร็จแล้ว <?= $done ?>/<?= $total ?> ภารกิจ (<?= $pct ?>%)</div>
        </div>
      </div>
    </section>
  </main>

  <script>
  // Tabs: ภารกิจ / รางวัล / ข้อมูล
  function openTab(name) {
    document.querySelectorAll('.pl-tab').forEach(t => {
      const on = t.dataset.tab === name;
      t.classList.toggle('active', on);
      t.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('.pl-panel').forEach(p => { p.hidden = p.dataset.panel !== name; });
  }
  document.querySelectorAll('.pl-tab').forEach(t => t.addEventListener('click', () => openTab(t.dataset.tab)));
  openTab(<?= json_encode($defaultTab) ?>);
  </script>

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
        document.querySelectorAll('.qrow:not(.done)').forEach(c => c.classList.add('locked'));
      }
    }, () => {
      document.getElementById('gps-check').remove();
    }, { timeout: 8000, maximumAge: 60000 });
  }
  </script>
  <?php endif; ?>
</body>
</html>
