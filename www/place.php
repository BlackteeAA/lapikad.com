<?php
require_once "_auth.php";

$placeId = intval($_GET["id"] ?? 1);
$userId = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT * FROM places WHERE id=?");
$stmt->bind_param("i", $placeId);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) {
    redirect("places.php");
}

$stmt = $conn->prepare("
    SELECT q.*,
    CASE WHEN uq.id IS NULL THEN 0 ELSE 1 END AS done
    FROM quests q
    LEFT JOIN user_quests uq ON uq.quest_id=q.id AND uq.user_id=?
    WHERE q.place_id=?
    ORDER BY q.id
");
$stmt->bind_param("ii", $userId, $placeId);
$stmt->execute();
$quests = $stmt->get_result();

$total = 0;
$done = 0;
$questRows = [];
while ($row = $quests->fetch_assoc()) {
    $questRows[] = $row;
    $total++;
    if ($row["done"]) $done++;
}
$percent = $total > 0 ? round(($done / $total) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($place["name"]) ?> | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <main class="app">
    <?php include "_nav.php"; ?>

    <a class="back" href="places.php">กลับ</a>

    <section class="panel">
      <p class="eyebrow">สถานที่</p>
      <h1><?= e($place["name"]) ?></h1>
      <p class="muted"><?= e($place["description"]) ?></p>

      <div class="progress"><div style="width: <?= e($percent) ?>%"></div></div>
      <p class="meta">ความคืบหน้า <?= e($done) ?> / <?= e($total) ?> ภารกิจ</p>
    </section>

    <h3 class="section-title">รายการภารกิจ</h3>

    <?php foreach ($questRows as $quest): ?>
      <section class="panel quest-card">
        <p class="eyebrow"><?= $quest["done"] ? "สำเร็จแล้ว" : "ยังไม่สำเร็จ" ?></p>
        <h3><?= e($quest["title"]) ?></h3>
        <p class="muted"><?= e($quest["description"]) ?></p>
        <p class="meta">+<?= e($quest["reward_points"]) ?> คะแนน</p>
        <a class="btn <?= $quest["done"] ? "ghost" : "" ?>" href="quest.php?id=<?= e($quest["id"]) ?>">
          <?= $quest["done"] ? "ดูภารกิจ" : "เริ่มภารกิจ" ?>
        </a>
      </section>
    <?php endforeach; ?>
  </main>
</body>
</html>
