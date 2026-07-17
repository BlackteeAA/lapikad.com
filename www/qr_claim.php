<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$code = strtoupper(trim($_GET["code"] ?? ""));

$success = false;
$title = "QR Code ไม่ถูกต้อง";
$message = "QR Code นี้ไม่ใช่ของล่าพิกัด.com หรือไม่มีอยู่ในระบบ";

if ($code !== "") {
    $stmt = $conn->prepare("SELECT * FROM quests WHERE target_code = ? AND quest_type = 'qr'");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $quest = $stmt->get_result()->fetch_assoc();

    if ($quest) {
        $ownerUserId = null;
        if ($quest["place_id"]) {
            $sp = $conn->prepare("SELECT owner_user_id FROM places WHERE id=?");
            $sp->bind_param("i", $quest["place_id"]);
            $sp->execute();
            $ownerRow = $sp->get_result()->fetch_assoc();
            $ownerUserId = $ownerRow && $ownerRow["owner_user_id"] !== null ? intval($ownerRow["owner_user_id"]) : null;
        }
        $dailyRefresh = isDailyRefreshQuest($ownerUserId);

        if ($dailyRefresh) {
            $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=? AND completed_date=CURDATE()");
        } else {
            $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
        }
        $stmt->bind_param("ii", $userId, $quest["id"]);
        $stmt->execute();
        $alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

        if ($alreadyDone) {
            $success = true;
            $title = "ทำภารกิจแล้ว";
            $message = $dailyRefresh
                ? "คุณทำภารกิจนี้ไปแล้ววันนี้ พรุ่งนี้กลับมาทำใหม่ได้"
                : "คุณได้รับคะแนนจาก QR นี้ไปแล้ว";
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id, completed_date) VALUES (?, ?, CURDATE())");
                $stmt->bind_param("ii", $userId, $quest["id"]);
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

                $reason = "สแกน QR Code: " . $quest["title"];

                $stmt = $conn->prepare("
                    INSERT INTO point_logs (user_id, admin_id, points, reason)
                    VALUES (?, NULL, ?, ?)
                ");
                $stmt->bind_param("iis", $userId, $quest["reward_points"], $reason);
                if (!$stmt->execute()) throw new Exception($conn->error);

                $conn->commit();

                $success = true;
                $title = "สแกนสำเร็จ";
                $message = "คุณได้รับ " . $quest["reward_points"] . " คะแนน";
            } catch (Exception $e) {
                $conn->rollback();
                $success = false;
                $title = "เกิดข้อผิดพลาด";
                $message = "ไม่สามารถบันทึกภารกิจได้ กรุณาลองใหม่";
            }
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
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body class="<?= $success ? "success-screen" : "fail-screen" ?>">
  <main class="result-page">
    <section class="result-card">
      <div class="result-mark"><?= $success ? "✓" : "!" ?></div>
      <h1><?= e($title) ?></h1>
      <p><?= e($message) ?></p>

      <a class="btn result-btn" href="dashboard.php">กลับหน้าหลัก</a>
    </section>
  </main>
</body>
</html>