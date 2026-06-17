<nav class="nav">
  <a class="brand" href="index.php">ล่าพิกัด.com</a>
  <div class="nav-links">
    <?php if (isset($_SESSION["user_id"])): ?>
      <a href="dashboard.php">หน้าหลัก</a>
      <a href="places.php">สถานที่</a>
      <a href="rewards.php">รางวัล</a>
      <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
        <a href="admin.php">แอดมิน</a>
      <?php endif; ?>
      <a href="logout.php">ออก</a>
    <?php else: ?>
      <a href="login.php">เข้าสู่ระบบ</a>
      <a href="register.php">สมัครสมาชิก</a>
    <?php endif; ?>
  </div>
</nav>
