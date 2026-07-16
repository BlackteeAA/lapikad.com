<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$questId = intval($_POST["quest_id"] ?? 0);
$rawCode = trim($_POST["scanned_code"] ?? "");

if (!csrf_verify()) redirect("places.php");

$lastAttempt = $_SESSION['last_quest_attempt'] ?? 0;
if (time() - $lastAttempt < 5) redirect("places.php");
$_SESSION['last_quest_attempt'] = time();

$scannedCode = extractQrCode($rawCode);

$stmt = $conn->prepare("SELECT * FROM quests WHERE id=?");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) redirect("places.php");

// Get place info
$placeRow = null;
if ($quest["place_id"]) {
    $sp = $conn->prepare("SELECT name, category, district, province, owner_user_id FROM places WHERE id=?");
    $sp->bind_param("i", $quest["place_id"]);
    $sp->execute();
    $placeRow = $sp->get_result()->fetch_assoc();
}

$targetCode = strtoupper($quest["target_code"]);

$success = false;
$title = "QR Code ไม่ถูกต้อง";
$message = "QR Code นี้ไม่ใช่ของล่าพิกัด.com หรือไม่ตรงกับภารกิจนี้";
$primaryLink = "scan.php?id=" . $questId;
$primaryText = "สแกนอีกครั้ง";

if ($scannedCode === $targetCode) {
    $ownerUserId  = $placeRow["owner_user_id"] ?? null;
    $dailyRefresh = isDailyRefreshQuest($ownerUserId !== null ? intval($ownerUserId) : null);

    if ($dailyRefresh) {
        $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=? AND completed_date=CURDATE()");
    } else {
        $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
    }
    $stmt->bind_param("ii", $userId, $questId);
    $stmt->execute();
    $alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

    if ($alreadyDone) {
        $success = true;
        $title = "ทำภารกิจแล้ว";
        $message = $dailyRefresh
            ? "คุณทำภารกิจนี้ไปแล้ววันนี้ พรุ่งนี้กลับมาทำใหม่ได้"
            : "คุณได้รับคะแนนจากภารกิจนี้ไปแล้ว";
        $primaryLink = "dashboard.php";
        $primaryText = "กลับหน้าหลัก";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id, completed_date) VALUES (?, ?, CURDATE())");
            $stmt->bind_param("ii", $userId, $questId);
            if (!$stmt->execute()) throw new Exception($conn->error);

            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $quest["reward_points"], $userId);
            if (!$stmt->execute()) throw new Exception($conn->error);

            if ($ownerUserId !== null) {
                $stmt = $conn->prepare("
                    INSERT INTO user_shop_points (user_id, place_id, points) VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE points = points + VALUES(points)
                ");
                $stmt->bind_param("iii", $userId, $quest["place_id"], $quest["reward_points"]);
                if (!$stmt->execute()) throw new Exception($conn->error);
            }

            $reason = "ทำภารกิจ QR Code: " . $quest["title"];
            $stmt = $conn->prepare("
                INSERT INTO point_logs (user_id, admin_id, points, reason)
                VALUES (?, NULL, ?, ?)
            ");
            $stmt->bind_param("iis", $userId, $quest["reward_points"], $reason);
            if (!$stmt->execute()) throw new Exception($conn->error);

            $conn->commit();

            $success = true;
            $title = "ภารกิจสำเร็จ";
            $message = "คุณได้รับ " . $quest["reward_points"] . " คะแนน";
            $primaryLink = "dashboard.php";
            $primaryText = "กลับหน้าหลัก";
        } catch (Exception $e) {
            $conn->rollback();
            $title = "เกิดข้อผิดพลาด";
            $message = "ไม่สามารถบันทึกภารกิจได้ กรุณาลองใหม่";
            $primaryLink = "scan.php?id=" . $questId;
            $primaryText = "ลองใหม่";
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
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
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .rc-wrap {
      width: 100%;
      max-width: 390px;
      background: #fff;
      border-radius: 28px;
      padding: 28px 22px 22px;
      position: relative;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      animation: popIn .35s cubic-bezier(.34,1.56,.64,1);
    }

    @keyframes popIn {
      from { transform: scale(.85); opacity: 0; }
      to   { transform: scale(1);   opacity: 1; }
    }

    .rc-close {
      position: absolute;
      top: 16px; right: 16px;
      width: 30px; height: 30px;
      background: #f1f5f9;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      text-decoration: none; color: #64748b; font-size: 16px; font-weight: 700;
    }

    /* Icon */
    .rc-icon-wrap {
      text-align: center;
      margin-bottom: 14px;
      position: relative;
    }

    .rc-icon {
      width: 72px; height: 72px;
      border-radius: 50%;
      display: inline-flex; align-items: center; justify-content: center;
      position: relative; z-index: 1;
    }

    .rc-icon.success { background: #22c55e; }
    .rc-icon.fail    { background: #ef4444; }
    .rc-icon.done    { background: #f59e0b; }

    .rc-icon svg { width: 36px; height: 36px; fill: #fff; }

    /* Sparkles */
    .sparkle {
      position: absolute;
      width: 8px; height: 8px;
      border-radius: 50%;
      animation: sparkle 1.2s ease-out forwards;
    }

    @keyframes sparkle {
      0%   { transform: scale(0) translate(0,0); opacity: 1; }
      100% { transform: scale(1) translate(var(--tx), var(--ty)); opacity: 0; }
    }

    /* Text */
    .rc-title {
      font-size: 26px; font-weight: 700; color: #0f172a;
      text-align: center; margin-bottom: 4px;
    }

    .rc-sub {
      font-size: 14px; color: #64748b;
      text-align: center; margin-bottom: 12px; font-weight: 400;
    }

    .rc-pts {
      display: flex; align-items: center; justify-content: center;
      gap: 8px; margin-bottom: 4px;
    }

    .rc-pts strong {
      font-size: 44px; font-weight: 700; color: #2563eb; line-height: 1;
    }

    .rc-pts svg { width: 32px; height: 32px; fill: #f59e0b; }
    .rc-pts span { font-size: 20px; font-weight: 600; color: #0f172a; }

    .rc-from {
      text-align: center; font-size: 13px; color: #64748b;
      margin-bottom: 14px; font-weight: 300;
    }

    /* Place pill */
    .rc-place {
      background: #f0fdf4;
      border-radius: 14px;
      padding: 10px 14px;
      display: flex; align-items: center; gap: 8px;
      margin-bottom: 12px;
    }

    .rc-place svg { width: 18px; height: 18px; fill: #16a34a; flex-shrink: 0; }
    .rc-place strong { font-size: 14px; font-weight: 700; color: #0f172a; }
    .rc-place span   { font-size: 12px; color: #64748b; display: block; }

    /* Quest row */
    .rc-quest-row {
      display: flex; align-items: center; gap: 12px;
      background: #f8fafc; border-radius: 14px;
      padding: 12px 14px; margin-bottom: 14px;
    }

    .rc-quest-icon {
      width: 40px; height: 40px; border-radius: 12px;
      background: #ede9fe; display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }

    .rc-quest-icon svg { width: 22px; height: 22px; fill: #7c3aed; }

    .rc-quest-row > div { flex: 1; }
    .rc-quest-row strong { display: block; font-size: 13px; font-weight: 700; color: #0f172a; }
    .rc-quest-row span   { font-size: 12px; color: #64748b; }
    .rc-quest-pts { font-size: 14px; font-weight: 700; color: #16a34a; flex-shrink: 0; }

    /* Reminder box */
    .rc-reminder {
      background: #eff6ff;
      border-radius: 16px;
      padding: 14px;
      margin-bottom: 18px;
      display: flex; gap: 12px; align-items: center;
    }

    .rc-reminder-text strong { display: block; font-size: 14px; font-weight: 700; color: #1e40af; margin-bottom: 4px; }
    .rc-reminder-text p { font-size: 12px; color: #3b82f6; font-weight: 300; line-height: 1.4; margin: 0; }

    .rc-reminder-icon {
      width: 48px; height: 48px; flex-shrink: 0;
      background: linear-gradient(135deg,#2563eb,#7c3aed);
      border-radius: 14px; display: flex; align-items: center; justify-content: center;
    }

    .rc-reminder-icon svg { width: 26px; height: 26px; fill: #fff; }

    /* Buttons */
    .rc-btn-primary {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      width: 100%; padding: 15px;
      background: #2563eb; color: #fff;
      border: none; border-radius: 999px;
      font-family: 'Kanit',sans-serif; font-size: 15px; font-weight: 600;
      cursor: pointer; text-decoration: none; margin-bottom: 10px;
    }

    .rc-btn-primary svg { width: 18px; height: 18px; fill: #fff; }

    .rc-btn-ghost {
      display: block; width: 100%; padding: 14px;
      background: #fff; color: #374151;
      border: 1.5px solid #e2e8f0; border-radius: 999px;
      font-family: 'Kanit',sans-serif; font-size: 15px; font-weight: 500;
      cursor: pointer; text-decoration: none; text-align: center;
    }

    /* Fail state */
    .rc-fail-msg {
      background: #fff1f2; border-radius: 14px;
      padding: 14px; text-align: center;
      color: #e11d48; font-size: 14px;
      margin-bottom: 16px;
    }
  </style>
</head>
<body>
  <div class="rc-wrap">
    <a href="places.php" class="rc-close">✕</a>

    <!-- Icon -->
    <div class="rc-icon-wrap">
      <div class="rc-icon <?= $success ? 'success' : ($alreadyDone ?? false ? 'done' : 'fail') ?>">
        <?php if ($success): ?>
          <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <?php elseif ($alreadyDone ?? false): ?>
          <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        <?php else: ?>
          <svg viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
        <?php endif; ?>
      </div>
      <?php if ($success): ?>
        <div class="sparkle" style="top:8px;left:50%;--tx:-50px;--ty:-30px;background:#fbbf24;animation-delay:.1s"></div>
        <div class="sparkle" style="top:8px;left:60%;--tx:40px;--ty:-35px;background:#ef4444;animation-delay:.15s"></div>
        <div class="sparkle" style="top:20px;left:30%;--tx:-40px;--ty:-20px;background:#22c55e;animation-delay:.2s"></div>
        <div class="sparkle" style="top:10px;left:65%;--tx:45px;--ty:-20px;background:#2563eb;animation-delay:.05s"></div>
        <div class="sparkle" style="top:5px;left:45%;--tx:-20px;--ty:-45px;background:#8b5cf6;animation-delay:.12s"></div>
      <?php endif; ?>
    </div>

    <?php if ($success): ?>
      <h1 class="rc-title">ยินดีด้วย!</h1>
      <p class="rc-sub">คุณได้รับคะแนน</p>

      <div class="rc-pts">
        <strong>+<?= $quest["reward_points"] ?></strong>
        <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
        <span>คะแนน</span>
      </div>
      <p class="rc-from">จากการสแกน QR Code สำเร็จ</p>

      <?php if ($placeRow): ?>
        <div class="rc-place">
          <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
          <div>
            <strong><?= e($placeRow["name"]) ?></strong>
            <span><?= e($placeRow["category"] ?? "สถานที่ท่องเที่ยว") ?></span>
          </div>
        </div>
      <?php endif; ?>

      <div class="rc-quest-row">
        <div class="rc-quest-icon">
          <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 14.5v-9l6 4.5-6 4.5z"/></svg>
        </div>
        <div>
          <strong>ภารกิจที่ทำสำเร็จ</strong>
          <span><?= e($quest["title"]) ?></span>
        </div>
        <span class="rc-quest-pts">+<?= $quest["reward_points"] ?> คะแนน</span>
      </div>

      <div class="rc-reminder">
        <div class="rc-reminder-text">
          <strong>อย่าลืม!</strong>
          <p>นำหลักฐานการได้รับรางวัลนี้ไปแสดงให้พนักงานที่ร้านค้า เพื่อรับของรางวัลหรือสิทธิ์ส่วนลด</p>
        </div>
        <div class="rc-reminder-icon">
          <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
        </div>
      </div>

      <a href="<?= $ownerUserId !== null ? "place.php?id=" . $quest["place_id"] : "rewards.php" ?>" class="rc-btn-primary">
        <svg viewBox="0 0 24 24"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5z"/></svg>
        <?= $ownerUserId !== null ? "ดูของรางวัลร้านนี้" : "ดูของรางวัลของฉัน" ?>
      </a>
      <a href="dashboard.php" class="rc-btn-ghost">กลับหน้าหลัก</a>

    <?php else: ?>
      <h1 class="rc-title"><?= e($title) ?></h1>
      <div class="rc-fail-msg"><?= e($message) ?></div>
      <a href="<?= e($primaryLink) ?>" class="rc-btn-primary"><?= e($primaryText) ?></a>
      <a href="places.php" class="rc-btn-ghost">กลับหน้าสถานที่</a>
    <?php endif; ?>
  </div>
</body>
</html>