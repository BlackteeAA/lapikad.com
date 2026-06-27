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
        $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
        $stmt->bind_param("ii", $userId, $quest["id"]);
        $stmt->execute();
        $alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

        if ($alreadyDone) {
            $success = true;
            $title = "ทำภารกิจแล้ว";
            $message = "คุณได้รับคะแนนจาก QR นี้ไปแล้ว";
        } else {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $quest["id"]);
            $stmt->execute();

            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $quest["reward_points"], $userId);
            $stmt->execute();

            $reason = "สแกน QR Code: " . $quest["title"];

            $stmt = $conn->prepare("
                INSERT INTO point_logs (user_id, admin_id, points, reason)
                VALUES (?, NULL, ?, ?)
            ");
            $stmt->bind_param("iis", $userId, $quest["reward_points"], $reason);
            $stmt->execute();

            $conn->commit();

            $success = true;
            $title = "สแกนสำเร็จ";
            $message = "คุณได้รับ " . $quest["reward_points"] . " คะแนน";
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