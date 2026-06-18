<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $rewardId = intval($_POST["reward_id"] ?? 0);

    $stmt = $conn->prepare("SELECT points FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT * FROM rewards WHERE id=?");
    $stmt->bind_param("i", $rewardId);
    $stmt->execute();
    $reward = $stmt->get_result()->fetch_assoc();

    if (!$reward) {
        $msg = "ไม่พบของรางวัล";
    } elseif ($reward["stock"] <= 0) {
        $msg = "ของรางวัลหมดแล้ว";
    } elseif ($user["points"] < $reward["cost_points"]) {
        $msg = "คะแนนไม่เพียงพอ";
    } else {
        $conn->begin_transaction();

        $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id=?");
        $stmt->bind_param("ii", $reward["cost_points"], $userId);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE rewards SET stock = stock - 1 WHERE id=?");
        $stmt->bind_param("i", $rewardId);
        $stmt->execute();

        $stmt = $conn->prepare("INSERT INTO reward_redemptions (user_id, reward_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $rewardId);
        $stmt->execute();

        $conn->commit();
        $msg = "แลกรางวัลสำเร็จ";
    }
}

$user = $conn->query("SELECT points FROM users WHERE id=" . $userId)->fetch_assoc();
$rewards = $conn->query("SELECT * FROM rewards ORDER BY cost_points ASC");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รางวัล | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body>
  <main class="app">
    <?php include "includes/topbar.php"; ?>

    <section class="panel">
      <p class="muted">คะแนนของคุณ</p>
      <h1><?= e($user["points"]) ?> คะแนน</h1>
    </section>

    <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>

    <h3 class="section-title">แลกรางวัล</h3>

    <?php while ($reward = $rewards->fetch_assoc()): ?>
      <section class="panel reward-card">
        <h3><?= e($reward["name"]) ?></h3>
        <p class="muted"><?= e($reward["description"]) ?></p>
        <p class="meta">ใช้ <?= e($reward["cost_points"]) ?> คะแนน · คงเหลือ <?= e($reward["stock"]) ?></p>
        <form method="post">
          <input type="hidden" name="reward_id" value="<?= e($reward["id"]) ?>">
          <button class="btn" type="submit">แลกของรางวัล</button>
        </form>
      </section>
    <?php endwhile; ?>
  </main>
</body>
</html>
