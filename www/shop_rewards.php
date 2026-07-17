<?php
require_once "_shop.php";

$placeId = intval($shopPlace["id"]);

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_rewards.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_reward") {
        $name  = trim($_POST["reward_name"] ?? "");
        $desc  = trim($_POST["reward_desc"] ?? "");
        $cost  = intval($_POST["cost_points"] ?? 0);
        $stock = intval($_POST["stock"] ?? 1);
        $img   = "";

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
        $stmt = $conn->prepare("DELETE FROM rewards WHERE id=? AND place_id=?");
        $stmt->bind_param("ii", $rid, $placeId);
        $stmt->execute();
        $msg = "ลบรางวัลสำเร็จ";
    }
}

$rewards = $conn->query("SELECT * FROM rewards WHERE place_id=$placeId ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$autoOpen = $_GET["open"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการของรางวัล | ล่าพิกัด.com</title>
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
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

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
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="shop.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>จัดการของรางวัล</h1>
      <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-reward-form')">+ เพิ่มรางวัล</button>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div id="add-reward-form" class="pg-card" style="display:none;margin-bottom:14px">
      <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">เพิ่มรางวัลใหม่</h2>
      <p style="font-size:12.5px;color:#94a3b8;margin:2px 0 0">รางวัลนี้แลกได้เฉพาะผู้ที่เคยทำภารกิจร้านนี้เท่านั้น</p>
      <form method="post" enctype="multipart/form-data" class="adm-form">
        <input type="hidden" name="action" value="add_reward">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
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
    document.getElementById('rewards-empty').style.display = visible === 0 ? 'block' : 'none';
  }
  <?php if ($autoOpen === "add"): ?>
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-reward-form'));
  <?php endif; ?>
  </script>
</body>
</html>
