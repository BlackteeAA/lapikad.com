<?php
require_once "_auth.php";

$places = $conn->query("SELECT p.*, COUNT(q.id) AS quest_count FROM places p LEFT JOIN quests q ON q.place_id=p.id GROUP BY p.id ORDER BY p.id");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สถานที่ | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <main class="app">
    <?php include "_nav.php"; ?>

    <h1>สถานที่ท่องเที่ยว</h1>
    <p class="muted">เลือกสถานที่เพื่อเริ่มทำภารกิจ</p>

    <?php while ($place = $places->fetch_assoc()): ?>
      <section class="panel place-card">
        <h3><?= e($place["name"]) ?></h3>
        <p class="muted"><?= e($place["description"]) ?></p>
        <p class="meta"><?= e($place["location_text"]) ?> · ภารกิจ <?= e($place["quest_count"]) ?> รายการ</p>
        <a class="btn" href="place.php?id=<?= e($place["id"]) ?>">เข้าไปยังสถานที่</a>
      </section>
    <?php endwhile; ?>
  </main>
</body>
</html>
