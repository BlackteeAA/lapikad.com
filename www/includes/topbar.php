<?php
$currentPage = basename($_SERVER["PHP_SELF"]);

function activeTab($pages) {
    global $currentPage;
    return in_array($currentPage, $pages) ? "active" : "";
}

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["name"] ?? "ผู้ใช้";
$userRole = $_SESSION["role"] ?? "";
?>

<input type="checkbox" id="menu-toggle" class="menu-toggle">

<header class="mobile-topbar">
  <label for="menu-toggle" class="hamburger-btn">
    <span></span>
    <span></span>
    <span></span>
  </label>

  <a href="<?= $isLoggedIn ? 'dashboard.php' : 'index.php' ?>" class="mobile-brand">
    <span class="brand-circle">L</span>
    <span>ล่าพิกัด.com</span>
  </a>

  <a href="profile.php" class="top-profile">
    <?= e(mb_substr($userName, 0, 1, "UTF-8")) ?>
  </a>
</header>

<aside class="side-menu">
  <label for="menu-toggle" class="side-close">ปิด</label>

  <div class="side-profile">
    <div class="side-avatar">
      <?= e(mb_substr($userName, 0, 1, "UTF-8")) ?>
    </div>

    <strong><?= e($userName) ?></strong>

    <?php if ($isLoggedIn): ?>
      <span><?= e($userRole) ?></span>
    <?php else: ?>
      <span>ยังไม่ได้เข้าสู่ระบบ</span>
    <?php endif; ?>
  </div>

  <nav class="side-links">
    <?php if ($isLoggedIn): ?>
      <a href="dashboard.php">หน้าหลัก</a>
      <a href="places.php">สถานที่และภารกิจ</a>
      <a href="rewards.php">รางวัล</a>
      <a href="profile.php">โปรไฟล์</a>

      <?php if ($userRole === "admin"): ?>
        <a href="admin.php">แอดมิน</a>
      <?php endif; ?>

      <a href="logout.php" class="danger-link">ออกจากระบบ</a>
    <?php else: ?>
      <a href="login.php">เข้าสู่ระบบ</a>
      <a href="register.php">สมัครสมาชิก</a>
    <?php endif; ?>
  </nav>
</aside>

<label for="menu-toggle" class="menu-backdrop"></label>

<nav class="bottom-nav">
  <a class="<?= activeTab(["dashboard.php"]) ?>" href="dashboard.php">
    <svg viewBox="0 0 24 24">
      <path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z"/>
    </svg>
    <span>หน้าหลัก</span>
  </a>

  <a class="<?= activeTab(["profile.php", "login.php", "register.php"]) ?>" href="profile.php">
    <svg viewBox="0 0 24 24">
      <path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-5 0-9 2.5-9 5.5V22h18v-2.5C21 16.5 17 14 12 14z"/>
    </svg>
    <span>โปรไฟล์</span>
  </a>

  <a class="<?= activeTab(["places.php", "place.php", "quest.php", "scan.php", "qr_claim.php", "complete_quest.php"]) ?>" href="places.php">
    <svg viewBox="0 0 24 24">
      <path d="M9 3h12v12H9V3zm2 2v8h8V5h-8zM3 9h4v12H3V9zm2 2v8h0v-8zm4 6h12v4H9v-4zm2 2h8v0h-8z"/>
    </svg>
    <span>ภารกิจ</span>
  </a>

  <a class="<?= activeTab(["rewards.php"]) ?>" href="rewards.php">
    <svg viewBox="0 0 24 24">
      <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
    </svg>
    <span>รางวัล</span>
  </a>
</nav>