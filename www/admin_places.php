<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_places.php");
    $action = $_POST["action"] ?? "";

    if ($action === "update_place_image") {
        $pid    = intval($_POST["place_id"] ?? 0);
        $imgUrl = "";
        if (isset($_FILES["place_image"]) && $_FILES["place_image"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["place_image"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg","jpeg","png","webp"]) && $_FILES["place_image"]["size"] < 5*1024*1024) {
                $fname = "place_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $dest  = __DIR__ . "/assets/images/places/" . $fname;
                if (!is_dir(__DIR__ . "/assets/images/places/")) mkdir(__DIR__ . "/assets/images/places/", 0755, true);
                if (move_uploaded_file($_FILES["place_image"]["tmp_name"], $dest)) {
                    $imgUrl = "assets/images/places/" . $fname;
                    $stmt = $conn->prepare("UPDATE places SET image_url=? WHERE id=?");
                    $stmt->bind_param("si", $imgUrl, $pid);
                    $stmt->execute();
                    $msg = "อัพเดทรูปสำเร็จ";
                }
            }
        }
        $newCat = trim($_POST["category"] ?? "");
        if ($newCat !== "") {
            $stmt = $conn->prepare("UPDATE places SET category=? WHERE id=?");
            $stmt->bind_param("si", $newCat, $pid);
            $stmt->execute();
            $msg = "อัพเดทสถานที่สำเร็จ";
        }
    }

    if ($action === "update_place") {
        $pid  = intval($_POST["place_id"] ?? 0);
        $name = trim($_POST["place_name"] ?? "");
        $loc  = trim($_POST["location_text"] ?? "");
        $desc = trim($_POST["description"] ?? "");
        $lat  = $_POST["lat"] !== "" ? floatval($_POST["lat"]) : null;
        $lng  = $_POST["lng"] !== "" ? floatval($_POST["lng"]) : null;
        $dist = trim($_POST["district"] ?? "");
        $prov = trim($_POST["province"] ?? "");
        if ($pid > 0 && $name !== "") {
            $stmt = $conn->prepare("UPDATE places SET name=?, location_text=?, description=?, lat=?, lng=?, district=?, province=? WHERE id=?");
            $stmt->bind_param("sssddssi", $name, $loc, $desc, $lat, $lng, $dist, $prov, $pid);
            $stmt->execute();
            $msg = "แก้ไขสถานที่สำเร็จ";
        }
    }

    if ($action === "delete_place") {
        $pid = intval($_POST["place_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM places WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $msg = "ลบสถานที่สำเร็จ";
    }

    if ($action === "toggle_place_active") {
        $pid = intval($_POST["place_id"] ?? 0);
        $stmt = $conn->prepare("UPDATE places SET is_active = 1 - is_active WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $msg = "อัพเดทสถานะสถานที่สำเร็จ";
    }
}

$allPlaces = $conn->query("SELECT * FROM places ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);

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
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>จัดการสถานที่ | ล่าพิกัด.com</title>
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
    .pg-head .count { font-size:13px;color:#94a3b8;font-weight:500; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .place-group:last-child .row-item { border-bottom:none; }

    .row-item img, .row-item .ph { width:52px;height:52px;border-radius:12px;object-fit:cover;flex-shrink:0;background:#e2e8f0; }

    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info .sub { font-size:11.5px;color:#94a3b8;margin-top:2px;display:flex;align-items:center;gap:3px; }
    .row-info .sub svg { width:11px;height:11px;flex-shrink:0; }
    .row-info .cat { font-size:11.5px;color:#2563eb;margin-top:2px; }

    .row-side { display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0; }

    .active-pill { font-size:10.5px;font-weight:600;padding:4px 10px;border-radius:99px;border:none;cursor:pointer;font-family:inherit;white-space:nowrap; }
    .active-pill.on  { background:#dcfce7;color:#16a34a; }
    .active-pill.off { background:#f1f5f9;color:#94a3b8; }

    .icon-btns { display:flex;gap:6px; }
    .icon-btn { width:28px;height:28px;border-radius:8px;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;text-decoration:none; }
    .icon-btn svg { width:14px;height:14px; }
    .icon-btn.edit, .icon-btn.qr { background:#eff6ff;color:#2563eb; }
    .icon-btn.del { background:#fff1f2;color:#e11d48; }

    .edit-panel { padding:14px 0;border-bottom:1px solid #f1f5f9; }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:12px 20px;border:none;border-radius:12px;text-decoration:none;
      font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn.sm      { padding:7px 14px;font-size:13px; }
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
      <h1>จัดการสถานที่</h1>
      <a href="admin_places_add.php" class="adm-btn primary" style="padding:9px 16px;font-size:13px">+ เพิ่มสถานที่</a>
    </div>
    <div style="text-align:right;margin:-10px 0 14px">
      <span class="count" style="font-size:12.5px;color:#94a3b8"><?= count($allPlaces) ?> แห่งทั้งหมด</span>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="place-search" placeholder="ค้นหาชื่อสถานที่ / หมวดหมู่ / พื้นที่" oninput="filterPlaces(this.value)">
    </div>

    <div class="pg-card" id="places-list">
      <?php foreach ($allPlaces as $pl): $rowId = "pl" . $pl["id"]; ?>
      <div class="place-group" data-search="<?= e(mb_strtolower($pl["name"] . " " . ($pl["category"] ?? "") . " " . ($pl["location_text"] ?? "") . " " . ($pl["district"] ?? "") . " " . ($pl["province"] ?? ""), "UTF-8")) ?>">
        <div class="row-item">
          <?php if ($pl["image_url"]): ?>
            <img src="<?= e($pl["image_url"]) ?>" alt="">
          <?php else: ?>
            <div class="ph"></div>
          <?php endif; ?>
          <div class="row-info">
            <strong><?= e($pl["name"]) ?></strong>
            <div class="sub">
              <svg viewBox="0 0 24 24" fill="#94a3b8"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
              <?= ($pl["lat"] !== null && $pl["lng"] !== null) ? number_format((float)$pl["lat"], 6) . ", " . number_format((float)$pl["lng"], 6) : "ไม่ระบุพิกัด" ?>
            </div>
            <div class="cat">หมวดหมู่: <?= $pl["category"] ? e($pl["category"]) : "ไม่มีหมวดหมู่" ?></div>
          </div>
          <div class="row-side">
            <form method="post">
              <input type="hidden" name="action" value="toggle_place_active">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
              <button type="submit" class="active-pill <?= !empty($pl["is_active"]) ? 'on' : 'off' ?>">
                <?= !empty($pl["is_active"]) ? "เปิดใช้งาน" : "ปิดใช้งาน" ?>
              </button>
            </form>
            <div class="icon-btns">
              <button type="button" class="icon-btn edit" title="แก้ไข" onclick="toggleEdit('<?= $rowId ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
              </button>
              <a href="admin_quests.php" class="icon-btn qr" title="QR Code ของสถานที่นี้">
                <svg viewBox="0 0 24 24" fill="currentColor"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="3" height="3" rx="0.5"/><rect x="18" y="14" width="3" height="3" rx="0.5"/><rect x="14" y="18" width="3" height="3" rx="0.5"/><rect x="18" y="18" width="3" height="3" rx="0.5"/></svg>
              </a>
              <form method="post">
                <input type="hidden" name="action" value="delete_place">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
                <button type="submit" class="icon-btn del" title="ลบ"
                        onclick="return confirm('ลบสถานที่: <?= e($pl["name"]) ?>?\n(จะลบภารกิจทั้งหมดในสถานที่นี้ด้วย)')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 7h12M9 7V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v3M7 7l1 13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1l1-13"/></svg>
                </button>
              </form>
            </div>
          </div>
        </div>

        <div id="<?= $rowId ?>" class="edit-panel" style="display:none">
          <form method="post" class="adm-form" style="margin-top:0">
            <input type="hidden" name="action" value="update_place">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
            <input name="place_name" value="<?= e($pl["name"]) ?>" placeholder="ชื่อสถานที่" style="font-size:13px">
            <div class="adm-row">
              <input name="district" value="<?= e($pl["district"] ?? '') ?>" placeholder="อำเภอ" style="font-size:13px">
              <input name="province" value="<?= e($pl["province"] ?? '') ?>" placeholder="จังหวัด" style="font-size:13px">
            </div>
            <input name="location_text" value="<?= e($pl["location_text"] ?? '') ?>" placeholder="ที่อยู่ย่อ (เช่น อ.กุฉินารายณ์)" style="font-size:13px">
            <div class="adm-row">
              <input name="lat" type="number" step="any" value="<?= $pl["lat"] !== null ? $pl["lat"] : '' ?>" placeholder="ละติจูด เช่น 16.4322" style="font-size:13px">
              <input name="lng" type="number" step="any" value="<?= $pl["lng"] !== null ? $pl["lng"] : '' ?>" placeholder="ลองติจูด เช่น 104.062" style="font-size:13px">
            </div>
            <textarea name="description" placeholder="รายละเอียดสถานที่" style="font-size:13px;min-height:60px"><?= e($pl["description"] ?? '') ?></textarea>
            <p style="font-size:11px;color:#94a3b8;margin:0">หาพิกัดได้จาก Google Maps → คลิกขวาที่จุด → คัดลอกตัวเลข</p>
            <button class="adm-btn primary sm" type="submit">บันทึกข้อมูลสถานที่</button>
          </form>
          <form method="post" enctype="multipart/form-data" class="cat-form" style="display:grid;gap:8px;margin-top:10px">
            <input type="hidden" name="action" value="update_place_image">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
            <div style="display:flex;gap:6px">
              <select name="category" id="cat-select-<?= $pl["id"] ?>" style="flex:1;min-width:0;font-size:13px">
                <option value="">-- หมวดหมู่ --</option>
                <?php foreach ($placeCategories as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $pl["category"] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="button" class="icon-btn edit" title="สร้างหมวดหมู่ใหม่" onclick="addNewCategory('cat-select-<?= $pl["id"] ?>')" style="flex-shrink:0">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
              </button>
            </div>
            <input type="file" name="place_image" accept="image/*" style="font-size:12px;padding:6px;border:1px dashed #bfdbfe;border-radius:8px;background:#f8fafc">
            <button class="adm-btn sm" style="background:#eff6ff;color:#2563eb" type="submit">บันทึกรูป/หมวดหมู่</button>
          </form>
        </div>
      </div>
      <?php endforeach; ?>
      <div id="places-empty" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบสถานที่ที่ค้นหา</div>
    </div>

  </main>
  <script>
  function toggleEdit(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }

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

  function filterPlaces(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#places-list .place-group').forEach(group => {
      const match = !q || group.dataset.search.includes(q);
      group.style.display = match ? 'block' : 'none';
      if (match) visible++;
    });
    document.getElementById('places-empty').style.display = visible === 0 ? 'block' : 'none';
  }
  </script>
</body>
</html>
