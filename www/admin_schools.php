<?php
// Admin: create / rename / delete schools. Members and roles are managed per
// school in school_members.php.
require_once "_admin.php";
require_once "_school.php";

$msg = "";
$msgType = "good";

if (uploadPostTooLarge()) {
    $msg = uploadTooLargeMessage(); $msgType = "bad";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_schools.php");
    $action   = $_POST["action"] ?? "";
    $name     = trim($_POST["name"] ?? "");
    $district = trim($_POST["district"] ?? "");
    $province = trim($_POST["province"] ?? "");

    if ($action === "add_school" || $action === "edit_school") {
        if ($name === "") {
            $msg = "กรุณากรอกชื่อโรงเรียน"; $msgType = "bad";
        } else {
            try {
                if ($action === "add_school") {
                    $stmt = $conn->prepare("INSERT INTO schools (name, district, province) VALUES (?, ?, ?)");
                    $stmt->bind_param("sss", $name, $district, $province);
                } else {
                    $sid = intval($_POST["school_id"] ?? 0);
                    $stmt = $conn->prepare("UPDATE schools SET name=?, district=?, province=? WHERE id=?");
                    $stmt->bind_param("sssi", $name, $district, $province, $sid);
                }
                $stmt->execute();
                if ($action === "add_school") $sid = $conn->insert_id;
                $msg = $action === "add_school" ? "เพิ่มโรงเรียนสำเร็จ" : "บันทึกการแก้ไขสำเร็จ";

                if ($action === "edit_school" && !empty($_POST["remove_logo"])) {
                    removeSchoolLogo($conn, $sid);
                }
                $logoErr = saveSchoolLogo($conn, $sid, $_FILES["logo"] ?? null);
                if ($logoErr) { $msg .= " แต่ " . $logoErr; $msgType = "bad"; }
            } catch (mysqli_sql_exception $e) {
                $msg = "มีโรงเรียนชื่อนี้อยู่แล้ว"; $msgType = "bad";
            }
        }
    }

    if ($action === "delete_school") {
        $sid = intval($_POST["school_id"] ?? 0);
        deleteSchoolLogoFile(getSchool($conn, $sid)["logo_url"] ?? "");
        $stmt = $conn->prepare("DELETE FROM schools WHERE id=?");
        $stmt->bind_param("i", $sid);
        $stmt->execute();
        // FK ON DELETE SET NULL clears users.school_id; also clear the now-orphaned role.
        $conn->query("UPDATE users SET school_role=NULL WHERE school_id IS NULL AND school_role IS NOT NULL");
        $msg = "ลบโรงเรียนแล้ว";
    }
}

$schools = $conn->query("
    SELECT s.*,
           SUM(u.school_role = 'student') AS n_student,
           SUM(u.school_role = 'council') AS n_council,
           SUM(u.school_role = 'teacher') AS n_teacher,
           (SELECT COUNT(*) FROM events e WHERE e.school_id = s.id) AS n_events
    FROM schools s
    LEFT JOIN users u ON u.school_id = s.id
    GROUP BY s.id
    ORDER BY s.name
")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการโรงเรียน | ล่าพิกัด.com</title>
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
      font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:opacity .2s;flex-shrink:0;text-decoration:none;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn.ghost   { background:#f1f5f9;color:#475569; }
    .adm-btn:hover   { opacity:.85; }
    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }
    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { display:block;font-size:11.5px;color:#94a3b8; }
    .chips { display:flex;flex-wrap:wrap;gap:6px;margin-top:6px; }
    .chip { font-size:11px;font-weight:600;padding:3px 9px;border-radius:99px; }
    .chip.teacher { background:#fce7f3;color:#be185d; }
    .chip.council { background:#e0e7ff;color:#4338ca; }
    .chip.student { background:#f1f5f9;color:#475569; }
    .pill-btn {
      font-size:11.5px;font-weight:600;background:#eff6ff;color:#2563eb;
      border:1.5px solid #bfdbfe;border-radius:99px;padding:7px 12px;cursor:pointer;
      font-family:inherit;white-space:nowrap;flex-shrink:0;text-decoration:none;
    }
    .icon-btn {
      width:28px;height:28px;border-radius:8px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      border:none;cursor:pointer;background:#fff1f2;color:#e11d48;
    }
    .icon-btn svg { width:14px;height:14px; }
    .icon-btn.edit { background:#eff6ff;color:#2563eb; }
    .adm-form { display:grid;gap:10px;margin:14px 0; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
    .logo-label { font-size:13px;color:#374151; }
    .logo-input { background:#f8fafc;border:1.5px dashed #bfdbfe;padding:10px;border-radius:12px; }
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
      <h1>โรงเรียน</h1>
      <button type="button" class="adm-btn primary" onclick="toggle('add-form')">+ เพิ่มโรงเรียน</button>
    </div>
    <p style="margin:-10px 0 14px;font-size:12.5px;color:#94a3b8">แต่ละโรงเรียนมีนักเรียน สภานักเรียน และครูของตัวเอง กิจกรรมและรางวัลโรงเรียนแยกกันตามโรงเรียน</p>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div id="add-form" class="pg-card" style="display:<?= ($msgType === "bad" && ($_POST["action"] ?? "") === "add_school") ? "block" : "none" ?>;margin-bottom:14px">
      <form method="post" class="adm-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_school">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input name="name" placeholder="ชื่อโรงเรียน" maxlength="150" required>
        <div class="adm-row">
          <input name="district" placeholder="อำเภอ" maxlength="100">
          <input name="province" placeholder="จังหวัด" maxlength="100">
        </div>
        <label class="logo-label">โลโก้โรงเรียน (ไม่บังคับ · รูปไม่เกิน 5MB)</label>
        <input type="file" name="logo" accept="image/*" class="logo-input">
        <button class="adm-btn primary" type="submit">เพิ่มโรงเรียน</button>
      </form>
    </div>

    <div class="pg-card">
      <?php if (!$schools): ?>
        <div style="text-align:center;padding:24px;color:#94a3b8;font-size:13px">ยังไม่มีโรงเรียน กด "+ เพิ่มโรงเรียน" เพื่อเริ่มต้น</div>
      <?php endif; ?>
      <?php foreach ($schools as $sc): ?>
        <div class="row-item">
          <?= schoolLogoHtml($sc, 44) ?>
          <div class="row-info">
            <strong><?= e($sc["name"]) ?></strong>
            <?php if (schoolPlaceText($sc) !== ""): ?><span><?= e(schoolPlaceText($sc)) ?></span><?php endif; ?>
            <div class="chips">
              <span class="chip teacher">ครู <?= intval($sc["n_teacher"]) ?></span>
              <span class="chip council">สภา <?= intval($sc["n_council"]) ?></span>
              <span class="chip student">นักเรียน <?= intval($sc["n_student"]) ?></span>
              <span class="chip student">กิจกรรม <?= intval($sc["n_events"]) ?></span>
            </div>
          </div>
          <a class="pill-btn" href="school_members.php?id=<?= $sc["id"] ?>">สมาชิก</a>
          <a class="pill-btn" href="council_rewards.php?school_id=<?= $sc["id"] ?>">รางวัล</a>
          <button type="button" class="icon-btn edit" title="แก้ไข" onclick="toggle('edit-<?= $sc["id"] ?>')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
          </button>
          <form method="post">
            <input type="hidden" name="action" value="delete_school">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="school_id" value="<?= $sc["id"] ?>">
            <button type="submit" class="icon-btn" title="ลบ"
                    onclick="return confirm('ลบโรงเรียน <?= e(addslashes($sc["name"])) ?>?\nกิจกรรมและรางวัลของโรงเรียนนี้จะถูกลบด้วย และสมาชิกทุกคนจะไม่มีโรงเรียน')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
            </button>
          </form>
        </div>
        <div id="edit-<?= $sc["id"] ?>" style="display:none">
          <form method="post" class="adm-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit_school">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="school_id" value="<?= $sc["id"] ?>">
            <input name="name" value="<?= e($sc["name"]) ?>" placeholder="ชื่อโรงเรียน" maxlength="150" required>
            <div class="adm-row">
              <input name="district" value="<?= e($sc["district"]) ?>" placeholder="อำเภอ" maxlength="100">
              <input name="province" value="<?= e($sc["province"]) ?>" placeholder="จังหวัด" maxlength="100">
            </div>
            <label class="logo-label">โลโก้โรงเรียน<?= !empty($sc["logo_url"]) ? " (เลือกไฟล์ใหม่เพื่อเปลี่ยน)" : "" ?></label>
            <input type="file" name="logo" accept="image/*" class="logo-input">
            <?php if (!empty($sc["logo_url"])): ?>
              <label class="logo-label" style="display:flex;align-items:center;gap:6px"><input type="checkbox" name="remove_logo" value="1"> ลบโลโก้</label>
            <?php endif; ?>
            <div style="display:flex;gap:8px">
              <button class="adm-btn primary" type="submit">บันทึก</button>
              <button type="button" class="adm-btn ghost" onclick="toggle('edit-<?= $sc["id"] ?>')">ยกเลิก</button>
            </div>
          </form>
        </div>
      <?php endforeach; ?>
    </div>
  </main>
  <script>
  function toggle(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  </script>
</body>
</html>
