<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_quests.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_quest") {
        $placeId = intval($_POST["place_id"] ?? 0);
        $title   = trim($_POST["title"] ?? "");
        $desc    = trim($_POST["description"] ?? "");
        $type    = $_POST["quest_type"] ?? "qr";
        $pts     = intval($_POST["reward_points"] ?? 10);
        $code    = "LAPIKAD-" . strtoupper(bin2hex(random_bytes(6)));
        if ($placeId > 0 && $title !== "") {
            $stmt = $conn->prepare("INSERT INTO quests (place_id,title,description,quest_type,reward_points,target_code) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssis", $placeId, $title, $desc, $type, $pts, $code);
            $stmt->execute();
            $msg = "เพิ่มภารกิจสำเร็จ";
        } else { $msg = "กรุณากรอกข้อมูลให้ครบ"; $msgType = "bad"; }
    }

    if ($action === "delete_quest") {
        $qid = intval($_POST["quest_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM quests WHERE id=?");
        $stmt->bind_param("i", $qid);
        $stmt->execute();
        $msg = "ลบภารกิจสำเร็จ";
    }

    if ($action === "update_quest") {
        $qid   = intval($_POST["quest_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $desc  = trim($_POST["description"] ?? "");
        $pts   = intval($_POST["reward_points"] ?? 0);
        if ($qid > 0 && $title !== "") {
            $stmt = $conn->prepare("UPDATE quests SET title=?, description=?, reward_points=? WHERE id=?");
            $stmt->bind_param("ssii", $title, $desc, $pts, $qid);
            $stmt->execute();
            $msg = "แก้ไขภารกิจสำเร็จ";
        }
    }
}

$placeId = intval($_GET["place_id"] ?? 0);

$placesWithCounts = $conn->query("
    SELECT p.id, p.name, p.image_url, p.category, p.district, p.province,
           COUNT(q.id) AS quest_count
    FROM places p
    LEFT JOIN quests q ON q.place_id = p.id
    GROUP BY p.id
    ORDER BY p.name
")->fetch_all(MYSQLI_ASSOC);

$currentPlace = null;
if ($placeId > 0) {
    foreach ($placesWithCounts as $p) {
        if ((int)$p["id"] === $placeId) { $currentPlace = $p; break; }
    }
    if (!$currentPlace) $placeId = 0;
}

$quests = [];
$photos = [];
if ($currentPlace) {
    $stmt = $conn->prepare("SELECT q.*,p.name AS place_name FROM quests q JOIN places p ON p.id=q.place_id WHERE q.place_id=? ORDER BY q.id DESC");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $quests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $stmtPh = $conn->prepare("
        SELECT uq.photo_url, uq.completed_at, u.name AS uname, q.title AS qtitle
        FROM user_quests uq
        JOIN users u ON u.id = uq.user_id
        JOIN quests q ON q.id = uq.quest_id
        WHERE uq.photo_url IS NOT NULL AND q.place_id = ?
        ORDER BY uq.completed_at DESC
        LIMIT 9
    ");
    $stmtPh->bind_param("i", $placeId);
    $stmtPh->execute();
    $photos = $stmtPh->get_result()->fetch_all(MYSQLI_ASSOC);
}
$autoOpen = $_GET["open"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการภารกิจ | ล่าพิกัด.com</title>
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
    .pg-head p.sub { font-size:12.5px;color:#94a3b8;margin:2px 0 0; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:11px 18px;border:none;border-radius:999px;
      font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:opacity .2s;flex-shrink:0;
      text-decoration:none;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn.danger  { background:#fff1f2;color:#e11d48; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn.sm      { padding:7px 14px;font-size:12.5px;border-radius:999px; }

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
    .shop-quest-count {
      font-size:11px;font-weight:600;color:#2563eb;background:#eff6ff;
      padding:3px 10px;border-radius:99px;
    }
    .shop-quest-count.zero { color:#94a3b8;background:#f1f5f9; }

    .row-item { display:flex;align-items:center;gap:10px;padding:14px 0;border-bottom:1px solid #f1f5f9;flex-wrap:wrap; }
    .quest-group:last-child .row-item { border-bottom:none; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { font-size:11.5px;color:#94a3b8; }

    .type-badge { font-size:11px;font-weight:600;padding:3px 8px;border-radius:99px;white-space:nowrap; }
    .type-qr      { background:#dbeafe;color:#1d4ed8; }
    .type-checkin { background:#dcfce7;color:#15803d; }
    .type-quiz    { background:#ede9fe;color:#6d28d9; }

    .edit-panel { padding:0 0 14px;border-bottom:1px solid #f1f5f9;width:100%; }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .btn-outline {
      display:flex;align-items:center;justify-content:center;gap:6px;width:100%;
      padding:13px;border-radius:999px;border:1.5px solid #bfdbfe;
      background:#fff;color:#2563eb;font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;margin:12px 0;
      text-decoration:none;
    }

    .shop-context { display:flex;align-items:center;gap:10px;background:#eff6ff;border-radius:12px;padding:10px 12px;margin-bottom:2px; }
    .shop-context img, .shop-context .ph { width:36px;height:36px;border-radius:10px;object-fit:cover;background:#dbeafe;flex-shrink:0; }
    .shop-context strong { font-size:13px;color:#1d4ed8; }

    #shops-empty { display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <?php if ($currentPlace): ?>
      <a href="admin_quests.php" class="back-btn">
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

    <?php if (!$currentPlace): ?>
      <!-- ===== SHOP GRID VIEW ===== -->
      <div class="pg-head">
        <h1>ภารกิจ / QR Code</h1>
      </div>
      <p class="sub" style="margin:-10px 0 14px;font-size:12.5px;color:#94a3b8">เลือกร้าน/สถานที่เพื่อจัดการภารกิจและ QR Code</p>

      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="shop-search" placeholder="ค้นหาชื่อร้าน / หมวดหมู่" oninput="filterShops(this.value)">
      </div>

      <?php if (empty($placesWithCounts)): ?>
        <div class="pg-card" style="padding:24px;text-align:center">
          <p style="color:#94a3b8;font-size:13px;margin:0 0 10px">ยังไม่มีร้าน/สถานที่ในระบบ</p>
          <a href="admin_places.php" class="adm-btn primary sm">+ เพิ่มสถานที่</a>
        </div>
      <?php else: ?>
        <div class="shop-grid" id="shops-list">
          <?php foreach ($placesWithCounts as $p):
            $locParts = array_filter([$p["district"] ? "อ." . $p["district"] : null, $p["province"] ? "จ." . $p["province"] : null]);
            $locText = implode(" ", $locParts);
          ?>
            <a href="admin_quests.php?place_id=<?= $p["id"] ?>" class="shop-card"
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
              <span class="shop-quest-count <?= $p["quest_count"] == 0 ? 'zero' : '' ?>"><?= $p["quest_count"] ?> ภารกิจ</span>
            </a>
          <?php endforeach; ?>
        </div>
        <div id="shops-empty">ไม่พบร้าน/สถานที่ที่ค้นหา</div>
      <?php endif; ?>

    <?php else: ?>
      <!-- ===== QUEST LIST VIEW (scoped to one shop) ===== -->
      <div class="pg-head">
        <h1>ภารกิจ / QR Code</h1>
        <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-quest-form')">+ สร้างภารกิจ</button>
      </div>

      <div class="shop-context" style="margin-bottom:14px">
        <?php if ($currentPlace["image_url"]): ?>
          <img src="<?= e($currentPlace["image_url"]) ?>" alt="">
        <?php else: ?>
          <div class="ph"></div>
        <?php endif; ?>
        <strong><?= e($currentPlace["name"]) ?></strong>
      </div>

      <div id="add-quest-form" class="pg-card" style="display:none;margin-bottom:14px">
        <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">สร้างภารกิจใหม่</h2>
        <p style="font-size:12.5px;color:#94a3b8;margin:2px 0 0">สำหรับร้าน "<?= e($currentPlace["name"]) ?>" · ระบบสุ่มรหัส QR ให้อัตโนมัติ</p>
        <form method="post" class="adm-form">
          <input type="hidden" name="action" value="add_quest">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="place_id" value="<?= $currentPlace["id"] ?>">
          <input name="title" placeholder="ชื่อภารกิจ" required>
          <textarea name="description" placeholder="รายละเอียดภารกิจ"></textarea>
          <div class="adm-row">
            <select name="quest_type">
              <option value="qr">QR Code</option>
              <option value="checkin">Check-in</option>
              <option value="quiz">Quiz</option>
            </select>
            <input name="reward_points" type="number" value="10" placeholder="คะแนนรางวัล">
          </div>
          <button class="adm-btn primary" type="submit">สร้างภารกิจ</button>
        </form>
      </div>

      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="quest-search" placeholder="ค้นหาชื่อภารกิจ / ประเภท" oninput="filterQuests(this.value)">
      </div>

      <div class="pg-card" id="quests-list">
        <?php if (empty($quests)): ?>
          <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px">ร้านนี้ยังไม่มีภารกิจ กด "+ สร้างภารกิจ" เพื่อเริ่มต้น</div>
        <?php endif; ?>
        <?php foreach ($quests as $q): ?>
        <div class="quest-group" data-search="<?= e(mb_strtolower($q["title"] . " " . $q["quest_type"], "UTF-8")) ?>">
          <div class="row-item">
            <div class="row-info">
              <strong><?= e($q["title"]) ?></strong>
              <span>+<?= $q["reward_points"] ?> คะแนน</span>
            </div>
            <span class="type-badge type-<?= e($q["quest_type"]) ?>"><?= e($q["quest_type"]) ?></span>
            <?php if ($q["quest_type"] === "qr"): ?>
              <a href="qr_view.php?id=<?= $q["id"] ?>" class="adm-btn sm" style="background:#eff6ff;color:#2563eb">QR</a>
            <?php endif; ?>
            <button class="adm-btn sm" style="background:#f8fafc;color:#374151" onclick="toggleEdit('q<?= $q["id"] ?>')">แก้ไข</button>
            <form method="post" style="flex-shrink:0">
              <input type="hidden" name="action" value="delete_quest">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="quest_id" value="<?= $q["id"] ?>">
              <button class="adm-btn danger sm" type="submit" onclick="return confirm('ลบภารกิจ: <?= e($q["title"]) ?>?')">ลบ</button>
            </form>
          </div>
          <div id="q<?= $q["id"] ?>" class="edit-panel" style="display:none">
            <form method="post" style="display:grid;gap:8px">
              <input type="hidden" name="action" value="update_quest">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="quest_id" value="<?= $q["id"] ?>">
              <input name="title" value="<?= e($q["title"]) ?>" placeholder="ชื่อภารกิจ" style="font-size:13px">
              <textarea name="description" style="font-size:13px;min-height:60px"><?= e($q["description"] ?? '') ?></textarea>
              <input name="reward_points" type="number" value="<?= e($q["reward_points"]) ?>" placeholder="คะแนน" style="font-size:13px">
              <button class="adm-btn primary sm" type="submit">บันทึก</button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
        <div id="quests-empty" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบภารกิจที่ค้นหา</div>
      </div>

      <?php if (!empty($photos)): ?>
      <div class="pg-head" style="margin-top:22px">
        <h1 style="font-size:16px">รูปเช็คอินล่าสุด</h1>
      </div>
      <div class="pg-card" style="padding:16px">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
          <?php foreach ($photos as $ph): ?>
            <div style="position:relative">
              <img src="<?= e($ph['photo_url']) ?>" style="width:100%;aspect-ratio:1;object-fit:cover;border-radius:12px;display:block">
              <div style="position:absolute;bottom:0;left:0;right:0;background:linear-gradient(transparent,rgba(0,0,0,.7));border-radius:0 0 12px 12px;padding:6px 8px">
                <div style="font-size:11px;color:#fff;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($ph['uname']) ?></div>
                <div style="font-size:10px;color:rgba(255,255,255,.75)"><?= e($ph['qtitle']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>

  </main>
  <script>
  function toggleEdit(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  function revealAndScroll(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function filterQuests(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#quests-list .quest-group').forEach(group => {
      const match = !q || group.dataset.search.includes(q);
      group.style.display = match ? 'block' : 'none';
      if (match) visible++;
    });
    const emptyEl = document.getElementById('quests-empty');
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
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-quest-form'));
  <?php endif; ?>
  </script>
</body>
</html>
