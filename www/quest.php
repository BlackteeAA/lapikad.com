<?php
require_once "_auth.php";

$userId  = intval($_SESSION["user_id"]);
$questId = intval($_GET["id"] ?? 0);
$msg     = "";
$msgType = "bad";

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name, p.id AS place_id
    FROM quests q JOIN places p ON p.id=q.place_id WHERE q.id=?
");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) redirect("places.php");

$stmt = $conn->prepare("SELECT id, photo_url FROM user_quests WHERE user_id=? AND quest_id=?");
$stmt->bind_param("ii", $userId, $questId);
$stmt->execute();
$doneRow     = $stmt->get_result()->fetch_assoc();
$alreadyDone = (bool)$doneRow;
$myPhoto     = $doneRow["photo_url"] ?? null;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$alreadyDone) {
    if (!csrf_verify()) redirect("places.php");

    $inputCode  = strtoupper(trim($_POST["target_code"] ?? ""));
    $targetCode = strtoupper($quest["target_code"] ?? "");
    $isCheckin  = $quest["quest_type"] === "checkin";

    // Validate
    $valid = $isCheckin || $inputCode === $targetCode;

    if ($isCheckin && !isset($_FILES["checkin_photo"]) || ($isCheckin && $_FILES["checkin_photo"]["error"] !== UPLOAD_ERR_OK)) {
        if ($isCheckin) {
            $msg = "กรุณาถ่ายรูปหรือเลือกรูปเป็นหลักฐาน";
            $valid = false;
        }
    }

    $photoUrl = null;
    if ($valid && $isCheckin && isset($_FILES["checkin_photo"]) && $_FILES["checkin_photo"]["error"] === UPLOAD_ERR_OK) {
        $ext     = strtolower(pathinfo($_FILES["checkin_photo"]["name"], PATHINFO_EXTENSION));
        $allowed = ["jpg","jpeg","png","webp","heic","heif"];
        if (!in_array($ext, $allowed) || $_FILES["checkin_photo"]["size"] > 10 * 1024 * 1024) {
            $msg   = "ไฟล์ต้องเป็นรูปภาพ ขนาดไม่เกิน 10MB";
            $valid = false;
        } else {
            $fname    = "ci_" . $userId . "_" . $questId . "_" . time() . "." . $ext;
            $dest     = __DIR__ . "/assets/images/checkins/" . $fname;
            if (move_uploaded_file($_FILES["checkin_photo"]["tmp_name"], $dest)) {
                $photoUrl = "assets/images/checkins/" . $fname;
            } else {
                $msg   = "อัพโหลดรูปไม่สำเร็จ กรุณาลองใหม่";
                $valid = false;
            }
        }
    }

    if ($valid) {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id, photo_url) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $userId, $questId, $photoUrl);
            if (!$stmt->execute()) throw new Exception();

            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $quest["reward_points"], $userId);
            if (!$stmt->execute()) throw new Exception();

            $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, NULL, ?, ?)");
            $reason = "ทำภารกิจ: " . $quest["title"];
            $stmt->bind_param("iis", $userId, $quest["reward_points"], $reason);
            if (!$stmt->execute()) throw new Exception();

            $conn->commit();
            $alreadyDone = true;
            $myPhoto     = $photoUrl;
            $msg         = "ภารกิจสำเร็จ! ได้รับ " . $quest["reward_points"] . " คะแนน";
            $msgType     = "good";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "เกิดข้อผิดพลาด กรุณาลองใหม่";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($quest["title"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .q-card {
      background:#fff; border-radius:24px; padding:20px;
      box-shadow:0 2px 10px rgba(15,23,42,.06); margin-bottom:14px;
    }

    .q-eyebrow { font-size:12px; color:#2563eb; font-weight:600; margin:0 0 6px; }
    .q-title   { font-size:22px; font-weight:700; color:#0f172a; margin:0 0 8px; }
    .q-desc    { font-size:14px; color:#64748b; margin:0; line-height:1.6; }

    .q-reward {
      display:flex; align-items:center; gap:10px;
      background:#eff6ff; border-radius:14px; padding:14px 16px; margin:16px 0 0;
    }
    .q-reward svg  { width:24px;height:24px;fill:#f59e0b;flex-shrink:0; }
    .q-reward span { font-size:18px;font-weight:700;color:#2563eb; }
    .q-reward small{ font-size:13px;color:#64748b; }

    .q-alert {
      padding:13px 16px; border-radius:14px; font-size:14px;
      font-weight:500; margin-bottom:14px;
    }
    .q-alert.good { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .q-alert.bad  { background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; }

    /* Photo upload */
    .photo-upload-area {
      border: 2px dashed #bfdbfe;
      border-radius: 18px;
      background: #eff6ff;
      padding: 28px 20px;
      text-align: center;
      cursor: pointer;
      transition: border-color .2s, background .2s;
      position: relative;
    }
    .photo-upload-area:hover { border-color: #2563eb; background: #dbeafe; }
    .photo-upload-area input { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }

    .photo-upload-icon {
      width:56px;height:56px;background:#dbeafe;border-radius:18px;
      display:flex;align-items:center;justify-content:center;margin:0 auto 12px;
    }
    .photo-upload-icon svg { width:28px;height:28px;fill:#2563eb; }

    .photo-upload-area h3 { font-size:15px;font-weight:600;color:#0f172a;margin:0 0 4px; }
    .photo-upload-area p  { font-size:13px;color:#64748b;margin:0; }

    #photo-preview {
      display:none;
      width:100%; border-radius:16px; margin-top:12px;
      max-height:280px; object-fit:cover;
    }

    .q-btn {
      display:block;width:100%;padding:15px;
      background:#2563eb;color:#fff;border:none;border-radius:999px;
      font-family:inherit;font-size:16px;font-weight:600;cursor:pointer;
      margin-top:14px;transition:opacity .2s;
    }
    .q-btn:hover { opacity:.88; }
    .q-btn.ghost {
      background:#fff;color:#2563eb;border:1.5px solid #bfdbfe;margin-top:8px;
    }

    .done-photo {
      width:100%;border-radius:18px;margin-top:14px;
      max-height:280px;object-fit:cover;
      box-shadow:0 4px 16px rgba(15,23,42,.1);
    }

    .back-btn {
      display:inline-flex;align-items:center;gap:6px;
      color:#2563eb;font-size:14px;font-weight:500;
      text-decoration:none;margin-bottom:14px;
    }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="place.php?id=<?= e($quest["place_id"]) ?>" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <!-- Quest info card -->
    <div class="q-card">
      <p class="q-eyebrow"><?= e($quest["place_name"]) ?></p>
      <h1 class="q-title"><?= e($quest["title"]) ?></h1>
      <p class="q-desc"><?= e($quest["description"]) ?></p>

      <div class="q-reward">
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        <div>
          <span>+<?= e($quest["reward_points"]) ?> คะแนน</span><br>
          <small>รางวัลเมื่อสำเร็จ</small>
        </div>
      </div>
    </div>

    <?php if ($msg): ?>
      <div class="q-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($alreadyDone): ?>
      <!-- Done state -->
      <div class="q-card" style="text-align:center">
        <div style="width:64px;height:64px;background:#dcfce7;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
          <svg viewBox="0 0 24 24" style="width:32px;height:32px;fill:#16a34a"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
        <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin:0 0 6px">ภารกิจสำเร็จแล้ว</h2>
        <p style="font-size:14px;color:#64748b;margin:0">คุณได้รับ <?= e($quest["reward_points"]) ?> คะแนนจากภารกิจนี้</p>

        <?php if ($myPhoto): ?>
          <img src="<?= e($myPhoto) ?>" alt="Check-in photo" class="done-photo">
        <?php endif; ?>

        <a href="place.php?id=<?= e($quest["place_id"]) ?>" class="q-btn" style="display:block;text-decoration:none;text-align:center;margin-top:16px">
          กลับหน้าสถานที่
        </a>
      </div>

    <?php elseif ($quest["quest_type"] === "checkin"): ?>
      <!-- Photo check-in -->
      <div class="q-card">
        <h2 style="font-size:16px;font-weight:700;color:#0f172a;margin:0 0 4px">ถ่ายรูปเป็นหลักฐาน</h2>
        <p style="font-size:13px;color:#64748b;margin:0 0 16px">ถ่ายรูปตอนอยู่ที่สถานที่เพื่อยืนยันการเช็คอิน</p>

        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

          <div class="photo-upload-area" id="upload-area">
            <input type="file" name="checkin_photo" id="photo-input"
                   accept="image/*" capture="environment" required>
            <div class="photo-upload-icon">
              <svg viewBox="0 0 24 24"><path d="M12 15.2A3.2 3.2 0 1 1 12 8.8a3.2 3.2 0 0 1 0 6.4zM9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9zm3 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10z"/></svg>
            </div>
            <h3>กดเพื่อถ่ายรูป</h3>
            <p>หรือเลือกรูปจากคลัง · JPG, PNG ขนาดไม่เกิน 10MB</p>
          </div>

          <img id="photo-preview" alt="preview">

          <button class="q-btn" type="submit">
            ยืนยันเช็คอิน
          </button>
        </form>
      </div>

    <?php elseif ($quest["quest_type"] === "qr"): ?>
      <div class="q-card">
        <a class="q-btn" href="scan.php?id=<?= e($quest["id"]) ?>" style="text-decoration:none;text-align:center">
          เปิดกล้องสแกน QR
        </a>
        <form method="post" class="form" style="margin-top:12px">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input name="target_code" placeholder="หรือกรอกรหัส LAPIKAD:...">
          <button class="q-btn ghost" type="submit">ยืนยันด้วยรหัส</button>
        </form>
      </div>

    <?php else: ?>
      <div class="q-card">
        <form method="post" class="form">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input name="target_code" placeholder="กรอกรหัสภารกิจ" required>
          <button class="q-btn" type="submit">ยืนยันภารกิจ</button>
        </form>
      </div>
    <?php endif; ?>

  </main>

  <script>
  const input = document.getElementById('photo-input');
  const preview = document.getElementById('photo-preview');
  const area = document.getElementById('upload-area');

  if (input) {
    input.addEventListener('change', () => {
      const file = input.files[0];
      if (!file) return;
      const url = URL.createObjectURL(file);
      preview.src = url;
      preview.style.display = 'block';
      area.style.borderColor = '#22c55e';
      area.style.background  = '#f0fdf4';
    });
  }
  </script>
</body>
</html>
