<?php
require_once "_auth.php";

$role = $_SESSION["role"] ?? "";
if ($role !== "admin" && $role !== "shop") {
    redirect("dashboard.php");
}

$questId = intval($_GET["id"] ?? 0);

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name, p.owner_user_id
    FROM quests q
    JOIN places p ON p.id = q.place_id
    WHERE q.id = ?
");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) {
    redirect("admin.php");
}

if ($role === "shop" && intval($quest["owner_user_id"]) !== intval($_SESSION["user_id"])) {
    redirect("shop.php");
}

$baseUrl = "https://xn--12c2b2a1a6ddp1n.com";
$qrText = $baseUrl . "/qr_claim.php?code=" . urlencode($quest["target_code"]);
$qrImage = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qrText);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>QR Code | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body style="background:#f1f5f9!important;background-image:none!important">
  <main class="app">
    <?php include "includes/topbar.php"; ?>

    <a class="back" href="<?= $role === "shop" ? "shop_quests.php" : "admin.php" ?>">กลับ</a>

    <section class="panel">
      <p class="eyebrow">QR Code</p>
      <h1><?= e($quest["title"]) ?></h1>
      <p class="muted"><?= e($quest["place_name"]) ?></p>

      <div class="qr-display">
        <img src="<?= e($qrImage) ?>" alt="QR Code">
      </div>

      <code><?= e($qrText) ?></code>

      <p class="meta">
        +<?= e($quest["reward_points"]) ?> คะแนน
      </p>

      <a
        class="btn"
        href="<?= e($qrImage) ?>"
        download="qr-<?= e($quest["target_code"]) ?>.png"
      >
        ดาวน์โหลด QR Code
      </a>
    </section>
  </main>
</body>
</html>