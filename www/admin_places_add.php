<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_places_add.php");
    $action = $_POST["action"] ?? "";

    if ($action === "add_place") {
        $name     = trim($_POST["place_name"] ?? "");
        $desc     = trim($_POST["place_description"] ?? "");
        $loc      = trim($_POST["location_text"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $imgUrl   = "";

        if (isset($_FILES["place_image"]) && $_FILES["place_image"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["place_image"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg","jpeg","png","webp"]) && $_FILES["place_image"]["size"] < 5*1024*1024) {
                $fname = "place_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $dest  = __DIR__ . "/assets/images/places/" . $fname;
                if (!is_dir(__DIR__ . "/assets/images/places/")) mkdir(__DIR__ . "/assets/images/places/", 0755, true);
                if (move_uploaded_file($_FILES["place_image"]["tmp_name"], $dest)) {
                    $imgUrl = "assets/images/places/" . $fname;
                }
            }
        }

        if ($name !== "") {
            $stmt = $conn->prepare("INSERT INTO places (name, description, location_text, category, image_url) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $name, $desc, $loc, $category, $imgUrl);
            $stmt->execute();
            $msg = "เพิ่มสถานที่สำเร็จ";
        } else { $msg = "กรุณากรอกชื่อสถานที่"; $msgType = "bad"; }
    }
}

$existingPlaces = $conn->query("SELECT id,name,category,location_text FROM places ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

$baseCategories = ["วัด/ศาสนา","ธรรมชาติ","ร้านค้า/ร้านอาหาร","แหล่งท่องเที่ยว","พิพิธภัณฑ์","ชุมชน","อื่นๆ"];
$usedCategories = array_column($conn->query("SELECT DISTINCT category FROM places WHERE category IS NOT NULL AND category != ''")->fetch_all(MYSQLI_ASSOC), "category");
$placeCategories = array_values(array_unique(array_merge($baseCategories, $usedCategories)));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>เพิ่มสถานที่ | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .pg-card { background:#fff;border-radius:20px;padding:18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:12px 20px;border:none;border-radius:12px;
      font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }

    .icon-btn {
      width:44px;height:44px;border-radius:12px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      border:none;cursor:pointer;background:#eff6ff;color:#2563eb;
    }
    .icon-btn svg { width:18px;height:18px; }

    .existing-title {
      font-size:14px;font-weight:600;color:#0f172a;margin:22px 0 4px;
      display:flex;align-items:center;justify-content:space-between;
    }
    .existing-title a { font-size:12.5px;font-weight:600;color:#2563eb;text-decoration:none; }

    .row-item { display:flex;align-items:center;gap:10px;padding:11px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }
    .row-item .dot { width:6px;height:6px;border-radius:50%;background:#16a34a;flex-shrink:0; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:13.5px;font-weight:600;color:#0f172a; }
    .row-info span   { font-size:11.5px;color:#94a3b8; }
    .added-tag { font-size:10.5px;font-weight:600;color:#16a34a;background:#f0fdf4;padding:3px 9px;border-radius:99px;white-space:nowrap; }
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
      <h1>เพิ่มสถานที่ใหม่</h1>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="pg-card">
      <form method="post" enctype="multipart/form-data" class="adm-form" style="margin-top:0">
        <input type="hidden" name="action" value="add_place">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input name="place_name" placeholder="ชื่อสถานที่" required>
        <input name="location_text" placeholder="อำเภอ / จังหวัด">
        <div style="display:flex;gap:6px">
          <select name="category" id="new-place-cat" style="flex:1;min-width:0">
            <option value="">-- เลือกหมวดหมู่ --</option>
            <?php foreach ($placeCategories as $cat): ?>
              <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="button" class="icon-btn" title="สร้างหมวดหมู่ใหม่" onclick="addNewCategory('new-place-cat')">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          </button>
        </div>
        <p style="font-size:11.5px;color:#94a3b8;margin:-4px 0 0">หมวด "ร้านค้า/ร้านอาหาร" ภารกิจจะรีเฟรชให้ทำซ้ำได้ทุกวัน ส่วนหมวดอื่นทำได้ครั้งเดียว</p>
        <textarea name="place_description" placeholder="รายละเอียดสถานที่"></textarea>
        <label style="font-size:13px;color:#374151">รูปภาพสถานที่</label>
        <input type="file" name="place_image" accept="image/*" style="background:#f8fafc;border:1.5px dashed #bfdbfe;padding:10px">
        <button class="adm-btn primary" type="submit">เพิ่มสถานที่</button>
      </form>
    </div>

    <div class="existing-title">
      <span>สถานที่ที่เพิ่มไปแล้ว (<?= count($existingPlaces) ?>)</span>
      <a href="admin_places.php">จัดการทั้งหมด ›</a>
    </div>
    <div class="pg-card">
      <?php if (empty($existingPlaces)): ?>
        <div class="row-item"><span style="font-size:13px;color:#94a3b8">ยังไม่มีสถานที่ในระบบ</span></div>
      <?php else: ?>
        <?php foreach ($existingPlaces as $ep): ?>
          <div class="row-item">
            <span class="dot"></span>
            <div class="row-info">
              <strong><?= e($ep["name"]) ?></strong>
              <span><?= $ep["category"] ? e($ep["category"]) : "ไม่มีหมวดหมู่" ?><?= $ep["location_text"] ? " · " . e($ep["location_text"]) : "" ?></span>
            </div>
            <span class="added-tag">เพิ่มแล้ว</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>
  <script>
  function addNewCategory(selectId) {
    const name = prompt('ชื่อหมวดหมู่ใหม่:');
    if (!name || !name.trim()) return;
    const select = document.getElementById(selectId);
    const opt = document.createElement('option');
    opt.value = name.trim();
    opt.textContent = name.trim();
    opt.selected = true;
    select.appendChild(opt);
  }
  </script>
</body>
</html>
