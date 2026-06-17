<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$questId = intval($_POST["quest_id"] ?? 0);
$rawCode = trim($_POST["scanned_code"] ?? "");
$scannedCode = strtoupper($rawCode);

$stmt = $conn->prepare("SELECT * FROM quests WHERE id=?");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) {
    redirect("places.php");
}

$targetCode = strtoupper($quest["target_code"]);
$validWebsiteCodes = [
    "LAPIKAD:" . $targetCode,
    "LA-PIKAD:" . $targetCode,
    "https://ล่าพิกัด.com/quest/" . $targetCode,
    "https://lapikad.com/quest/" . $targetCode
];

$isWebsiteQr = false;
foreach ($validWebsiteCodes as $validCode) {
    if ($scannedCode === strtoupper($validCode)) {
        $isWebsiteQr = true;
        break;
    }
}

$stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
$stmt->bind_param("ii", $userId, $questId);
$stmt->execute();
$alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

$success = false;
$title = "QR Code ไม่ถูกต้อง";
$message = "QR Code นี้ไม่ใช่ QR ของล่าพิกัด.com หรือไม่ตรงกับภารกิจนี้";
$primaryLink = "scan.php?id=" . $questId;
$primaryText = "สแกนอีกครั้ง";

if (!$isWebsiteQr) {
    $success = false;
} elseif ($alreadyDone) {
    $success = true;
    $title = "ทำภารกิจแล้ว";
    $message = "คุณได้รับคะแนนจากภารกิจนี้ไปแล้ว";
    $primaryLink = "dashboard.php";
    $primaryText = "กลับหน้าหลัก";
} else {
    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $userId, $questId);
    $stmt->execute();

    $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
    $stmt->bind_param("ii", $quest["reward_points"], $userId);
    $stmt->execute();

    $reason = "ทำภารกิจ QR Code: " . $quest["title"];
    $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, NULL, ?, ?)");
    $stmt->bind_param("iis", $userId, $quest["reward_points"], $reason);
    $stmt->execute();

    $conn->commit();

    $success = true;
    $title = "ภารกิจสำเร็จ";
    $message = "คุณได้รับ " . $quest["reward_points"] . " คะแนน";
    $primaryLink = "dashboard.php";
    $primaryText = "กลับหน้าหลัก";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="<?= $success ? "success-screen" : "fail-screen" ?>">
  <main class="result-page">
    <section class="result-card">
      <div class="result-mark"><?= $success ? "✓" : "!" ?></div>
      <h1><?= e($title) ?></h1>
      <p><?= e($message) ?></p>

      <a class="btn result-btn" href="<?= e($primaryLink) ?>"><?= e($primaryText) ?></a>
      <a class="btn ghost result-btn" href="quest.php?id=<?= e($questId) ?>">กลับภารกิจ</a>
    </section>
  </main>
</body>
</html>
