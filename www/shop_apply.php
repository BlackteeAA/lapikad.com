<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$role   = $_SESSION["role"] ?? "";

if ($role === "shop") redirect("shop.php");
if ($role === "admin") redirect("admin.php");

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_apply.php");
    $action = $_POST["action"] ?? "";

    if ($action === "apply") {
        $name        = trim($_POST["shop_name"] ?? "");
        $nameEn      = trim($_POST["shop_name_en"] ?? "");
        $loc         = trim($_POST["location_text"] ?? "");
        $category    = trim($_POST["category"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $lat         = $_POST["lat"] !== "" ? floatval($_POST["lat"]) : null;
        $lng         = $_POST["lng"] !== "" ? floatval($_POST["lng"]) : null;
        $phone       = trim($_POST["phone"] ?? "");
        $lineId      = trim($_POST["line_id"] ?? "");
        $facebook    = trim($_POST["facebook_url"] ?? "");
        $contactMail = trim($_POST["contact_email"] ?? "");
        $hours       = trim($_POST["opening_hours"] ?? "");
        $highlights  = trim($_POST["highlights"] ?? "");
        $img         = "";

        if (isset($_FILES["shop_image"]) && $_FILES["shop_image"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["shop_image"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg","jpeg","png","webp"]) && $_FILES["shop_image"]["size"] < 5*1024*1024) {
                $fname = "place_" . time() . "_" . bin2hex(random_bytes(4)) . "." . $ext;
                $dest  = __DIR__ . "/assets/images/places/" . $fname;
                if (!is_dir(__DIR__ . "/assets/images/places/")) mkdir(__DIR__ . "/assets/images/places/", 0755, true);
                if (move_uploaded_file($_FILES["shop_image"]["tmp_name"], $dest)) {
                    $img = "assets/images/places/" . $fname;
                }
            }
        }

        if ($name !== "" && $category !== "" && $description !== "") {
            $stmt = $conn->prepare("
                INSERT INTO shop_requests
                    (user_id, shop_name, location_text, image_url, category, lat, lng,
                     shop_name_en, description, phone, line_id, facebook_url, contact_email, opening_hours, highlights)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param(
                "issssddssssssss",
                $userId, $name, $loc, $img, $category, $lat, $lng,
                $nameEn, $description, $phone, $lineId, $facebook, $contactMail, $hours, $highlights
            );
            $stmt->execute();
            $msg = "ส่งคำขอสมัครร้านค้าสำเร็จ กรุณารอแอดมินตรวจสอบ";
        } else { $msg = "กรุณากรอกชื่อร้าน ประเภทร้าน และคำอธิบายร้านค้าให้ครบ"; $msgType = "bad"; }
    }
}

$stmt = $conn->prepare("SELECT * FROM shop_requests WHERE user_id=? ORDER BY id DESC LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myRequest = $stmt->get_result()->fetch_assoc();

$showForm = !$myRequest || $myRequest["status"] === "rejected";

$baseCategories = ["ร้านค้า/ร้านอาหาร","วัด/ศาสนา","ธรรมชาติ","แหล่งท่องเที่ยว","พิพิธภัณฑ์","ชุมชน","อื่นๆ"];
$usedCategories = array_column($conn->query("SELECT DISTINCT category FROM places WHERE category IS NOT NULL AND category != ''")->fetch_all(MYSQLI_ASSOC), "category");
$shopCategories = array_values(array_unique(array_merge($baseCategories, $usedCategories)));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สมัครร้านค้า | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .status-pill { font-size:11px;font-weight:600;padding:4px 10px;border-radius:99px;white-space:nowrap;display:inline-block; }
    .status-pending  { background:#fef9c3;color:#a16207; }
    .status-rejected { background:#fff1f2;color:#e11d48; }

    .pg-card { background:#fff;border-radius:20px;padding:18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    /* ── Hero ── */
    .sa-hero {
      background:#fff;border-radius:20px;padding:22px 20px;margin-bottom:14px;
      box-shadow:0 2px 10px rgba(15,23,42,.06);
      display:flex;align-items:center;gap:16px;
    }
    .sa-hero-icon {
      width:76px;height:76px;border-radius:20px;flex-shrink:0;
      background:linear-gradient(135deg,#eff6ff,#dbeafe);
      display:flex;align-items:center;justify-content:center;
    }
    .sa-hero-icon svg { width:40px;height:40px;fill:#2563eb; }
    .sa-hero h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0 0 4px; }
    .sa-hero p  { font-size:12.5px;color:#64748b;margin:0;line-height:1.5;font-weight:300; }

    /* ── Stepper ── */
    .sa-stepper { display:flex;align-items:flex-start;margin-bottom:16px; }
    .sa-step-dot {
      display:flex;flex-direction:column;align-items:center;gap:6px;cursor:pointer;flex-shrink:0;width:56px;
    }
    .sa-step-num {
      width:32px;height:32px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;
      font-size:13px;font-weight:700;color:#94a3b8;background:#e2e8f0;
      transition:background .15s,color .15s;
    }
    .sa-step-dot.active .sa-step-num,
    .sa-step-dot.done .sa-step-num { background:#2563eb;color:#fff; }
    .sa-step-label { font-size:10.5px;color:#94a3b8;text-align:center;line-height:1.2; }
    .sa-step-dot.active .sa-step-label { color:#2563eb;font-weight:600; }
    .sa-step-line { flex:1;height:2px;background:#e2e8f0;margin-top:15px;border-top:2px dashed #cbd5e1;background:none; }

    /* ── Section header ── */
    .sa-section-head { display:flex;align-items:flex-start;gap:10px;margin-bottom:16px; }
    .sa-section-icon {
      width:36px;height:36px;border-radius:10px;flex-shrink:0;
      background:#eff6ff;display:flex;align-items:center;justify-content:center;
    }
    .sa-section-icon svg { width:18px;height:18px;fill:#2563eb; }
    .sa-section-head h2 { font-size:16px;font-weight:700;color:#0f172a;margin:0; }
    .sa-section-head p  { font-size:12px;color:#94a3b8;margin:2px 0 0; }

    /* ── Fields ── */
    .sa-field { margin-bottom:16px; }
    .sa-field:last-child { margin-bottom:0; }
    .sa-field label {
      display:block;font-size:13.5px;font-weight:600;color:#0f172a;margin-bottom:8px;
    }
    .sa-field label .req { color:#ef4444;margin-left:2px; }
    .sa-field label .opt { color:#94a3b8;font-weight:400;font-size:12px; }

    .sa-input, .sa-select, .sa-textarea {
      width:100%;padding:13px 14px;border:1.5px solid #e2e8f0;border-radius:12px;
      font-family:'Kanit',sans-serif;font-size:14px;color:#0f172a;outline:none;
      background:#fff;transition:border-color .15s;
    }
    .sa-input:focus, .sa-select:focus, .sa-textarea:focus { border-color:#2563eb; }
    .sa-input::placeholder, .sa-textarea::placeholder { color:#94a3b8; }
    .sa-textarea { resize:none;min-height:80px; }
    .sa-input.invalid, .sa-select.invalid, .sa-textarea.invalid { border-color:#ef4444; }

    .sa-counter { text-align:right;font-size:11px;color:#94a3b8;margin-top:4px; }

    .sa-select-wrap { position:relative; }
    .sa-select { appearance:none;padding-right:38px; }
    .sa-select-wrap svg {
      position:absolute;right:14px;top:50%;transform:translateY(-50%);
      width:16px;height:16px;fill:#94a3b8;pointer-events:none;
    }

    .sa-hint { font-size:11.5px;color:#94a3b8;margin-top:6px; }
    .sa-error { font-size:12px;color:#e11d48;margin-top:6px;display:none; }

    .sa-row { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .sa-locate-btn {
      display:inline-flex;align-items:center;gap:6px;
      background:#eff6ff;color:#2563eb;border:none;border-radius:10px;
      padding:10px 14px;font-family:inherit;font-size:12.5px;font-weight:600;
      cursor:pointer;margin-top:8px;
    }
    .sa-locate-btn svg { width:15px;height:15px;fill:currentColor; }
    .sa-geo-status { font-size:11.5px;color:#94a3b8;margin-left:8px; }

    /* ── Upload dropzone ── */
    .sa-dropzone {
      border:1.5px dashed #bfdbfe;border-radius:14px;background:#f8fafc;
      padding:28px 16px;text-align:center;cursor:pointer;position:relative;display:block;
    }
    .sa-dropzone input[type=file] {
      position:absolute;inset:0;opacity:0;cursor:pointer;
    }
    .sa-dropzone svg { width:32px;height:32px;fill:#94a3b8;margin-bottom:8px; }
    .sa-dropzone .sa-dz-title { font-size:13.5px;font-weight:600;color:#2563eb; }
    .sa-dropzone .sa-dz-sub   { font-size:11.5px;color:#94a3b8;margin-top:4px; }
    .sa-dropzone.has-file { border-style:solid;border-color:#bbf7d0;background:#f0fdf4; }
    .sa-dz-preview { max-height:120px;border-radius:10px;margin-bottom:8px;display:none; }

    .sa-draft-banner {
      background:#fffbeb;border:1px solid #fde68a;color:#92400e;
      font-size:12px;border-radius:12px;padding:10px 14px;margin-bottom:16px;display:none;
    }

    /* ── Wizard nav ── */
    .wizard-step { display:none; }
    .wizard-step.active { display:block; }

    .sa-wizard-nav { display:flex;gap:10px;margin-top:20px; }
    .sa-nav-btn {
      flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
      padding:14px;border-radius:999px;border:none;cursor:pointer;
      font-family:'Kanit',sans-serif;font-size:14.5px;font-weight:700;
    }
    .sa-nav-btn svg { width:17px;height:17px;fill:currentColor; }
    .sa-nav-btn.primary { background:#2563eb;color:#fff; }
    .sa-nav-btn.primary:hover { opacity:.9; }
    .sa-nav-btn.secondary { background:#f1f5f9;color:#374151;flex:0 0 auto;padding-left:20px;padding-right:20px; }

    .sa-save-draft-btn {
      display:block;width:100%;text-align:center;background:#fff;color:#2563eb;
      border:1.5px solid #bfdbfe;border-radius:999px;padding:12px;margin-top:10px;
      font-family:'Kanit',sans-serif;font-size:13.5px;font-weight:600;cursor:pointer;
    }
    .sa-draft-status { display:block;text-align:center;font-size:11.5px;color:#16a34a;margin-top:6px;min-height:14px; }

    /* ── Summary (step 5) ── */
    .sa-summary-row { display:flex;justify-content:space-between;gap:10px;padding:10px 0;border-bottom:1px solid #f1f5f9; }
    .sa-summary-row:last-child { border-bottom:none; }
    .sa-summary-row .lbl { font-size:12.5px;color:#94a3b8;flex-shrink:0; }
    .sa-summary-row .val { font-size:13px;color:#0f172a;font-weight:500;text-align:right;overflow-wrap:anywhere; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="profile.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>" id="sa-server-msg" data-ok="<?= $msgType === 'good' ? '1' : '0' ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($myRequest && $myRequest["status"] === "pending"): ?>
      <div class="pg-card">
        <span class="status-pill status-pending">รอตรวจสอบ</span>
        <p style="font-size:14px;color:#374151;margin:12px 0 0">คำขอสมัครร้าน "<?= e($myRequest["shop_name"]) ?>" ของคุณกำลังรอแอดมินตรวจสอบ</p>
      </div>
    <?php elseif ($myRequest && $myRequest["status"] === "rejected"): ?>
      <div class="pg-card" style="margin-bottom:14px">
        <span class="status-pill status-rejected">ถูกปฏิเสธ</span>
        <p style="font-size:14px;color:#374151;margin:12px 0 0">คำขอสมัครร้าน "<?= e($myRequest["shop_name"]) ?>" ของคุณถูกปฏิเสธ</p>
        <?php if ($myRequest["admin_note"]): ?>
          <p style="font-size:13px;color:#94a3b8;margin:6px 0 0">เหตุผล: <?= e($myRequest["admin_note"]) ?></p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($showForm): ?>

    <!-- Hero -->
    <div class="sa-hero">
      <div class="sa-hero-icon">
        <svg viewBox="0 0 24 24"><path d="M20 4H4v2l1.6 8H18.4L20 6V4zM4 20h16v-2H4v2zm2.4-6l-1-6h13.2l-1 6H6.4z"/></svg>
      </div>
      <div>
        <h1>สมัครร้านค้า</h1>
        <p>เข้าร่วมเป็นร้านค้ากับล่าพิกัด.com<br>ร่วมสร้างภารกิจและกิจกรรมสนุกๆ ให้ลูกค้าค้นพบร้านคุณ</p>
      </div>
    </div>

    <!-- Stepper -->
    <div class="sa-stepper" id="sa-stepper">
      <div class="sa-step-dot active" data-step="1" onclick="goToStep(1)">
        <span class="sa-step-num">1</span>
        <span class="sa-step-label">ข้อมูลร้านค้า</span>
      </div>
      <div class="sa-step-line"></div>
      <div class="sa-step-dot" data-step="2" onclick="goToStep(2)">
        <span class="sa-step-num">2</span>
        <span class="sa-step-label">ข้อมูลติดต่อ</span>
      </div>
      <div class="sa-step-line"></div>
      <div class="sa-step-dot" data-step="3" onclick="goToStep(3)">
        <span class="sa-step-num">3</span>
        <span class="sa-step-label">ที่ตั้งร้านค้า</span>
      </div>
      <div class="sa-step-line"></div>
      <div class="sa-step-dot" data-step="4" onclick="goToStep(4)">
        <span class="sa-step-num">4</span>
        <span class="sa-step-label">รายละเอียด</span>
      </div>
      <div class="sa-step-line"></div>
      <div class="sa-step-dot" data-step="5" onclick="goToStep(5)">
        <span class="sa-step-num">5</span>
        <span class="sa-step-label">ยืนยันข้อมูล</span>
      </div>
    </div>

    <div class="pg-card">
      <div class="sa-draft-banner" id="sa-draft-banner">
        กู้คืนข้อมูลจากฉบับร่างที่บันทึกไว้แล้ว — กรุณาแนบรูปร้านค้าใหม่อีกครั้งก่อนส่งคำขอ (ไฟล์รูปไม่สามารถบันทึกไว้ในฉบับร่างได้)
      </div>

      <form method="post" enctype="multipart/form-data" id="shop-apply-form">
        <input type="hidden" name="action" value="apply">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <!-- Step 1: ข้อมูลร้านค้า -->
        <div class="wizard-step active" data-step="1">
          <div class="sa-section-head">
            <div class="sa-section-icon">
              <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            </div>
            <div>
              <h2>ข้อมูลร้านค้า</h2>
              <p>กรอกข้อมูลพื้นฐานของร้านค้าของคุณ</p>
            </div>
          </div>

          <div class="sa-field">
            <label>ชื่อร้านค้า <span class="req">*</span></label>
            <input class="sa-input" name="shop_name" id="f-shop-name" placeholder="เช่น ร้านกาแฟล่าพิกัด" required>
            <div class="sa-error">กรุณากรอกชื่อร้านค้า</div>
          </div>

          <div class="sa-field">
            <label>ชื่อร้านค้า (ภาษาอังกฤษ) <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="shop_name_en" id="f-shop-name-en" placeholder="เช่น Lapikad Cafe">
          </div>

          <div class="sa-field">
            <label>ประเภทร้านค้า <span class="req">*</span></label>
            <div class="sa-select-wrap">
              <select class="sa-select" name="category" id="f-category" required>
                <option value="">เลือกประเภทร้านค้า</option>
                <?php foreach ($shopCategories as $cat): ?>
                  <option value="<?= e($cat) ?>" <?= $cat === "ร้านค้า/ร้านอาหาร" ? "selected" : "" ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
              </select>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
            </div>
            <div class="sa-error">กรุณาเลือกประเภทร้านค้า</div>
            <p class="sa-hint">หมวด "ร้านค้า/ร้านอาหาร" ภารกิจของร้านจะรีเฟรชให้ทำซ้ำได้ทุกวัน</p>
          </div>

          <div class="sa-field">
            <label>คำอธิบายร้านค้า <span class="req">*</span></label>
            <textarea class="sa-textarea" name="description" id="f-description" maxlength="300" placeholder="อธิบายเกี่ยวกับร้านค้าของคุณ เช่น ประเภทสินค้า บริการ จุดเด่นของร้าน" required></textarea>
            <div class="sa-counter"><span id="f-description-count">0</span>/300</div>
            <div class="sa-error">กรุณากรอกคำอธิบายร้านค้า</div>
          </div>

          <div class="sa-field">
            <label>รูปร้านค้า <span class="req">*</span></label>
            <label class="sa-dropzone" id="sa-dropzone">
              <img id="sa-dz-preview" class="sa-dz-preview">
              <div id="sa-dz-placeholder">
                <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                <div class="sa-dz-title">คลิกหรืออัปโหลดไฟล์</div>
                <div class="sa-dz-sub">รองรับไฟล์ JPG, PNG ขนาดไม่เกิน 5MB</div>
              </div>
              <input type="file" name="shop_image" id="shop-image-input" accept="image/*">
            </label>
            <div class="sa-error">กรุณาแนบรูปร้านค้า</div>
          </div>
        </div>

        <!-- Step 2: ข้อมูลติดต่อ -->
        <div class="wizard-step" data-step="2">
          <div class="sa-section-head">
            <div class="sa-section-icon">
              <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
            </div>
            <div>
              <h2>ข้อมูลติดต่อ</h2>
              <p>ช่องทางให้ลูกค้าหรือแอดมินติดต่อร้านคุณ (ไม่บังคับ)</p>
            </div>
          </div>

          <div class="sa-field">
            <label>เบอร์โทรศัพท์ร้าน <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="phone" type="tel" placeholder="เช่น 08X-XXX-XXXX">
          </div>
          <div class="sa-field">
            <label>LINE ID / ช่องทาง LINE OA <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="line_id" placeholder="เช่น @lapikadcafe">
          </div>
          <div class="sa-field">
            <label>Facebook Page <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="facebook_url" placeholder="ลิงก์เพจ Facebook ของร้าน">
          </div>
          <div class="sa-field">
            <label>อีเมลติดต่ออื่น <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="contact_email" type="email" placeholder="อีเมลสำหรับติดต่อร้าน (ถ้าต่างจากอีเมลบัญชี)">
          </div>
        </div>

        <!-- Step 3: ที่ตั้งร้านค้า -->
        <div class="wizard-step" data-step="3">
          <div class="sa-section-head">
            <div class="sa-section-icon">
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </div>
            <div>
              <h2>ที่ตั้งร้านค้า</h2>
              <p>ใช้เช็คระยะตอนลูกค้าทำภารกิจใกล้ร้าน</p>
            </div>
          </div>

          <div class="sa-field">
            <label>ที่อยู่ร้านค้า <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="location_text" placeholder="เช่น อำเภอกุฉินารายณ์ จังหวัดกาฬสินธุ์">
          </div>

          <div class="sa-field">
            <label>พิกัดร้านค้า <span class="opt">(ไม่บังคับ)</span></label>
            <div class="sa-row">
              <input class="sa-input" name="lat" id="shop-lat" type="number" step="any" placeholder="ละติจูด เช่น 16.4322">
              <input class="sa-input" name="lng" id="shop-lng" type="number" step="any" placeholder="ลองติจูด เช่น 104.062">
            </div>
            <button type="button" class="sa-locate-btn" onclick="useCurrentLocation()">
              <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
              ใช้พิกัดปัจจุบันของฉัน
            </button>
            <span class="sa-geo-status" id="geo-status"></span>
          </div>
        </div>

        <!-- Step 4: รายละเอียดร้านค้า -->
        <div class="wizard-step" data-step="4">
          <div class="sa-section-head">
            <div class="sa-section-icon">
              <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm.5 5H11v6l5.25 3.15.75-1.23-4.5-2.67V7z"/></svg>
            </div>
            <div>
              <h2>รายละเอียดร้านค้า</h2>
              <p>ข้อมูลเพิ่มเติมที่ช่วยให้ลูกค้ารู้จักร้านคุณมากขึ้น</p>
            </div>
          </div>

          <div class="sa-field">
            <label>เวลาเปิด-ปิด <span class="opt">(ไม่บังคับ)</span></label>
            <input class="sa-input" name="opening_hours" placeholder="เช่น 08:00-18:00 ทุกวัน">
          </div>
          <div class="sa-field">
            <label>จุดเด่น/บริการของร้าน <span class="opt">(ไม่บังคับ)</span></label>
            <textarea class="sa-textarea" name="highlights" maxlength="300" placeholder="เช่น มีที่จอดรถ, มี Wi-Fi, ที่นั่งสัตว์เลี้ยงได้"></textarea>
          </div>
        </div>

        <!-- Step 5: ยืนยันข้อมูล -->
        <div class="wizard-step" data-step="5">
          <div class="sa-section-head">
            <div class="sa-section-icon">
              <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div>
              <h2>ยืนยันข้อมูล</h2>
              <p>ตรวจสอบข้อมูลก่อนส่งคำขอสมัครร้านค้า</p>
            </div>
          </div>
          <div id="sa-summary"></div>
        </div>

        <div class="sa-wizard-nav">
          <button type="button" class="sa-nav-btn secondary" id="sa-back-btn" onclick="prevStep()" style="display:none">← กลับ</button>
          <button type="button" class="sa-nav-btn primary" id="sa-next-btn" onclick="nextStep()">
            ถัดไป
            <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
          </button>
          <button type="submit" class="sa-nav-btn primary" id="sa-submit-btn" style="display:none">
            ส่งคำขอสมัคร
            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
          </button>
        </div>

        <button type="button" class="sa-save-draft-btn" onclick="saveDraft()">บันทึกไว้ก่อน</button>
        <span class="sa-draft-status" id="sa-draft-status"></span>
      </form>
    </div>
    <?php endif; ?>

  </main>
  <script>
  var currentStep = 1;
  var totalSteps = 5;
  var stepLabels = {
    1: 'ข้อมูลร้านค้า', 2: 'ข้อมูลติดต่อ', 3: 'ที่ตั้งร้านค้า', 4: 'รายละเอียด', 5: 'ยืนยันข้อมูล'
  };

  function requiredFieldsForStep(n) {
    if (n === 1) {
      return [
        { el: document.getElementById('f-shop-name'), check: el => el.value.trim() !== '' },
        { el: document.getElementById('f-category'), check: el => el.value !== '' },
        { el: document.getElementById('f-description'), check: el => el.value.trim() !== '' },
        { el: document.getElementById('shop-image-input'), check: el => el.files && el.files.length > 0 },
      ];
    }
    return [];
  }

  function validateStep(n) {
    const fields = requiredFieldsForStep(n);
    let ok = true;
    fields.forEach(f => {
      const errEl = f.el.closest('.sa-field').querySelector('.sa-error');
      if (!f.check(f.el)) {
        ok = false;
        f.el.classList.add('invalid');
        if (errEl) errEl.style.display = 'block';
      } else {
        f.el.classList.remove('invalid');
        if (errEl) errEl.style.display = 'none';
      }
    });
    return ok;
  }

  function showStep(n) {
    document.querySelectorAll('.wizard-step').forEach(el => {
      el.classList.toggle('active', parseInt(el.dataset.step) === n);
    });
    document.querySelectorAll('.sa-step-dot').forEach(dot => {
      const s = parseInt(dot.dataset.step);
      dot.classList.toggle('active', s === n);
      dot.classList.toggle('done', s < n);
    });
    document.getElementById('sa-back-btn').style.display = n > 1 ? 'flex' : 'none';
    document.getElementById('sa-next-btn').style.display = n < totalSteps ? 'flex' : 'none';
    document.getElementById('sa-submit-btn').style.display = n === totalSteps ? 'flex' : 'none';
    if (n === totalSteps) buildSummary();
    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function nextStep() {
    if (!validateStep(currentStep)) return;
    if (currentStep < totalSteps) showStep(currentStep + 1);
  }

  function prevStep() {
    if (currentStep > 1) showStep(currentStep - 1);
  }

  function goToStep(n) {
    showStep(n);
  }

  function summaryRow(label, value) {
    return '<div class="sa-summary-row"><span class="lbl">' + label + '</span><span class="val">' + (value || '-') + '</span></div>';
  }

  function esc(s) {
    const d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function buildSummary() {
    const catSelect = document.getElementById('f-category');
    const catText = catSelect.options[catSelect.selectedIndex] ? catSelect.options[catSelect.selectedIndex].text : '';
    const lat = document.getElementById('shop-lat').value;
    const lng = document.getElementById('shop-lng').value;
    const fileInput = document.getElementById('shop-image-input');
    const fileName = fileInput.files.length ? fileInput.files[0].name : 'ยังไม่ได้แนบรูป';

    const rows = [
      summaryRow('ชื่อร้านค้า', esc(document.getElementById('f-shop-name').value)),
      summaryRow('ชื่อร้านค้า (EN)', esc(document.getElementById('f-shop-name-en').value)),
      summaryRow('ประเภทร้านค้า', esc(catText === 'เลือกประเภทร้านค้า' ? '' : catText)),
      summaryRow('คำอธิบายร้านค้า', esc(document.getElementById('f-description').value)),
      summaryRow('รูปร้านค้า', esc(fileName)),
      summaryRow('เบอร์โทรศัพท์', esc(document.querySelector('[name="phone"]').value)),
      summaryRow('LINE', esc(document.querySelector('[name="line_id"]').value)),
      summaryRow('Facebook', esc(document.querySelector('[name="facebook_url"]').value)),
      summaryRow('อีเมลติดต่อ', esc(document.querySelector('[name="contact_email"]').value)),
      summaryRow('ที่อยู่ร้านค้า', esc(document.querySelector('[name="location_text"]').value)),
      summaryRow('พิกัด', (lat && lng) ? (esc(lat) + ', ' + esc(lng)) : ''),
      summaryRow('เวลาเปิด-ปิด', esc(document.querySelector('[name="opening_hours"]').value)),
      summaryRow('จุดเด่น/บริการ', esc(document.querySelector('[name="highlights"]').value)),
    ];
    document.getElementById('sa-summary').innerHTML = rows.join('');
  }

  function useCurrentLocation() {
    const status = document.getElementById('geo-status');
    if (!navigator.geolocation) {
      status.textContent = 'อุปกรณ์นี้ไม่รองรับการระบุพิกัด';
      return;
    }
    status.textContent = 'กำลังค้นหาพิกัด...';
    navigator.geolocation.getCurrentPosition(
      pos => {
        document.getElementById('shop-lat').value = pos.coords.latitude.toFixed(7);
        document.getElementById('shop-lng').value = pos.coords.longitude.toFixed(7);
        status.textContent = 'ระบุพิกัดสำเร็จ';
      },
      () => { status.textContent = 'ไม่สามารถระบุพิกัดได้ กรุณากรอกเอง'; },
      { timeout: 8000 }
    );
  }

  document.getElementById('shop-image-input')?.addEventListener('change', function() {
    const file = this.files[0];
    const zone = document.getElementById('sa-dropzone');
    const preview = document.getElementById('sa-dz-preview');
    const placeholder = document.getElementById('sa-dz-placeholder');
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
    placeholder.style.display = 'none';
    zone.classList.add('has-file');
    document.getElementById('shop-image-input').closest('.sa-field').querySelector('.sa-error').style.display = 'none';
  });

  document.getElementById('f-description')?.addEventListener('input', function() {
    document.getElementById('f-description-count').textContent = this.value.length;
  });

  // Cross-step validation net: catches the case where someone jumped via the
  // stepper dots straight to step 5 without ever passing nextStep()'s checks.
  document.getElementById('shop-apply-form')?.addEventListener('submit', function(e) {
    for (let s = 1; s < totalSteps; s++) {
      if (!validateStep(s)) {
        e.preventDefault();
        showStep(s);
        return;
      }
    }
    localStorage.removeItem('shop_apply_draft');
  });

  // ── Draft save / restore (localStorage only — file input can't be restored) ──
  var DRAFT_FIELDS = [
    'shop_name','shop_name_en','category','description',
    'location_text','lat','lng',
    'phone','line_id','facebook_url','contact_email',
    'opening_hours','highlights'
  ];

  function saveDraft() {
    const form = document.getElementById('shop-apply-form');
    const data = {};
    DRAFT_FIELDS.forEach(name => {
      const el = form.querySelector('[name="' + name + '"]');
      if (el) data[name] = el.value;
    });
    localStorage.setItem('shop_apply_draft', JSON.stringify(data));
    const status = document.getElementById('sa-draft-status');
    status.textContent = 'บันทึกฉบับร่างแล้ว';
    setTimeout(() => { status.textContent = ''; }, 3000);
  }

  function restoreDraft() {
    const raw = localStorage.getItem('shop_apply_draft');
    if (!raw) return;
    let data;
    try { data = JSON.parse(raw); } catch (e) { return; }
    const form = document.getElementById('shop-apply-form');
    let restored = false;
    DRAFT_FIELDS.forEach(name => {
      const el = form.querySelector('[name="' + name + '"]');
      if (el && data[name]) { el.value = data[name]; restored = true; }
    });
    if (restored) {
      document.getElementById('sa-draft-banner').style.display = 'block';
      const descEl = document.getElementById('f-description');
      if (descEl) document.getElementById('f-description-count').textContent = descEl.value.length;
    }
  }

  const serverMsg = document.getElementById('sa-server-msg');
  if (serverMsg && serverMsg.dataset.ok === '1') {
    localStorage.removeItem('shop_apply_draft');
  } else {
    restoreDraft();
  }

  showStep(1);
  </script>
</body>
</html>
