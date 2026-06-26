<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$questId = intval($_POST["quest_id"] ?? 0);
$rawCode = trim($_POST["scanned_code"] ?? "");

function extractQrCode($rawCode) {
    $rawCode = trim($rawCode);

    if ($rawCode === "") {
        return "";
    }

    if (str_starts_with(strtoupper($rawCode), "LAPIKAD:")) {
        return strtoupper(str_replace("LAPIKAD:", "", $rawCode));
    }

    if (filter_var($rawCode, FILTER_VALIDATE_URL)) {
        $parts = parse_url($rawCode);

        if (isset($parts["query"])) {
            parse_str($parts["query"], $query);

            if (isset($query["code"])) {
                return strtoupper(trim($query["code"]));
            }
        }
    }

    return strtoupper($rawCode);
}

$scannedCode = extractQrCode($rawCode);

$stmt = $conn->prepare("SELECT * FROM quests WHERE id=?");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) {
    redirect("places.php");
}

$targetCode = strtoupper($quest["target_code"]);

$success = false;
$title = "QR Code ไม่ถูกต้อง";
$message = "QR Code นี้ไม่ใช่ของล่าพิกัด.com หรือไม่ตรงกับภารกิจนี้";
$primaryLink = "scan.php?id=" . $questId;
$primaryText = "สแกนอีกครั้ง";

if ($scannedCode === $targetCode) {
    $stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
    $stmt->bind_param("ii", $userId, $questId);
    $stmt->execute();
    $alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

    if ($alreadyDone) {
        $success = true;
        $title = "ทำภารกิจแล้ว";
        $message = "คุณได้รับคะแนนจากภารกิจนี้ไปแล้ว";
        $primaryLink = "dashboard.php";
        $primaryText = "กลับหน้าหลัก";
    } else {
        try {
            $conn->begin_transaction();

            $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $questId);
            if (!$stmt->execute()) throw new Exception($conn->error);

            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $quest["reward_points"], $userId);
            if (!$stmt->execute()) throw new Exception($conn->error);

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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
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

      <a class="btn result-btn" href="<?= e($primaryLink) ?>">
        <?= e($primaryText) ?>
      </a>

      <a class="btn ghost result-btn" href="quest.php?id=<?= e($questId) ?>">
        กลับภารกิจ
      </a>
    </section>
  </main>
</body>
</html>