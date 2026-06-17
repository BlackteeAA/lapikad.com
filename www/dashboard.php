<?php
require_once "_auth.php";

$userId = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT name, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$completed = $conn->query("SELECT COUNT(*) AS total FROM user_quests WHERE user_id=" . intval($userId))->fetch_assoc()["total"];
$places = $conn->query("SELECT p.*, COUNT(q.id) AS quest_count FROM places p LEFT JOIN quests q ON q.place_id=p.id GROUP BY p.id ORDER BY p.id LIMIT 3");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>หน้าหลัก | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <main class="app">
    <?php include "_nav.php"; ?>

    <section class="header-block">
      <h2>สวัสดี, <?= e($user["name"]) ?></h2>
      <p class="muted">ออกตามล่าพิกัดและรับคะแนน</p>
    </section>

    <section class="stats">
      <div class="panel stat">
        <p class="muted">คะแนน</p>
        <h1><?= e($user["points"]) ?></h1>
      </div>
      <div class="panel stat">
        <p class="muted">ภารกิจสำเร็จ</p>
        <h1><?= e($completed) ?></h1>
      </div>
    </section>

    <a class="btn" href="places.php">เริ่มล่าพิกัด</a>

    <h3 class="section-title">สถานที่แนะนำ</h3>

    <?php while ($place = $places->fetch_assoc()): ?>
      <section class="panel place-card">
        <h3><?= e($place["name"]) ?></h3>
        <p class="muted"><?= e($place["description"]) ?></p>
        <p class="meta">ภารกิจ <?= e($place["quest_count"]) ?> รายการ</p>
        <a class="btn ghost" href="place.php?id=<?= e($place["id"]) ?>">ดูรายละเอียด</a>
      </section>
    <?php endwhile; ?>
  </main>
</body>
</html>
