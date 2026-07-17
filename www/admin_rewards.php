<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_rewards.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_reward") {
        $name  = trim($_POST["reward_name"] ?? "");
        $desc  = trim($_POST["reward_desc"] ?? "");
        $cost  = intval($_POST["cost_points"] ?? 0);
        $stock = intval($_POST["stock"] ?? 1);
        $img   = "";
        $placeIdRaw = $_POST["place_id"] ?? "";
        $placeId = $placeIdRaw === "" ? null : intval($placeIdRaw);

        if (isset($_FILES["reward_image"]) && $_FILES["reward_image"]["error"] === UPLOAD_ERR_OK) {
            $ext     = strtolower(pathinfo($_FILES["reward_image"]["name"], PATHINFO_EXTENSION));
            $allowed = ["jpg","jpeg","png","webp","gif"];
            if (in_array($ext, $allowed) && $_FILES["reward_image"]["size"] < 5 * 1024 * 1024) {
                $fname = "reward_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $dest  = __DIR__ . "/assets/images/rewards/" . $fname;
                if (!is_dir(__DIR__ . "/assets/images/rewards/")) mkdir(__DIR__ . "/assets/images/rewards/", 0755, true);
                if (move_uploaded_file($_FILES["reward_image"]["tmp_name"], $dest)) {
                    $img = "assets/images/rewards/" . $fname;
                }
            }
        }

        if ($name !== "" && $cost > 0) {
            $stmt = $conn->prepare("INSERT INTO rewards (name,description,cost_points,stock,image_url,place_id) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("ssiisi", $name, $desc, $cost, $stock, $img, $placeId);
            $stmt->execute();
            $msg = "เพิ่มรางวัลสำเร็จ";
        } else { $msg = "กรุณากรอกชื่อและคะแนน"; $msgType = "bad"; }
    }

    if ($action === "delete_reward") {
        $rid = intval($_POST["reward_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM rewards WHERE id=?");
        $stmt->bind_param("i", $rid);
        $stmt->execute();
        $msg = "ลบรางวัลสำเร็จ";
    }
}

$placesWithCounts = $conn->query("
    SELECT p.id, p.name, p.image_url, p.category, p.district, p.province,
           COUNT(r.id) AS reward_count
    FROM places p
    LEFT JOIN rewards r ON r.place_id = p.id
    GROUP BY p.id
    ORDER BY p.name
")->fetch_all(MYSQLI_ASSOC);

$globalCount = (int)($conn->query("SELECT COUNT(*) c FROM rewards WHERE place_id IS NULL")->fetch_assoc()["c"] ?? 0);

$scopeParam = $_GET["place_id"] ?? "";
$scope = ""; // "" = grid, "global" = global bucket, or a place id
$currentPlace = null;

if ($scopeParam === "global") {
    $scope = "global";
} elseif ($scopeParam !== "") {
    $pid = intval($scopeParam);
    foreach ($placesWithCounts as $p) {
        if ((int)$p["id"] === $pid) { $currentPlace = $p; $scope = $pid; break; }
    }
}

$rewards = [];
if ($scope === "global") {
    $rewards = $conn->query("SELECT * FROM rewards WHERE place_id IS NULL ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
} elseif ($currentPlace) {
    $stmt = $conn->prepare("SELECT * FROM rewards WHERE place_id=? ORDER BY id DESC");
    $stmt->bind_param("i", $currentPlace["id"]);
    $stmt->execute();
    $rewards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$inScope = ($scope !== "");
$autoOpen = $_GET["open"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการรางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:11px 18px;border:none;border-radius:999px;
      font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:opacity .2s;flex-shrink:0;
      text-decoration:none;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn.sm      { padding:7px 14px;font-size:12.5px; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    /* shop grid */
    .shop-grid { display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px; }
    .shop-card {
      background:#fff;border-radius:18px;padding:14px;box-shadow:0 2px 10px rgba(15,23,42,.06);
      text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;text-align:center;gap:8px;
      transition:box-shadow .2s;
    }
    .shop-card:hover { box-shadow:0 6px 20px rgba(15,23,42,.12); }
    .shop-photo { width:76px;height:76px;border-radius:16px;object-fit:cover;background:linear-gradient(135deg,#dbeafe,#eff6ff);flex-shrink:0; }
    .shop-photo.ph { display:flex;align-items:center;justify-content:center; }
    .shop-photo.ph svg { width:30px;height:30px;fill:#2563eb; }
    .shop-name { font-size:13.5px;font-weight:700;color:#0f172a;line-height:1.3; }
    .shop-meta { font-size:11px;color:#94a3b8; }
    .shop-badge {
      font-size:11px;font-weight:600;color:#2563eb;background:#eff6ff;
      padding:3px 10px;border-radius:99px;
    }
    .shop-badge.zero { color:#94a3b8;background:#f1f5f9; }

    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }
    .row-item img, .row-item .ph { width:52px;height:52px;border-radius:12px;object-fit:cover;flex-shrink:0;background:#e2e8f0; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { font-size:11.5px;color:#94a3b8; }

    .icon-btn {
      width:28px;height:28px;border-radius:8px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      border:none;cursor:pointer;background:#fff1f2;color:#e11d48;
    }
    .icon-btn svg { width:14px;height:14px; }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .shop-context { display:flex;align-items:center;gap:10px;background:#eff6ff;border-radius:12px;padding:10px 12px;margin-bottom:14px; }
    .shop-context img, .shop-context .ph { width:36px;height:36px;border-radius:10px;object-fit:cover;background:#dbeafe;flex-shrink:0; }
    .shop-context strong { font-size:13px;color:#1d4ed8; }

    #shops-empty { display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <?php if ($inScope): ?>
      <a href="admin_rewards.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        กลับไปเลือกร้าน
      </a>
    <?php else: ?>
      <a href="admin.php" class="back-btn">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        กลับ
      </a>
    <?php endif; ?>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if (!$inScope): ?>
      <!-- ===== SHOP GRID VIEW ===== -->
      <div class="pg-head">
        <h1>จัดการรางวัล</h1>
      </div>
      <p class="sub" style="margin:-10px 0 14px;font-size:12.5px;color:#94a3b8">เลือกร้าน/สถานที่เพื่อดูและจัดการรางวัลของร้านนั้น</p>

      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="shop-search" placeholder="ค้นหาชื่อร้าน / หมวดหมู่" oninput="filterShops(this.value)">
      </div>

      <div class="shop-grid" id="shops-list">
        <a href="admin_rewards.php?place_id=global" class="shop-card" data-search="ทั่วไป global">
          <div class="shop-photo ph">
            <svg viewBox="0 0 24 24"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
          </div>
          <div class="shop-name">รางวัลทั่วไป</div>
          <div class="shop-meta">ไม่ระบุร้าน</div>
          <span class="shop-badge <?= $globalCount == 0 ? 'zero' : '' ?>"><?= $globalCount ?> รางวัล</span>
        </a>
        <?php foreach ($placesWithCounts as $p):
          $locParts = array_filter([$p["district"] ? "อ." . $p["district"] : null, $p["province"] ? "จ." . $p["province"] : null]);
          $locText = implode(" ", $locParts);
        ?>
          <a href="admin_rewards.php?place_id=<?= $p["id"] ?>" class="shop-card"
             data-search="<?= e(mb_strtolower($p["name"] . " " . ($p["category"] ?? ""), "UTF-8")) ?>">
            <?php if ($p["image_url"]): ?>
              <img class="shop-photo" src="<?= e($p["image_url"]) ?>" alt="">
            <?php else: ?>
              <div class="shop-photo ph">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
              </div>
            <?php endif; ?>
            <div class="shop-name"><?= e($p["name"]) ?></div>
            <?php if ($locText): ?><div class="shop-meta"><?= e($locText) ?></div><?php endif; ?>
            <span class="shop-badge <?= $p["reward_count"] == 0 ? 'zero' : '' ?>"><?= $p["reward_count"] ?> รางวัล</span>
          </a>
        <?php endforeach; ?>
      </div>
      <div id="shops-empty">ไม่พบร้าน/สถานที่ที่ค้นหา</div>

    <?php else: ?>
      <!-- ===== REWARD LIST VIEW (scoped to one shop, or global) ===== -->
      <div class="pg-head">
        <h1>จัดการรางวัล</h1>
        <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-reward-form')">+ เพิ่มรางวัล</button>
      </div>

      <div class="shop-context">
        <?php if ($scope === "global"): ?>
          <div class="ph"></div>
          <strong>รางวัลทั่วไป (ไม่ระบุร้าน)</strong>
        <?php else: ?>
          <?php if ($currentPlace["image_url"]): ?>
            <img src="<?= e($currentPlace["image_url"]) ?>" alt="">
          <?php else: ?>
            <div class="ph"></div>
          <?php endif; ?>
          <strong><?= e($currentPlace["name"]) ?></strong>
        <?php endif; ?>
      </div>

      <div id="add-reward-form" class="pg-card" style="display:none;margin-bottom:14px">
        <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">เพิ่มรางวัลใหม่</h2>
        <p style="font-size:12.5px;color:#94a3b8;margin:2px 0 0">
          <?= $scope === "global" ? "รางวัลทั่วไป ไม่ผูกกับร้านใดร้านหนึ่ง" : "รางวัลนี้แลกได้เฉพาะผู้ที่เคยทำภารกิจร้าน \"" . e($currentPlace["name"]) . "\" เท่านั้น" ?>
        </p>
        <form method="post" enctype="multipart/form-data" class="adm-form">
          <input type="hidden" name="action" value="add_reward">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="place_id" value="<?= $scope === "global" ? "" : $currentPlace["id"] ?>">
          <input name="reward_name" placeholder="ชื่อรางวัล" required>
          <input name="reward_desc" placeholder="คำอธิบาย">
          <label style="font-size:13px;color:#374151;margin-bottom:4px">รูปภาพรางวัล</label>
          <input type="file" name="reward_image" accept="image/*" style="background:#f8fafc;border:1.5px dashed #bfdbfe;padding:10px">
          <div class="adm-row">
            <input name="cost_points" type="number" placeholder="คะแนนที่ใช้แลก" required>
            <input name="stock" type="number" placeholder="จำนวนชิ้น" value="10">
          </div>
          <button class="adm-btn primary" type="submit">เพิ่มรางวัล</button>
        </form>
      </div>

      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="reward-search" placeholder="ค้นหาชื่อรางวัล" oninput="filterRewards(this.value)">
      </div>

      <div class="pg-card" id="rewards-list">
        <?php if (empty($rewards)): ?>
          <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px">ยังไม่มีรางวัลในหมวดนี้ กด "+ เพิ่มรางวัล" เพื่อเริ่มต้น</div>
        <?php endif; ?>
        <?php foreach ($rewards as $rw): ?>
          <div class="row-item" data-search="<?= e(mb_strtolower($rw["name"], "UTF-8")) ?>">
            <?php if ($rw["image_url"]): ?>
              <img src="<?= e($rw["image_url"]) ?>" alt="">
            <?php else: ?>
              <div class="ph"></div>
            <?php endif; ?>
            <div class="row-info">
              <strong><?= e($rw["name"]) ?></strong>
              <span><?= number_format($rw["cost_points"]) ?> คะแนน · เหลือ <?= $rw["stock"] ?> ชิ้น</span>
            </div>
            <form method="post">
              <input type="hidden" name="action" value="delete_reward">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="reward_id" value="<?= $rw["id"] ?>">
              <button type="submit" class="icon-btn" title="ลบ" onclick="return confirm('ลบรางวัลนี้?')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
              </button>
            </form>
          </div>
        <?php endforeach; ?>
        <div id="rewards-empty" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบรางวัลที่ค้นหา</div>
      </div>
    <?php endif; ?>

  </main>
  <script>
  function revealAndScroll(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function filterRewards(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#rewards-list .row-item').forEach(row => {
      const match = !q || row.dataset.search.includes(q);
      row.style.display = match ? 'flex' : 'none';
      if (match) visible++;
    });
    const emptyEl = document.getElementById('rewards-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
  }

  function filterShops(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#shops-list .shop-card').forEach(card => {
      const match = !q || card.dataset.search.includes(q);
      card.style.display = match ? 'flex' : 'none';
      if (match) visible++;
    });
    const emptyEl = document.getElementById('shops-empty');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';
  }
  <?php if ($autoOpen === "add"): ?>
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-reward-form'));
  <?php endif; ?>
  </script>
</body>
</html>
