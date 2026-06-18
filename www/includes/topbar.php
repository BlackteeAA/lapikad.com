<?php
$currentPage = basename($_SERVER["PHP_SELF"]);

function activeTab($pages) {
    global $currentPage;
    return in_array($currentPage, $pages) ? "active" : "";
}
?>

<header class="app-topbar">
  <a class="app-brand" href="dashboard.php">
    <div class="brand-logo">L</div>
    <span>ล่าพิกัด.com</span>
  </a>

  <nav class="app-tabs">
    <a class="<?= activeTab(["dashboard.php"]) ?>" href="dashboard.php">หน้าหลัก</a>
    <a class="<?= activeTab(["places.php", "place.php"]) ?>" href="places.php">สถานที่</a>
    <a class="<?= activeTab(["quest.php", "scan.php", "qr_claim.php"]) ?>" href="places.php">QR Code</a>
    <a class="<?= activeTab(["rewards.php"]) ?>" href="rewards.php">รางวัล</a>

    <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
      <a class="<?= activeTab(["admin.php", "qr_view.php"]) ?>" href="admin.php">แอดมิน</a>
    <?php endif; ?>
  </nav>

  <div class="topbar-user">
    <div class="avatar">T</div>
    <div>
      <strong><?= e($_SESSION["name"] ?? "ผู้ใช้") ?></strong>
      <span><?= e($_SESSION["role"] ?? "user") ?></span>
    </div>
  </div>
</header>