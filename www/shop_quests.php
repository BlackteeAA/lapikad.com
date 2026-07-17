<?php
require_once "_shop.php";

$placeId = intval($shopPlace["id"]);

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_quests.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_quest") {
        $title = trim($_POST["title"] ?? "");
        $desc  = trim($_POST["description"] ?? "");
        $type  = $_POST["quest_type"] ?? "qr";
        $pts   = intval($_POST["reward_points"] ?? 10);
        $code  = "LAPIKAD-" . strtoupper(bin2hex(random_bytes(6)));

        if ($title === "") {
            $msg = "กรุณากรอกชื่อภารกิจ"; $msgType = "bad";
        } elseif ($pts < 1 || $pts > 100) {
            $msg = "คะแนนรางวัลต้องไม่เกิน 100 แต้ม"; $msgType = "bad";
        } else {
            $stmt = $conn->prepare("INSERT INTO quests (place_id,title,description,quest_type,reward_points,target_code) VALUES (?,?,?,?,?,?)");
            $stmt->bind_param("isssis", $placeId, $title, $desc, $type, $pts, $code);
            $stmt->execute();
            $msg = "เพิ่มภารกิจสำเร็จ";
        }
    }

    if ($action === "delete_quest") {
        $qid = intval($_POST["quest_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM quests WHERE id=? AND place_id=?");
        $stmt->bind_param("ii", $qid, $placeId);
        $stmt->execute();
        $msg = "ลบภารกิจสำเร็จ";
    }

    if ($action === "update_quest") {
        $qid   = intval($_POST["quest_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $desc  = trim($_POST["description"] ?? "");
        $pts   = intval($_POST["reward_points"] ?? 0);

        if ($qid <= 0 || $title === "") {
            $msg = "กรุณากรอกข้อมูลให้ครบ"; $msgType = "bad";
        } elseif ($pts < 1 || $pts > 100) {
            $msg = "คะแนนรางวัลต้องไม่เกิน 100 แต้ม"; $msgType = "bad";
        } else {
            $stmt = $conn->prepare("UPDATE quests SET title=?, description=?, reward_points=? WHERE id=? AND place_id=?");
            $stmt->bind_param("ssiii", $title, $desc, $pts, $qid, $placeId);
            $stmt->execute();
            $msg = "แก้ไขภารกิจสำเร็จ";
        }
    }
}

$quests = $conn->query("SELECT * FROM quests WHERE place_id=$placeId ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$photos = $conn->query("
    SELECT uq.photo_url, uq.completed_at, u.name AS uname, q.title AS qtitle
    FROM user_quests uq
    JOIN users u ON u.id = uq.user_id
    JOIN quests q ON q.id = uq.quest_id
    WHERE uq.photo_url IS NOT NULL AND q.place_id=$placeId
    ORDER BY uq.completed_at DESC
    LIMIT 9
")->fetch_all(MYSQLI_ASSOC);
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

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:11px 18px;border:none;border-radius:999px;
      font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:opacity .2s;flex-shrink:0;
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
      <h1>ภารกิจ / QR Code</h1>
      <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-quest-form')">+ สร้างภารกิจ</button>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div id="add-quest-form" class="pg-card" style="display:none;margin-bottom:14px">
      <h2 style="font-size:15px;font-weight:700;color:#0f172a;margin:0">สร้างภารกิจใหม่</h2>
      <p style="font-size:12.5px;color:#94a3b8;margin:2px 0 0">ระบบสุ่มรหัส QR ให้อัตโนมัติ · คะแนนรางวัลสูงสุด 100 แต้ม</p>
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="add_quest">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input name="title" placeholder="ชื่อภารกิจ" required>
        <textarea name="description" placeholder="รายละเอียดภารกิจ"></textarea>
        <div class="adm-row">
          <select name="quest_type">
            <option value="qr">QR Code</option>
            <option value="checkin">Check-in</option>
            <option value="quiz">Quiz</option>
          </select>
          <input name="reward_points" type="number" value="10" min="1" max="100" placeholder="คะแนนรางวัล (สูงสุด 100)">
        </div>
        <button class="adm-btn primary" type="submit">สร้างภารกิจ</button>
      </form>
    </div>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="quest-search" placeholder="ค้นหาชื่อภารกิจ / ประเภท" oninput="filterQuests(this.value)">
    </div>

    <div class="pg-card" id="quests-list">
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
            <input name="reward_points" type="number" value="<?= e($q["reward_points"]) ?>" min="1" max="100" placeholder="คะแนน" style="font-size:13px">
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
    document.getElementById('quests-empty').style.display = visible === 0 ? 'block' : 'none';
  }
  <?php if ($autoOpen === "add"): ?>
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-quest-form'));
  <?php endif; ?>
  </script>
</body>
</html>
