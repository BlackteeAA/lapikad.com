<?php
require_once "_auth.php";

$userId   = intval($_SESSION["user_id"]);
$rewardId = intval($_GET["reward_id"] ?? $_POST["reward_id"] ?? 0);
$msg      = "";

$stmt = $conn->prepare("
    SELECT r.*, p.name AS place_name, p.owner_user_id
    FROM rewards r JOIN places p ON p.id = r.place_id
    WHERE r.id=?
");
$stmt->bind_param("i", $rewardId);
$stmt->execute();
$reward = $stmt->get_result()->fetch_assoc();

if (!$reward || $reward["owner_user_id"] === null) redirect("places.php");

$placeId = intval($reward["place_id"]);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_redeem.php?place_id=$placeId");

    try {
        $conn->begin_transaction();

        // Lock user_shop_points before rewards, matching expireIfNeeded()'s lock
        // order, so the two transactions can never deadlock on these two tables.
        $lockP = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=? FOR UPDATE");
        $lockP->bind_param("ii", $userId, $placeId);
        $lockP->execute();
        $pLock = $lockP->get_result()->fetch_assoc();

        $lockR = $conn->prepare("SELECT stock, cost_points FROM rewards WHERE id=? FOR UPDATE");
        $lockR->bind_param("i", $rewardId);
        $lockR->execute();
        $rLock = $lockR->get_result()->fetch_assoc();

        if (!$rLock || $rLock["stock"] <= 0) {
            throw new Exception("out_of_stock");
        }
        if (!$pLock || intval($pLock["points"]) < intval($rLock["cost_points"])) {
            throw new Exception("insufficient_points");
        }

        $cost = intval($rLock["cost_points"]);

        $upd1 = $conn->prepare("UPDATE user_shop_points SET points = points - ? WHERE user_id=? AND place_id=?");
        $upd1->bind_param("iii", $cost, $userId, $placeId);
        if (!$upd1->execute()) throw new Exception("error");

        $upd2 = $conn->prepare("UPDATE rewards SET stock = stock - 1 WHERE id=?");
        $upd2->bind_param("i", $rewardId);
        if (!$upd2->execute()) throw new Exception("error");

        $code      = generateRedemptionCode();
        $expiresAt = date("Y-m-d H:i:s", time() + 60 * REDEMPTION_EXPIRY_MINUTES);

        $ins = $conn->prepare("
            INSERT INTO shop_redemptions (code, user_id, place_id, reward_id, points_cost, status, expires_at)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ");
        $ins->bind_param("siiiss", $code, $userId, $placeId, $rewardId, $cost, $expiresAt);
        if (!$ins->execute()) throw new Exception("error");

        $conn->commit();
        redirect("redeem_wait.php?code=" . urlencode($code));
    } catch (Exception $e) {
        $conn->rollback();
        $msg = match ($e->getMessage()) {
            "out_of_stock"        => "ของรางวัลหมดแล้ว",
            "insufficient_points" => "คะแนนไม่พอ",
            default                => "เกิดข้อผิดพลาด กรุณาลองใหม่",
        };
    }
}

$stmt = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=?");
$stmt->bind_param("ii", $userId, $placeId);
$stmt->execute();
$balRow    = $stmt->get_result()->fetch_assoc();
$myPoints  = $balRow ? intval($balRow["points"]) : 0;
$afterPts  = $myPoints - intval($reward["cost_points"]);
$canRedeem = $myPoints >= $reward["cost_points"] && $reward["stock"] > 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ยืนยันการแลก | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.bad { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }

    .confirm-card { background:#fff;border-radius:20px;padding:20px;box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:16px; }

    .rw-img { width:100%;aspect-ratio:16/10;border-radius:16px;overflow:hidden;background:#eff6ff;margin-bottom:16px; }
    .rw-img img { width:100%;height:100%;object-fit:cover;display:block; }
    .rw-img-ph { width:100%;height:100%;display:flex;align-items:center;justify-content:center; }

    .confirm-card h1 { font-size:18px;font-weight:700;color:#0f172a;margin:0 0 4px; }
    .confirm-card .place { font-size:13px;color:#64748b;margin:0 0 16px; }

    .confirm-row {
      display:flex;align-items:center;justify-content:space-between;
      padding:12px 0;border-top:1px solid #f1f5f9;font-size:14px;
    }
    .confirm-row:first-of-type { border-top:none; }
    .confirm-row .lbl { color:#64748b; }
    .confirm-row .val { font-weight:700;color:#0f172a; }
    .confirm-row .val.cost { color:#e11d48; }
    .confirm-row .val.after { color:#16a34a; }

    .confirm-btn {
      display:block;width:100%;background:#2563eb;color:#fff;border:none;border-radius:999px;
      padding:15px;font-family:inherit;font-size:16px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .confirm-btn:disabled { background:#e2e8f0;color:#94a3b8; }
    .confirm-btn:not(:disabled):hover { opacity:.85; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="shop_redeem.php?place_id=<?= $placeId ?>" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <h1 style="font-size:20px;font-weight:700;color:#0f172a;margin:0 0 14px">ยืนยันการแลก</h1>

    <?php if ($msg): ?>
      <div class="adm-alert bad"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="confirm-card">
      <div class="rw-img">
        <?php if ($reward["image_url"]): ?>
          <img src="<?= e($reward["image_url"]) ?>" alt="<?= e($reward["name"]) ?>">
        <?php else: ?>
          <div class="rw-img-ph">
            <svg viewBox="0 0 24 24" style="width:48px;height:48px;fill:#2563eb"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
          </div>
        <?php endif; ?>
      </div>
      <h1><?= e($reward["name"]) ?></h1>
      <p class="place">ร้าน <?= e($reward["place_name"]) ?></p>

      <div class="confirm-row">
        <span class="lbl">ใช้แต้มร้าน</span>
        <span class="val cost">-<?= number_format($reward["cost_points"]) ?></span>
      </div>
      <div class="confirm-row">
        <span class="lbl">แต้มร้านคงเหลือ</span>
        <span class="val"><?= number_format($myPoints) ?></span>
      </div>
      <div class="confirm-row">
        <span class="lbl">หลังแลกจะเหลือ</span>
        <span class="val after"><?= number_format(max(0, $afterPts)) ?></span>
      </div>
    </div>

    <p style="font-size:12px;color:#94a3b8;text-align:center;margin:0 0 16px">แต้มจะถูกหักทันทีเมื่อกดยืนยัน กรุณานำ QR Code ไปให้ร้านสแกนภายใน <?= REDEMPTION_EXPIRY_MINUTES ?> นาที ไม่งั้นแต้มจะคืนอัตโนมัติ</p>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="reward_id" value="<?= $rewardId ?>">
      <button class="confirm-btn" type="submit" <?= $canRedeem ? '' : 'disabled' ?>>
        <?= $canRedeem ? "ยืนยันแลกของรางวัล" : ($reward["stock"] <= 0 ? "ของรางวัลหมดแล้ว" : "คะแนนไม่พอ") ?>
      </button>
    </form>

  </main>
</body>
</html>
