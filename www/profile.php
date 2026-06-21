<?php
require_once "_db.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$user = null;

if ($isLoggedIn) {
    $userId = intval($_SESSION["user_id"]);

    $stmt = $conn->prepare("SELECT name, email, role, points FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    $completed = $conn
      ->query("SELECT COUNT(*) AS total FROM user_quests WHERE user_id=" . $userId)
      ->fetch_assoc()["total"];
} else {
    $completed = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>โปรไฟล์ | ล่าพิกัด.com</title>

  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>

<body>
  <main class="app profile-page">
    <?php include "includes/topbar.php"; ?>

    <section class="profile-hero">
      <div class="profile-avatar-large">
        <?= $isLoggedIn ? e(mb_substr($user["name"], 0, 1, "UTF-8")) : "L" ?>
      </div>

      <?php if ($isLoggedIn): ?>
        <h1><?= e($user["name"]) ?></h1>
        <p><?= e($user["email"]) ?></p>
        <span class="role-badge"><?= e($user["role"]) ?></span>
      <?php else: ?>
        <h1>ยังไม่ได้เข้าสู่ระบบ</h1>
        <p>เข้าสู่ระบบเพื่อสะสมคะแนนและทำภารกิจ</p>
      <?php endif; ?>
    </section>

    <?php if ($isLoggedIn): ?>
      <section class="profile-stats">
        <div class="profile-stat-card">
          <p>คะแนนสะสม</p>
          <strong><?= e($user["points"]) ?></strong>
        </div>

        <div class="profile-stat-card">
          <p>ภารกิจสำเร็จ</p>
          <strong><?= e($completed) ?></strong>
        </div>
      </section>

      <section class="panel profile-menu">
        <a href="dashboard.php">หน้าหลัก</a>
        <a href="places.php">ภารกิจและสถานที่</a>
        <a href="rewards.php">รางวัล</a>

        <?php if ($user["role"] === "admin"): ?>
          <a href="admin.php">แผงควบคุมแอดมิน</a>
        <?php endif; ?>

        <a href="logout.php" class="logout-button">ออกจากระบบ</a>
      </section>
    <?php else: ?>
      <section class="panel profile-menu">
        <a href="login.php" class="login-button">เข้าสู่ระบบ</a>
        <a href="register.php">สมัครสมาชิก</a>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>