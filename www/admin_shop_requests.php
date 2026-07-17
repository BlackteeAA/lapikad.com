<?php
require_once "_admin.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_shop_requests.php");
    $action = $_POST["action"] ?? "";
    $reqId  = intval($_POST["request_id"] ?? 0);

    $stmt = $conn->prepare("SELECT * FROM shop_requests WHERE id=?");
    $stmt->bind_param("i", $reqId);
    $stmt->execute();
    $reqRow = $stmt->get_result()->fetch_assoc();

    if (!$reqRow || $reqRow["status"] !== "pending") {
        $msg = "ไม่พบคำขอ หรือคำขอนี้ถูกตรวจสอบไปแล้ว";
        $msgType = "bad";
    } elseif ($action === "approve") {
        $ownsPlace = $conn->prepare("SELECT id FROM places WHERE owner_user_id=?");
        $ownsPlace->bind_param("i", $reqRow["user_id"]);
        $ownsPlace->execute();
        if ($ownsPlace->get_result()->fetch_assoc()) {
            $msg = "ผู้ใช้นี้เป็นเจ้าของร้านอยู่แล้ว";
            $msgType = "bad";
        } else {
            try {
                $conn->begin_transaction();

                $applicantId  = intval($reqRow["user_id"]);
                $shopName     = $reqRow["shop_name"];
                $shopLoc      = $reqRow["location_text"];
                $shopImg      = $reqRow["image_url"];
                $shopCategory = $reqRow["category"] !== "" && $reqRow["category"] !== null ? $reqRow["category"] : SHOP_QUEST_CATEGORY;
                $shopLat      = $reqRow["lat"];
                $shopLng      = $reqRow["lng"];

                $upd = $conn->prepare("UPDATE users SET role='shop' WHERE id=?");
                $upd->bind_param("i", $applicantId);
                if (!$upd->execute()) throw new Exception();

                $ins = $conn->prepare("INSERT INTO places (name, location_text, image_url, category, lat, lng, owner_user_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $ins->bind_param("ssssddi", $shopName, $shopLoc, $shopImg, $shopCategory, $shopLat, $shopLng, $applicantId);
                if (!$ins->execute()) throw new Exception();

                $adminId = intval($_SESSION["user_id"]);
                $updReq = $conn->prepare("UPDATE shop_requests SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE id=?");
                $updReq->bind_param("ii", $adminId, $reqId);
                if (!$updReq->execute()) throw new Exception();

                $conn->commit();
                $msg = "อนุมัติร้าน \"" . $reqRow["shop_name"] . "\" สำเร็จ";
            } catch (Exception $e) {
                $conn->rollback();
                $msg = "เกิดข้อผิดพลาด กรุณาลองใหม่";
                $msgType = "bad";
            }
        }
    } elseif ($action === "reject") {
        $note = trim($_POST["admin_note"] ?? "");
        $adminId = intval($_SESSION["user_id"]);
        $updReq = $conn->prepare("UPDATE shop_requests SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?");
        $updReq->bind_param("sii", $note, $adminId, $reqId);
        $updReq->execute();
        $msg = "ปฏิเสธคำขอสำเร็จ";
    }
}

$requests = $conn->query("
    SELECT sr.*, u.name AS applicant_name, u.email AS applicant_email
    FROM shop_requests sr
    JOIN users u ON u.id = sr.user_id
    ORDER BY (sr.status='pending') DESC, sr.id DESC
")->fetch_all(MYSQLI_ASSOC);

$pendingRequests  = array_values(array_filter($requests, fn($r) => $r["status"] === "pending"));
$reviewedRequests = array_values(array_filter($requests, fn($r) => $r["status"] !== "pending"));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>คำขอร้านค้า | ล่าพิกัด.com</title>
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

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .sec-head { display:flex;align-items:center;gap:8px;margin:20px 0 10px; }
    .sec-head:first-of-type { margin-top:0; }
    .sec-head h2 { font-size:15px;font-weight:700;color:#0f172a;margin:0; }
    .sec-head .sec-count { font-size:11.5px;font-weight:600;color:#64748b;background:#f1f5f9;padding:2px 9px;border-radius:99px; }

    .row-item { display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9;flex-wrap:wrap; }
    .row-item:last-child { border-bottom:none; }
    .row-item img, .row-item .ph { width:52px;height:52px;border-radius:12px;object-fit:cover;flex-shrink:0;background:#e2e8f0; }
    .row-info { flex:1;min-width:180px; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a;overflow-wrap:anywhere; }
    .row-info span   { font-size:11.5px;color:#94a3b8;display:block;margin-top:2px;overflow-wrap:anywhere; }

    .status-pill { font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:99px;white-space:nowrap; }
    .status-pending  { background:#fef9c3;color:#a16207; }
    .status-approved { background:#f0fdf4;color:#16a34a; }
    .status-rejected { background:#fff1f2;color:#e11d48; }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:8px 14px;border:none;border-radius:999px;
      font-family:inherit;font-size:12.5px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#16a34a;color:#fff; }
    .adm-btn.danger  { background:#fff1f2;color:#e11d48; }
    .adm-btn:hover   { opacity:.85; }
    .adm-btn.primary, .adm-btn.danger { min-width:88px; }

    .row-actions {
      width:100%;display:flex;flex-direction:column;align-items:center;gap:10px;
      margin-top:12px;padding-top:12px;border-top:1px dashed #f1f5f9;
    }
    .row-action-btns { display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap; }
    .reject-form { display:flex;gap:6px; }
    .reject-form input[type=text] {
      width:130px;font-size:12px;padding:8px 10px;border:1px solid #e2e8f0;border-radius:8px;font-family:inherit;text-align:center;
    }
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
      <h1>คำขอสมัครร้านค้า</h1>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php
      function render_shop_request_row(array $r): void {
    ?>
          <div class="row-item">
            <?php if ($r["image_url"]): ?>
              <img src="<?= e($r["image_url"]) ?>" alt="">
            <?php else: ?>
              <div class="ph"></div>
            <?php endif; ?>
            <div class="row-info">
              <strong><?= e($r["shop_name"]) ?><?= !empty($r["shop_name_en"]) ? " (" . e($r["shop_name_en"]) . ")" : "" ?></strong>
              <span><?= e($r["applicant_name"]) ?> · <?= e($r["applicant_email"]) ?></span>
              <?php if ($r["category"]): ?><span><?= e($r["category"]) ?></span><?php endif; ?>
              <?php if (!empty($r["description"])): ?><span><?= e($r["description"]) ?></span><?php endif; ?>
              <?php if ($r["location_text"]): ?><span><?= e($r["location_text"]) ?></span><?php endif; ?>
              <?php if ($r["lat"] !== null && $r["lng"] !== null): ?>
                <span>
                  <?= number_format((float)$r["lat"], 6) ?>, <?= number_format((float)$r["lng"], 6) ?>
                  · <a href="admin_report_location.php?lat=<?= urlencode($r["lat"]) ?>&lng=<?= urlencode($r["lng"]) ?>&radius=300" style="color:#2563eb;font-weight:600">วิเคราะห์ทำเล</a>
                </span>
              <?php endif; ?>
              <?php
                $contactParts = array_filter([
                    !empty($r["phone"]) ? "โทร " . $r["phone"] : null,
                    !empty($r["line_id"]) ? "LINE " . $r["line_id"] : null,
                    !empty($r["facebook_url"]) ? "FB " . $r["facebook_url"] : null,
                    !empty($r["contact_email"]) ? $r["contact_email"] : null,
                ]);
              ?>
              <?php if ($contactParts): ?><span><?= e(implode(" · ", $contactParts)) ?></span><?php endif; ?>
              <?php if (!empty($r["opening_hours"])): ?><span>เปิด-ปิด: <?= e($r["opening_hours"]) ?></span><?php endif; ?>
              <?php if (!empty($r["highlights"])): ?><span>จุดเด่น: <?= e($r["highlights"]) ?></span><?php endif; ?>
              <?php if ($r["status"] === "rejected" && $r["admin_note"]): ?>
                <span style="color:#e11d48">เหตุผล: <?= e($r["admin_note"]) ?></span>
              <?php endif; ?>
            </div>
            <div class="row-actions">
              <span class="status-pill status-<?= e($r["status"]) ?>">
                <?= ["pending"=>"รอตรวจสอบ","approved"=>"อนุมัติแล้ว","rejected"=>"ถูกปฏิเสธ"][$r["status"]] ?>
              </span>
              <?php if ($r["status"] === "pending"): ?>
                <div class="row-action-btns">
                  <form method="post">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="request_id" value="<?= $r["id"] ?>">
                    <button class="adm-btn primary" type="submit" onclick="return confirm('อนุมัติร้าน: <?= e($r["shop_name"]) ?>?')">อนุมัติ</button>
                  </form>
                  <form method="post" class="reject-form">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="request_id" value="<?= $r["id"] ?>">
                    <input type="text" name="admin_note" placeholder="เหตุผล (ถ้ามี)">
                    <button class="adm-btn danger" type="submit">ปฏิเสธ</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
          </div>
    <?php
      }
    ?>

    <div class="sec-head">
      <h2>รอตรวจสอบ</h2>
      <span class="sec-count"><?= count($pendingRequests) ?></span>
    </div>
    <div class="pg-card">
      <?php if (empty($pendingRequests)): ?>
        <div class="row-item"><span style="font-size:13px;color:#94a3b8">ไม่มีคำขอที่รอตรวจสอบ</span></div>
      <?php else: ?>
        <?php foreach ($pendingRequests as $r) render_shop_request_row($r); ?>
      <?php endif; ?>
    </div>

    <div class="sec-head">
      <h2>ตรวจสอบแล้ว</h2>
      <span class="sec-count"><?= count($reviewedRequests) ?></span>
    </div>
    <div class="pg-card">
      <?php if (empty($reviewedRequests)): ?>
        <div class="row-item"><span style="font-size:13px;color:#94a3b8">ยังไม่มีร้านที่ตรวจสอบแล้ว</span></div>
      <?php else: ?>
        <?php foreach ($reviewedRequests as $r) render_shop_request_row($r); ?>
      <?php endif; ?>
    </div>

  </main>
</body>
</html>
