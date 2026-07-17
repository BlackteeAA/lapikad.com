<?php
require_once "_shop.php";

$placeId = intval($shopPlace["id"]);
$rawCode = trim($_GET["code"] ?? $_POST["code"] ?? "");
$code    = extractQrCode($rawCode);

$row       = null;
$status    = null;
$notFound  = false;
$wrongShop = false;

if ($code !== "") {
    $stmt = $conn->prepare("
        SELECT sr.*, r.name AS reward_name, r.image_url, u.name AS customer_name
        FROM shop_redemptions sr
        JOIN rewards r ON r.id = sr.reward_id
        JOIN users u ON u.id = sr.user_id
        WHERE sr.code=?
    ");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        $notFound = true;
    } elseif (intval($row["place_id"]) !== $placeId) {
        $wrongShop = true;
    } else {
        $status = expireIfNeeded($conn, $row);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && $row && !$wrongShop && $status === "pending") {
    if (!csrf_verify()) redirect("shop_scan_redeem.php");

    try {
        $conn->begin_transaction();

        $lock = $conn->prepare("SELECT status FROM shop_redemptions WHERE id=? FOR UPDATE");
        $lock->bind_param("i", $row["id"]);
        $lock->execute();
        $lockRow = $lock->get_result()->fetch_assoc();

        if ($lockRow && $lockRow["status"] === "pending") {
            $confirmerId = intval($_SESSION["user_id"]);
            $upd = $conn->prepare("UPDATE shop_redemptions SET status='completed', confirmed_at=NOW(), confirmed_by=? WHERE id=?");
            $upd->bind_param("ii", $confirmerId, $row["id"]);
            $upd->execute();
            $status = "completed";
        } else {
            $status = $lockRow["status"] ?? $status;
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>ยืนยันแลกของรางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Kanit', sans-serif;
      min-height: 100vh;
      background:
        linear-gradient(rgba(10,20,50,.65), rgba(10,20,50,.65)),
        url("assets/images/lapikadbg.png") center/cover no-repeat fixed;
      display: flex; align-items: center; justify-content: center; padding: 20px;
    }

    .rc-wrap {
      width: 100%; max-width: 390px; background: #fff; border-radius: 28px;
      padding: 28px 22px 22px; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,.35);
    }

    .rc-close {
      position: absolute; top: 16px; right: 16px; width: 30px; height: 30px;
      background: #f1f5f9; border-radius: 50%; display: flex; align-items: center; justify-content: center;
      text-decoration: none; color: #64748b; font-size: 16px; font-weight: 700;
    }

    .rc-icon-wrap { text-align: center; margin-bottom: 14px; }
    .rc-icon {
      width: 72px; height: 72px; border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
    }
    .rc-icon.success { background: #22c55e; }
    .rc-icon.fail    { background: #ef4444; }
    .rc-icon.wait    { background: #2563eb; }
    .rc-icon svg { width: 36px; height: 36px; fill: #fff; }

    .rc-title { font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; margin-bottom: 4px; }
    .rc-sub   { font-size: 13px; color: #64748b; text-align: center; margin-bottom: 18px; font-weight: 400; }

    .customer-card {
      background: #f8fafc; border-radius: 16px; padding: 14px; margin-bottom: 14px;
      display: flex; align-items: center; gap: 12px;
    }
    .customer-card .avatar {
      width: 44px; height: 44px; border-radius: 50%;
      background: linear-gradient(135deg,#2563eb,#7c3aed); color: #fff;
      display: flex; align-items: center; justify-content: center; font-weight: 700; flex-shrink: 0;
    }
    .customer-card strong { display: block; font-size: 14px; font-weight: 700; color: #0f172a; }
    .customer-card span { font-size: 12px; color: #64748b; }

    .reward-row {
      display: flex; align-items: center; gap: 12px;
      background: #eff6ff; border-radius: 14px; padding: 12px 14px; margin-bottom: 18px;
    }
    .reward-row img { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
    .reward-row .ph {
      width: 48px; height: 48px; border-radius: 10px; background: #dbeafe; flex-shrink: 0;
      display: flex; align-items: center; justify-content: center;
    }
    .reward-row > div { flex: 1; min-width: 0; }
    .reward-row strong { display: block; font-size: 14px; font-weight: 700; color: #0f172a; }
    .reward-row span { font-size: 13px; color: #2563eb; font-weight: 600; }

    .rc-btn-primary {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 15px; background: #2563eb; color: #fff;
      border: none; border-radius: 999px; font-family: 'Kanit',sans-serif; font-size: 15px; font-weight: 600;
      cursor: pointer; text-decoration: none; margin-bottom: 10px;
    }
    .rc-btn-ghost {
      display: block; width: 100%; padding: 14px; background: #fff; color: #374151;
      border: 1.5px solid #e2e8f0; border-radius: 999px; font-family: 'Kanit',sans-serif;
      font-size: 15px; font-weight: 500; cursor: pointer; text-decoration: none; text-align: center;
    }

    .rc-fail-msg {
      background: #fff1f2; border-radius: 14px; padding: 14px; text-align: center;
      color: #e11d48; font-size: 14px; margin-bottom: 16px;
    }

    .manual-form { display: flex; gap: 8px; margin-top: 16px; }
    .manual-form input {
      flex: 1; border: 1.5px solid #e2e8f0; border-radius: 12px; padding: 12px;
      font-family: inherit; font-size: 14px; text-transform: uppercase;
    }
    .manual-form button {
      background: #f1f5f9; color: #374151; border: none; border-radius: 12px;
      padding: 0 18px; font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="rc-wrap">
    <a href="shop.php" class="rc-close">✕</a>

    <?php if ($code === "" || $notFound): ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon fail">
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">ไม่พบรหัสนี้</h1>
      <div class="rc-fail-msg">รหัสไม่ถูกต้อง หรือหมดอายุไปแล้ว</div>

    <?php elseif ($wrongShop): ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon fail">
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">ไม่ใช่ QR ของร้านคุณ</h1>
      <div class="rc-fail-msg">รหัสนี้เป็นของร้านอื่น</div>

    <?php elseif ($status === "completed"): ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon success">
          <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">แลกสำเร็จ!</h1>
      <p class="rc-sub">หักแต้มร้านเรียบร้อยแล้ว</p>
      <div class="customer-card">
        <div class="avatar"><?= e(mb_substr($row["customer_name"], 0, 1, "UTF-8")) ?></div>
        <div>
          <strong><?= e($row["customer_name"]) ?></strong>
          <span>ลูกค้า</span>
        </div>
      </div>
      <div class="reward-row">
        <?php if ($row["image_url"]): ?>
          <img src="<?= e($row["image_url"]) ?>" alt="">
        <?php else: ?>
          <div class="ph"></div>
        <?php endif; ?>
        <div>
          <strong><?= e($row["reward_name"]) ?></strong>
          <span>-<?= number_format($row["points_cost"]) ?> คะแนน</span>
        </div>
      </div>
      <a href="shop_scan_redeem.php" class="rc-btn-primary">สแกนต่อ</a>
      <a href="shop.php" class="rc-btn-ghost">กลับหน้าร้าน</a>

    <?php elseif ($status === "expired"): ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon fail">
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">หมดเวลาแล้ว</h1>
      <div class="rc-fail-msg">ลูกค้าต้องกดแลกใหม่</div>

    <?php elseif ($status === "cancelled"): ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon fail">
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">ลูกค้ายกเลิกแล้ว</h1>

    <?php else: // pending ?>
      <div class="rc-icon-wrap">
        <div class="rc-icon wait">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2zm0-8h-2V7h2z"/></svg>
        </div>
      </div>
      <h1 class="rc-title">ยืนยันการแลก</h1>
      <p class="rc-sub">ตรวจสอบข้อมูลก่อนกดยืนยัน</p>

      <div class="customer-card">
        <div class="avatar"><?= e(mb_substr($row["customer_name"], 0, 1, "UTF-8")) ?></div>
        <div>
          <strong><?= e($row["customer_name"]) ?></strong>
          <span>ลูกค้า</span>
        </div>
      </div>

      <div class="reward-row">
        <?php if ($row["image_url"]): ?>
          <img src="<?= e($row["image_url"]) ?>" alt="">
        <?php else: ?>
          <div class="ph"></div>
        <?php endif; ?>
        <div>
          <strong><?= e($row["reward_name"]) ?></strong>
          <span>-<?= number_format($row["points_cost"]) ?> คะแนน</span>
        </div>
      </div>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="code" value="<?= e($code) ?>">
        <button type="submit" class="rc-btn-primary" style="border:none">ยืนยันมอบของรางวัล</button>
      </form>
      <a href="shop_scan_redeem.php" class="rc-btn-ghost">ยกเลิก</a>
    <?php endif; ?>

    <?php if ($code === "" || $notFound): ?>
      <form method="get" class="manual-form">
        <input type="text" name="code" placeholder="กรอกรหัสด้วยมือ" maxlength="20">
        <button type="submit">ค้นหา</button>
      </form>
      <a href="shop_scan_redeem.php" class="rc-btn-ghost" style="margin-top:14px">สแกนใหม่</a>
    <?php endif; ?>
  </div>
</body>
</html>
