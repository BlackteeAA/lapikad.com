<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT name, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$completed = $conn
  ->query("SELECT COUNT(*) AS total FROM user_quests WHERE user_id=" . $userId)
  ->fetch_assoc()["total"];

$places = $conn->query("
  SELECT 
    p.*, 
    COUNT(q.id) AS quest_count
  FROM places p
  LEFT JOIN quests q ON q.place_id = p.id
  GROUP BY p.id
  ORDER BY p.id
  LIMIT 3
");

$visitedPlaces = $conn
  ->query("
    SELECT COUNT(DISTINCT q.place_id) AS total
    FROM user_quests uq
    JOIN quests q ON q.id = uq.quest_id
    WHERE uq.user_id = " . $userId
  )
  ->fetch_assoc()["total"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>หน้าหลัก | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>

<body>
  <main class="app dashboard-page">
    <?php include "includes/topbar.php"; ?>

    <section class="dashboard-hero">
      <div>
        <p class="eyebrow">Lapikad Dashboard</p>
        <h1>สวัสดี, <?= e($user["name"]) ?></h1>
        <p>ออกตามล่าพิกัด ทำภารกิจ และสะสมคะแนนจากสถานที่จริง</p>
      </div>

      <a class="hero-btn" href="places.php">
        เริ่มล่าพิกัด
      </a>
    </section>

    <section class="stat-grid">
      <div class="stat-card stat-blue">
        <p>คะแนนสะสม</p>
        <h2><?= e($user["points"]) ?></h2>
        <span>คะแนน</span>
      </div>

      <div class="stat-card stat-green">
        <p>ภารกิจสำเร็จ</p>
        <h2><?= e($completed) ?></h2>
        <span>ภารกิจ</span>
      </div>

      <div class="stat-card stat-purple">
        <p>สถานที่เยือน</p>
        <h2><?= e($visitedPlaces) ?></h2>
        <span>แห่ง</span>
      </div>
    </section>

    <section class="quick-actions">
      <a href="places.php" class="quick-card">
        <div class="quick-icon">QR</div>
        <div>
          <strong>สแกน QR</strong>
          <p>รับคะแนนจากจุดภารกิจ</p>
        </div>
      </a>

      <a href="places.php" class="quick-card">
        <div class="quick-icon">MAP</div>
        <div>
          <strong>สถานที่</strong>
          <p>เลือกแหล่งท่องเที่ยว</p>
        </div>
      </a>

      <a href="rewards.php" class="quick-card">
        <div class="quick-icon">RE</div>
        <div>
          <strong>รางวัล</strong>
          <p>แลกคะแนนสะสม</p>
        </div>
      </a>
    </section>

    <div class="section-head">
      <h3>สถานที่แนะนำ</h3>
      <a href="places.php">ดูทั้งหมด</a>
    </div>

    <?php while ($place = $places->fetch_assoc()): ?>
      <section class="modern-place-card">
        <div class="place-cover">
          <div class="place-badge">
            <?= e($place["quest_count"]) ?> ภารกิจ
          </div>
        </div>

        <div class="place-content">
          <h3><?= e($place["name"]) ?></h3>
          <p><?= e($place["description"]) ?></p>

          <a class="btn place-btn" href="place.php?id=<?= e($place["id"]) ?>">
            ดูรายละเอียด
          </a>
        </div>
      </section>
    <?php endwhile; ?>
  </main>
</body>
</html>