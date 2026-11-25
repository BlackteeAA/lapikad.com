<?php
// Shop owner sets which province / district the shop is in (places.province /
// places.district) — used by the search filter on places.php and the
// provincial Top 3 on the dashboard.
require_once "_shop.php";
require_once "_shop_points.php";
require_once "_thai_location.php";

$placeId = intval($shopPlace["id"]);
$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_location.php");
    $province = cleanThaiArea($_POST["province"] ?? "");
    $district = cleanThaiArea($_POST["district"] ?? "");
    $locText  = mb_substr(trim($_POST["location_text"] ?? ""), 0, 255, "UTF-8");
    if ($province === "" || $district === "") {
        $msg = "กรุณาเลือกจังหวัดและกรอกอำเภอ"; $msgType = "bad";
    } else {
        $stmt = $conn->prepare("UPDATE places SET province=?, district=?, location_text=? WHERE id=? AND owner_user_id=?");
        $uid = intval($_SESSION["user_id"]);
        $stmt->bind_param("sssii", $province, $district, $locText, $placeId, $uid);
        $stmt->execute();
        $msg = "บันทึกที่ตั้งร้านแล้ว";
    }
    $shopPlace["province"] = $province;
    $shopPlace["district"] = $district;
    $shopPlace["location_text"] = $locText;
}

[$provSel, $distInput] = thaiLocationFields($conn, $shopPlace["province"] ?? "", $shopPlace["district"] ?? "");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ที่ตั้งร้าน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/shop_points.css?v=<?= @filemtime(__DIR__ . '/css/shop_points.css') ?>">
  <style>
    .sp-form label.lbl { font-size:13px; font-weight:600; color:#334155; margin-bottom:-4px; }
    .sp-form select { width:100%; box-sizing:border-box; padding:11px 12px; border:1px solid #e2e8f0; border-radius:12px; font:inherit; font-size:15px; background:#fff; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div class="sp-wrap">
      <a href="shop.php" style="display:inline-block;color:#2563eb;text-decoration:none;margin-bottom:12px">‹ กลับ</a>
      <h1 style="font-size:21px;margin:0 0 4px;color:#0f172a">ที่ตั้งร้าน</h1>
      <p style="margin:0 0 14px;color:#64748b;font-size:13.5px">ลูกค้าค้นหาร้านตามจังหวัด/อำเภอได้ในหน้าสถานที่ และร้านจะไปติดอันดับยอดนิยมของจังหวัดนั้น</p>

      <?php if ($msg): ?><div class="sp-alert <?= $msgType ?>"><?= e($msg) ?></div><?php endif; ?>

      <section class="sp-card">
        <form method="post" class="sp-form">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <label class="lbl">จังหวัด</label>
          <?= $provSel ?>
          <label class="lbl">อำเภอ</label>
          <?= $distInput ?>
          <label class="lbl">ที่อยู่ (ไม่บังคับ)</label>
          <input name="location_text" maxlength="255" value="<?= e($shopPlace["location_text"] ?? "") ?>" placeholder="เช่น 12 ถ.ถีนานนท์ ต.กุฉินารายณ์">
          <button class="sp-btn" type="submit" style="margin:0">บันทึก</button>
        </form>
      </section>
    </div>
  </main>
</body>
</html>
