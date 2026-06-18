<?php require_once "_bootstrap.php"; ?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body>
  <main class="app">
    <?php include "includes/topbar.php"; ?>

    <section class="hero panel">
      <p class="eyebrow">แพลตฟอร์มท่องเที่ยวเชิงภารกิจ</p>
      <h1>ล่าพิกัด ทำภารกิจ สะสมคะแนน</h1>
      <p class="muted">สำรวจสถานที่ท่องเที่ยวผ่านภารกิจแบบเกม เช็คอิน เก็บคะแนน และแลกรางวัลจากชุมชน</p>

      <div class="actions">
        <a class="btn" href="login.php">เข้าสู่ระบบ</a>
        <a class="btn ghost" href="register.php">สมัครสมาชิก</a>
      </div>
    </section>

    <section class="grid">
      <div class="panel small">
        <h3>Check-in</h3>
        <p class="muted">ยืนยันการมาถึงจุดท่องเที่ยว</p>
      </div>
      <div class="panel small">
        <h3>Quest</h3>
        <p class="muted">ทำภารกิจเพื่อรับคะแนน</p>
      </div>
      <div class="panel small">
        <h3>Reward</h3>
        <p class="muted">แลกคะแนนเป็นของรางวัล</p>
      </div>
    </section>
  </main>
</body>
</html>
