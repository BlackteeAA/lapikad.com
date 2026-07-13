<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_users.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_points") {
        $targetUserId = intval($_POST["user_id"] ?? 0);
        $points = intval($_POST["points"] ?? 0);
        $reason = trim($_POST["reason"] ?? "เพิ่มคะแนนโดยแอดมิน");
        if ($targetUserId > 0 && $points !== 0) {
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $points, $targetUserId);
            $stmt->execute();
            $adminId = intval($_SESSION["user_id"]);
            $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $targetUserId, $adminId, $points, $reason);
            $stmt->execute();
            $conn->commit();
            $msg = "เพิ่มคะแนนสำเร็จ";
        } else { $msg = "ข้อมูลไม่ถูกต้อง"; $msgType = "bad"; }
    }
}

$users = $conn->query("SELECT id,name,email,role,points,avatar_url FROM users ORDER BY points DESC")->fetch_all(MYSQLI_ASSOC);
$logs  = $conn->query("SELECT pl.*,u.name AS uname FROM point_logs pl JOIN users u ON u.id=pl.user_id ORDER BY pl.id DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
$autoOpen = $_GET["open"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการผู้ใช้ | ล่าพิกัด.com</title>
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

    .search-box { display:flex;align-items:center;gap:8px;background:#f8fafc;border-radius:12px;padding:10px 14px;margin-bottom:14px; }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }

    .row-item .avatar {
      width:44px;height:44px;border-radius:50%;overflow:hidden;
      background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;
      display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;
    }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { font-size:11.5px;color:#94a3b8; }

    .pts { text-align:right;font-size:10.5px;color:#64748b;white-space:nowrap; }
    .pts b { display:block;font-size:13px;color:#0f172a;margin-top:1px; }

    .pill-btn {
      font-size:11.5px;font-weight:600;background:#eff6ff;color:#2563eb;
      border:1.5px solid #bfdbfe;border-radius:99px;padding:7px 12px;cursor:pointer;
      font-family:inherit;white-space:nowrap;flex-shrink:0;
    }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
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
      <h1>เพิ่มคะแนนให้ผู้ใช้</h1>
      <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-points-form')">+ เพิ่มคะแนน</button>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="user-search" placeholder="ค้นหาชื่อผู้ใช้ / อีเมล" oninput="filterUsers(this.value)">
    </div>

    <div id="add-points-form" class="pg-card" style="display:none;margin-bottom:14px">
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="add_points">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <select name="user_id" required>
          <option value="">เลือกผู้ใช้</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u["id"] ?>"><?= e($u["name"]) ?> · <?= e($u["email"]) ?> · <?= $u["points"] ?> คะแนน</option>
          <?php endforeach; ?>
        </select>
        <div class="adm-row">
          <input name="points" type="number" placeholder="คะแนน เช่น 50 / -20" required>
          <input name="reason" placeholder="เหตุผล">
        </div>
        <button class="adm-btn primary" type="submit">บันทึกคะแนน</button>
      </form>
    </div>

    <div class="pg-card">
      <div id="users-list">
        <?php foreach ($users as $u): ?>
          <div class="row-item" data-search="<?= e(mb_strtolower($u["name"] . " " . $u["email"], "UTF-8")) ?>">
            <div class="avatar">
              <?php if (!empty($u["avatar_url"])): ?>
                <img src="<?= e($u["avatar_url"]) ?>" style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
                <?= e(mb_substr($u["name"], 0, 1, "UTF-8")) ?>
              <?php endif; ?>
            </div>
            <div class="row-info">
              <strong><?= e($u["name"]) ?></strong>
              <span><?= e($u["email"]) ?></span>
            </div>
            <div class="pts">
              คะแนนปัจจุบัน
              <b>★ <?= number_format($u["points"]) ?></b>
            </div>
            <button type="button" class="pill-btn" onclick="quickAddPoints(<?= $u["id"] ?>)">เพิ่มคะแนน</button>
            <form method="post" id="qform-<?= $u["id"] ?>" style="display:none">
              <input type="hidden" name="action" value="add_points">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
              <input type="hidden" name="reason" value="เพิ่มคะแนนด่วนโดยแอดมิน">
              <input type="hidden" name="points" id="qpoints-<?= $u["id"] ?>">
            </form>
          </div>
        <?php endforeach; ?>
      </div>
      <div id="users-empty" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบผู้ใช้ที่ค้นหา</div>
    </div>

    <?php if (!empty($logs)): ?>
    <div class="pg-head" style="margin-top:22px">
      <h1 style="font-size:16px">ประวัติคะแนนล่าสุด</h1>
    </div>
    <div class="pg-card">
      <?php foreach ($logs as $log): ?>
        <div class="row-item">
          <div class="row-info">
            <strong><?= e($log["uname"]) ?></strong>
            <span><?= e($log["reason"]) ?></span>
          </div>
          <span style="font-size:14px;font-weight:700;color:<?= $log["points"] >= 0 ? '#16a34a' : '#e11d48' ?>">
            <?= $log["points"] >= 0 ? '+' : '' ?><?= $log["points"] ?>
          </span>
        </div>
      <?php endforeach; ?>
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

  function quickAddPoints(userId) {
    const amt = prompt('ต้องการเพิ่ม (หรือลด) กี่คะแนน?', '10');
    if (amt === null) return;
    const n = parseInt(amt, 10);
    if (!n) return;
    document.getElementById('qpoints-' + userId).value = n;
    document.getElementById('qform-' + userId).submit();
  }

  function filterUsers(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#users-list .row-item').forEach(row => {
      const match = !q || row.dataset.search.includes(q);
      row.style.display = match ? 'flex' : 'none';
      if (match) visible++;
    });
    document.getElementById('users-empty').style.display = visible === 0 ? 'block' : 'none';
  }

  <?php if ($autoOpen === "add"): ?>
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-points-form'));
  <?php endif; ?>
  </script>
</body>
</html>
