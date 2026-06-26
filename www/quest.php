<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$questId = intval($_GET["id"] ?? 0);
$msg = "";

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name, p.id AS place_id
    FROM quests q
    JOIN places p ON p.id=q.place_id
    WHERE q.id=?
");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) {
    redirect("places.php");
}

$stmt = $conn->prepare("SELECT id FROM user_quests WHERE user_id=? AND quest_id=?");
$stmt->bind_param("ii", $userId, $questId);
$stmt->execute();
$alreadyDone = (bool)$stmt->get_result()->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$alreadyDone) {
    $inputCode = strtoupper(trim($_POST["target_code"] ?? ""));
    $targetCode = strtoupper($quest["target_code"] ?? "");

    if ($quest["quest_type"] === "checkin" || $inputCode === $targetCode) {
        $conn->begin_transaction();

        $stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $questId);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
        $stmt->bind_param("ii", $quest["reward_points"], $userId);
        $stmt->execute();

        $conn->commit();

        $alreadyDone = true;
        $msg = "ภารกิจสำเร็จ คุณได้รับ " . $quest["reward_points"] . " คะแนน";
    } else {
        $msg = "รหัสภารกิจไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ภารกิจ | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body>
  <main class="app">
    <?php include "includes/topbar.php"; ?>

    <a class="back" href="place.php?id=<?= e($quest["place_id"]) ?>">กลับ</a>

    <section class="panel">
      <p class="eyebrow"><?= e($quest["place_name"]) ?></p>
      <h1><?= e($quest["title"]) ?></h1>
      <p class="muted"><?= e($quest["description"]) ?></p>

      <div class="reward-box">
        <p class="muted">รางวัลภารกิจ</p>
        <h2>+<?= e($quest["reward_points"]) ?> คะแนน</h2>
      </div>

      <?php if ($msg): ?>
        <div class="alert <?= $alreadyDone ? "good" : "bad" ?>"><?= e($msg) ?></div>
      <?php endif; ?>

      <?php if ($alreadyDone): ?>
        <div class="alert good">คุณทำภารกิจนี้สำเร็จแล้ว</div>
      <?php else: ?>
        <?php if ($quest["quest_type"] === "qr"): ?>
          <a class="btn" href="scan.php?id=<?= e($quest["id"]) ?>">เปิดกล้อง</a>
          <form method="post" class="form manual-code">
            <input name="target_code" placeholder="หรือกรอก QR ของล่าพิกัด.com เช่น LAPIKAD:TEMPLE-2026">
            <button class="btn ghost" type="submit">ยืนยันด้วยรหัส</button>
          </form>
        <?php else: ?>
          <form method="post" class="form">
            <?php if ($quest["quest_type"] === "checkin"): ?>
              <p class="muted">ต้นแบบนี้ใช้ปุ่มยืนยันแทน GPS จริง</p>
            <?php else: ?>
              <input name="target_code" placeholder="กรอกรหัสภารกิจ" required>
            <?php endif; ?>
            <button class="btn" type="submit">ยืนยันภารกิจ</button>
          </form>
        <?php endif; ?>
      <?php endif; ?>
    </section>
  </main>
</body>
</html>
