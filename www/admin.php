<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin.php");
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
        }
    }

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

    if ($action === "delete_place") {
        $pid = intval($_POST["place_id"] ?? 0);
        $stmt = $conn->prepare("DELETE FROM places WHERE id=?");
        $stmt->bind_param("i", $pid);
        $stmt->execute();
        $msg = "ลบสถานที่สำเร็จ";
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
                if (move_uploaded_file($_FILES["reward_image"]["tmp_name"], $dest)) {
                    $img = "assets/images/rewards/" . $fname;
                }
            }
        }

        if ($name !== "" && $cost > 0) {
            $stmt = $conn->prepare("INSERT INTO rewards (name,description,cost_points,stock,image_url) VALUES (?,?,?,?,?)");
            $stmt->bind_param("ssiss", $name, $desc, $cost, $stock, $img);
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

$users   = $conn->query("SELECT id,name,email,role,points FROM users ORDER BY id")->fetch_all(MYSQLI_ASSOC);
$placesQ = $conn->query("SELECT id,name FROM places ORDER BY id");
$placesArr = $placesQ->fetch_all(MYSQLI_ASSOC);
$quests  = $conn->query("SELECT q.*,p.name AS place_name FROM quests q JOIN places p ON p.id=q.place_id ORDER BY q.id DESC")->fetch_all(MYSQLI_ASSOC);
$rewards = $conn->query("SELECT * FROM rewards ORDER BY id DESC")->fetch_all(MYSQLI_ASSOC);
$logs    = $conn->query("SELECT pl.*,u.name AS uname,a.name AS aname FROM point_logs pl JOIN users u ON u.id=pl.user_id LEFT JOIN users a ON a.id=pl.admin_id ORDER BY pl.id DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แอดมิน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .adm-header { margin-bottom:20px; }
    .adm-header h1 { font-size:26px;font-weight:700;color:#0f172a;margin:0 0 4px; }
    .adm-header p  { font-size:14px;color:#64748b;margin:0; }

    .adm-alert {
      padding:13px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px;
    }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .adm-card {
      background:#fff;border-radius:20px;padding:20px;
      box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:14px;
    }

    .adm-card h2 { font-size:17px;font-weight:700;color:#0f172a;margin:0 0 4px; }
    .adm-card p.sub { font-size:13px;color:#64748b;margin:0 0 16px; }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:12px 20px;border:none;border-radius:12px;
      font-family:inherit;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn.danger  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }
    .adm-btn.ghost   { background:#f8fafc;color:#374151;border:1px solid #e2e8f0; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn.sm      { padding:7px 14px;font-size:13px; }

    .adm-list { display:grid;gap:8px;margin-top:14px; }

    .adm-row-item {
      display:flex;align-items:center;gap:12px;
      padding:12px 14px;background:#f8fafc;border-radius:14px;
    }

    .adm-row-item .info { flex:1;min-width:0; }
    .adm-row-item strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .adm-row-item span   { font-size:12px;color:#64748b; }

    .type-badge {
      font-size:11px;font-weight:600;padding:3px 8px;border-radius:99px;white-space:nowrap;
    }
    .type-qr      { background:#dbeafe;color:#1d4ed8; }
    .type-checkin { background:#dcfce7;color:#15803d; }
    .type-quiz    { background:#ede9fe;color:#6d28d9; }

    .thumb {
      width:44px;height:44px;border-radius:12px;object-fit:cover;flex-shrink:0;
    }
    .thumb-ph {
      width:44px;height:44px;border-radius:12px;background:#e2e8f0;flex-shrink:0;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="adm-header">
      <h1>Admin Panel</h1>
      <p>จัดการระบบ ล่าพิกัด.com</p>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- เพิ่มคะแนน -->
    <div class="adm-card">
      <h2>เพิ่ม / ลดคะแนนผู้ใช้</h2>
      <p class="sub">ใส่ค่าติดลบเพื่อลดคะแนน</p>
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

    <!-- เพิ่มสถานที่ -->
    <div class="adm-card">
      <h2>เพิ่มสถานที่</h2>
      <form method="post" enctype="multipart/form-data" class="adm-form">
        <input type="hidden" name="action" value="add_place">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input name="place_name" placeholder="ชื่อสถานที่" required>
        <input name="location_text" placeholder="อำเภอ / จังหวัด">
        <select name="category">
          <option value="">-- เลือกหมวดหมู่ --</option>
          <option value="วัด/ศาสนา">วัด/ศาสนา</option>
          <option value="ธรรมชาติ">ธรรมชาติ</option>
          <option value="ร้านอาหาร">ร้านอาหาร</option>
          <option value="แหล่งท่องเที่ยว">แหล่งท่องเที่ยว</option>
          <option value="พิพิธภัณฑ์">พิพิธภัณฑ์</option>
          <option value="ชุมชน">ชุมชน</option>
          <option value="อื่นๆ">อื่นๆ</option>
        </select>
        <textarea name="place_description" placeholder="รายละเอียดสถานที่"></textarea>
        <label style="font-size:13px;color:#374151">รูปภาพสถานที่</label>
        <input type="file" name="place_image" accept="image/*" style="background:#f8fafc;border:1.5px dashed #bfdbfe;padding:10px">
        <button class="adm-btn primary" type="submit">เพิ่มสถานที่</button>
      </form>

      <?php
      $existPlaces = $conn->query("SELECT * FROM places ORDER BY id DESC");
      while ($pl = $existPlaces->fetch_assoc()):
      ?>
        <div class="adm-row-item" style="margin-top:10px;flex-wrap:wrap;gap:10px">
          <?php if ($pl["image_url"]): ?>
            <img src="<?= e($pl["image_url"]) ?>" class="thumb">
          <?php else: ?>
            <div class="thumb-ph"></div>
          <?php endif; ?>
          <div class="info">
            <strong><?= e($pl["name"]) ?></strong>
            <span><?= $pl["category"] ? e($pl["category"]) : 'ไม่มีหมวดหมู่' ?> · <?= e($pl["location_text"] ?? '') ?></span>
          </div>
          <form method="post" style="display:grid;gap:6px;width:100%;margin-bottom:6px">
            <input type="hidden" name="action" value="update_place">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
            <input name="place_name" value="<?= e($pl["name"]) ?>" placeholder="ชื่อสถานที่" style="font-size:13px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
              <input name="district" value="<?= e($pl["district"] ?? '') ?>" placeholder="อำเภอ" style="font-size:13px">
              <input name="province" value="<?= e($pl["province"] ?? '') ?>" placeholder="จังหวัด" style="font-size:13px">
            </div>
            <input name="location_text" value="<?= e($pl["location_text"] ?? '') ?>" placeholder="ที่อยู่ย่อ (เช่น อ.กุฉินารายณ์)" style="font-size:13px">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px">
              <input name="lat" type="number" step="any" value="<?= $pl["lat"] !== null ? $pl["lat"] : '' ?>" placeholder="ละติจูด เช่น 16.4322" style="font-size:13px">
              <input name="lng" type="number" step="any" value="<?= $pl["lng"] !== null ? $pl["lng"] : '' ?>" placeholder="ลองติจูด เช่น 104.062" style="font-size:13px">
            </div>
            <p style="font-size:11px;color:#94a3b8;margin:0">หาพิกัดได้จาก Google Maps → คลิกขวาที่จุด → คัดลอกตัวเลข</p>
            <button class="adm-btn primary sm" type="submit">บันทึก</button>
          </form>
          <form method="post" enctype="multipart/form-data" style="display:flex;gap:6px;flex-wrap:wrap;width:100%">
            <input type="hidden" name="action" value="update_place_image">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
            <select name="category" style="flex:1;min-width:120px;font-size:13px">
              <option value="">-- หมวดหมู่ --</option>
              <?php foreach (["วัด/ศาสนา","ธรรมชาติ","ร้านอาหาร","แหล่งท่องเที่ยว","พิพิธภัณฑ์","ชุมชน","อื่นๆ"] as $cat): ?>
                <option value="<?= $cat ?>" <?= $pl["category"] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
            <input type="file" name="place_image" accept="image/*" style="flex:1;font-size:12px;padding:6px;border:1px dashed #bfdbfe;border-radius:8px;background:#f8fafc">
            <button class="adm-btn ghost sm" type="submit">บันทึก</button>
          </form>
          <form method="post" style="width:100%;margin-top:4px">
            <input type="hidden" name="action" value="delete_place">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="place_id" value="<?= $pl["id"] ?>">
            <button class="adm-btn danger sm" type="submit" style="width:100%" onclick="return confirm('ลบสถานที่: <?= e($pl["name"]) ?>?\n(จะลบภารกิจทั้งหมดในสถานที่นี้ด้วย)')">ลบสถานที่นี้</button>
          </form>
        </div>
      <?php endwhile; ?>
    </div>

    <!-- เพิ่มภารกิจ -->
    <div class="adm-card">
      <h2>เพิ่มภารกิจ</h2>
      <p class="sub">ระบบสุ่มรหัส QR ให้อัตโนมัติ</p>
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="add_quest">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <select name="place_id" required>
          <option value="">เลือกสถานที่</option>
          <?php foreach ($placesArr as $p): ?>
            <option value="<?= $p["id"] ?>"><?= e($p["name"]) ?></option>
          <?php endforeach; ?>
        </select>
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

    <!-- รายการภารกิจ -->
    <div class="adm-card">
      <h2>ภารกิจทั้งหมด (<?= count($quests) ?>)</h2>
      <div class="adm-list">
        <?php foreach ($quests as $q): ?>
          <div class="adm-row-item">
            <div class="info">
              <strong><?= e($q["title"]) ?></strong>
              <span><?= e($q["place_name"]) ?> · +<?= $q["reward_points"] ?> คะแนน</span>
            </div>
            <span class="type-badge type-<?= e($q["quest_type"]) ?>"><?= e($q["quest_type"]) ?></span>
            <?php if ($q["quest_type"] === "qr"): ?>
              <a href="qr_view.php?id=<?= $q["id"] ?>" class="adm-btn ghost sm">QR</a>
            <?php endif; ?>
            <button class="adm-btn ghost sm" onclick="toggleEdit('q<?= $q["id"] ?>')">แก้ไข</button>
            <form method="post" style="flex-shrink:0">
              <input type="hidden" name="action" value="delete_quest">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="quest_id" value="<?= $q["id"] ?>">
              <button class="adm-btn danger sm" type="submit" onclick="return confirm('ลบภารกิจ: <?= e($q["title"]) ?>?')">ลบ</button>
            </form>
          </div>
          <div id="q<?= $q["id"] ?>" style="display:none;padding:10px 14px 14px;border-top:1px solid #f1f5f9">
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
        <?php endforeach; ?>
      </div>
    </div>

    <!-- จัดการรางวัล -->
    <div class="adm-card">
      <h2>จัดการรางวัล</h2>
      <p class="sub">ใส่ URL รูปจากอินเทอร์เน็ตหรือที่อัพโหลดไว้</p>
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
      <div class="adm-list">
        <?php foreach ($rewards as $rw): ?>
          <div class="adm-row-item">
            <?php if ($rw["image_url"]): ?>
              <img src="<?= e($rw["image_url"]) ?>" class="thumb">
            <?php else: ?>
              <div class="thumb-ph"></div>
            <?php endif; ?>
            <div class="info">
              <strong><?= e($rw["name"]) ?></strong>
              <span><?= number_format($rw["cost_points"]) ?> คะแนน · เหลือ <?= $rw["stock"] ?> ชิ้น</span>
            </div>
            <form method="post" style="flex-shrink:0">
              <input type="hidden" name="action" value="delete_reward">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="reward_id" value="<?= $rw["id"] ?>">
              <button class="adm-btn danger sm" type="submit" onclick="return confirm('ลบรางวัลนี้?')">ลบ</button>
            </form>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- รูปเช็คอินล่าสุด -->
    <?php
    $photos = $conn->query("
        SELECT uq.photo_url, uq.completed_at, u.name AS uname, q.title AS qtitle
        FROM user_quests uq
        JOIN users u ON u.id = uq.user_id
        JOIN quests q ON q.id = uq.quest_id
        WHERE uq.photo_url IS NOT NULL
        ORDER BY uq.completed_at DESC
        LIMIT 12
    ")->fetch_all(MYSQLI_ASSOC);
    ?>
    <?php if (!empty($photos)): ?>
    <div class="adm-card">
      <h2>รูปเช็คอินล่าสุด</h2>
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:12px">
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

    <!-- ประวัติคะแนน -->
    <div class="adm-card">
      <h2>ประวัติคะแนนล่าสุด</h2>
      <div class="adm-list">
        <?php foreach ($logs as $log): ?>
          <div class="adm-row-item">
            <div class="info">
              <strong><?= e($log["uname"]) ?></strong>
              <span><?= e($log["reason"]) ?></span>
            </div>
            <span style="font-size:14px;font-weight:700;color:<?= $log["points"] >= 0 ? '#16a34a' : '#e11d48' ?>">
              <?= $log["points"] >= 0 ? '+' : '' ?><?= $log["points"] ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>
  <script>
  function toggleEdit(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  </script>
</body>
</html>
