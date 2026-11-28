<?php
// Shop owner defines their own rewards (rewards.place_id): name, points needed
// and an optional photo. Customers see them on the shop's page (place.php), on
// check-in and in my_points.php; the shop deducts points when handing a reward
// over (shop_deduct.php).
require_once "_shop.php";
require_once "_shop_points.php";

$placeId = intval($shopPlace["id"]);
$msg = "";
$msgType = "good";

function getOwnReward($conn, $id, $placeId) {
    $stmt = $conn->prepare("SELECT * FROM rewards WHERE id=? AND place_id=?");
    $stmt->bind_param("ii", $id, $placeId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

if (uploadPostTooLarge()) {
    // PHP dropped the whole request (file over post_max_size), so there's no CSRF token to check.
    $msg = uploadTooLargeMessage(); $msgType = "bad";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("shop_rewards.php");
    $action = $_POST["action"] ?? "";
    $name   = mb_substr(trim($_POST["name"] ?? ""), 0, 150, "UTF-8");
    $desc   = mb_substr(trim($_POST["description"] ?? ""), 0, 500, "UTF-8");
    $cost   = intval($_POST["cost_points"] ?? 0);

    if ($action === "add" || $action === "edit") {
        $own = $action === "edit" ? getOwnReward($conn, intval($_POST["id"] ?? 0), $placeId) : null;
        if ($action === "edit" && !$own) {
            $msg = "ไม่พบรางวัลนี้"; $msgType = "bad";
        } elseif ($name === "" || $cost <= 0) {
            $msg = "กรุณากรอกชื่อรางวัลและคะแนนที่ใช้แลก (มากกว่า 0)"; $msgType = "bad";
        } elseif ($cost > SHOP_REDEEM_MAX_POINTS) {
            // Same ceiling redeemShopPoints() enforces, so a shop can't create a
            // reward that could never be redeemed.
            $msg = "คะแนนที่ใช้แลกต้องไม่เกิน " . number_format(SHOP_REDEEM_MAX_POINTS) . " คะแนน"; $msgType = "bad";
        } else {
            [$imgErr, $newImg] = saveShopRewardImage($_FILES["image"] ?? null, $placeId);
            if ($imgErr) {
                $msg = $imgErr; $msgType = "bad";
            } elseif ($action === "add") {
                $img = $newImg ?? "";
                $stmt = $conn->prepare("INSERT INTO rewards (name, description, cost_points, stock, image_url, place_id, school_id) VALUES (?, ?, ?, 0, ?, ?, NULL)");
                $stmt->bind_param("ssisi", $name, $desc, $cost, $img, $placeId);
                $stmt->execute();
                $msg = "เพิ่มรางวัลแล้ว";
            } else {
                $oldImg = $own["image_url"] ?? "";
                $img = $oldImg;
                if ($newImg) $img = $newImg;
                elseif (!empty($_POST["remove_image"])) $img = "";
                $stmt = $conn->prepare("UPDATE rewards SET name=?, description=?, cost_points=?, image_url=? WHERE id=? AND place_id=?");
                $stmt->bind_param("ssisii", $name, $desc, $cost, $img, $own["id"], $placeId);
                $stmt->execute();
                if ($img !== $oldImg) deleteShopRewardImage($oldImg);
                $msg = "บันทึกแล้ว";
            }
        }
    }
    if ($action === "delete") {
        $own = getOwnReward($conn, intval($_POST["id"] ?? 0), $placeId);
        if ($own) {
            $stmt = $conn->prepare("DELETE FROM rewards WHERE id=? AND place_id=?");
            $stmt->bind_param("ii", $own["id"], $placeId);
            $stmt->execute();
            deleteShopRewardImage($own["image_url"] ?? "");
            $msg = "ลบรางวัลแล้ว";
        }
    }
}

$rewards = getShopRewards($conn, $placeId);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>รางวัลของร้าน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/shop_points.css?v=<?= @filemtime(__DIR__ . '/css/shop_points.css') ?>">
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div class="sp-wrap">
      <a href="shop.php" style="display:inline-block;color:#2563eb;text-decoration:none;margin-bottom:12px">‹ กลับ</a>
      <h1 style="font-size:21px;margin:0 0 4px;color:#0f172a">รางวัลของร้าน</h1>
      <p style="margin:0 0 14px;color:#64748b;font-size:13.5px">ลูกค้าได้ <?= SHOP_CHECKIN_CUSTOMER_POINTS ?> คะแนนต่อการเช็คอิน 1 ครั้ง (วันละครั้ง) ตั้งรางวัลให้เหมาะ เช่น 100 คะแนน = มากินประมาณ 10 ครั้ง</p>

      <?php if ($msg): ?><div class="sp-alert <?= $msgType ?>"><?= e($msg) ?></div><?php endif; ?>

      <section class="sp-card">
        <form method="post" class="sp-form" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="action" value="add">
          <label class="sp-upload">
            <img alt="" hidden>
            <span class="sp-upload-ph">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg>
              เพิ่มรูปรางวัล (ไม่ใส่ก็ได้)
            </span>
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImg(this)">
          </label>
          <input name="name" maxlength="150" placeholder="ชื่อรางวัล เช่น ก๋วยเตี๋ยวฟรี 1 ชาม" required>
          <input name="description" maxlength="500" placeholder="รายละเอียด (ไม่ใส่ก็ได้) เช่น ไม่รวมพิเศษ">
          <input name="cost_points" type="number" min="1" max="<?= SHOP_REDEEM_MAX_POINTS ?>" placeholder="คะแนนที่ใช้แลก เช่น 100" required>
          <button class="sp-btn" type="submit" style="margin:0">+ เพิ่มรางวัล</button>
        </form>
      </section>

      <div class="sp-section">รางวัลทั้งหมด (<?= count($rewards) ?>)</div>
      <section class="sp-card" style="padding-top:6px;padding-bottom:6px">
        <?php if (!$rewards): ?>
          <div class="sp-empty">ยังไม่มีรางวัล</div>
        <?php endif; ?>
        <?php foreach ($rewards as $rw): ?>
          <details class="sp-shoprow">
            <summary>
              <?= rewardThumbHtml($rw, 56) ?: '<div class="sp-rw-img ph" style="width:56px;height:56px"></div>' ?>
              <div class="info">
                <strong><?= e($rw["name"]) ?></strong>
                <span><?= e($rw["description"] ?? "") ?: "แตะเพื่อแก้ไข" ?></span>
              </div>
              <div class="pts"><?= number_format($rw["cost_points"]) ?> <small>คะแนน</small></div>
            </summary>
            <div style="padding-bottom:12px">
              <form method="post" class="sp-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= intval($rw["id"]) ?>">
                <label class="sp-upload">
                  <img alt="" <?= !empty($rw["image_url"]) ? 'src="' . e($rw["image_url"]) . '"' : "hidden" ?>>
                  <span class="sp-upload-ph" <?= !empty($rw["image_url"]) ? "hidden" : "" ?>>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/></svg>
                    เพิ่มรูปรางวัล
                  </span>
                  <input type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewImg(this)">
                </label>
                <?php if (!empty($rw["image_url"])): ?>
                  <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#64748b">
                    <input type="checkbox" name="remove_image" value="1" style="width:auto"> ลบรูปนี้
                  </label>
                <?php endif; ?>
                <input name="name" maxlength="150" value="<?= e($rw["name"]) ?>" required>
                <input name="description" maxlength="500" value="<?= e($rw["description"] ?? "") ?>" placeholder="รายละเอียด">
                <input name="cost_points" type="number" min="1" max="<?= SHOP_REDEEM_MAX_POINTS ?>" value="<?= intval($rw["cost_points"]) ?>" required>
                <button class="sp-btn" type="submit" style="margin:0">บันทึก</button>
              </form>
              <form method="post" style="margin-top:8px" onsubmit="return confirm('ลบรางวัลนี้?')">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= intval($rw["id"]) ?>">
                <button class="sp-btn danger" type="submit" style="margin:0">ลบรางวัล</button>
              </form>
            </div>
          </details>
        <?php endforeach; ?>
      </section>
    </div>
  </main>
  <script>
  // Show the chosen photo right away (before saving).
  function previewImg(input) {
    const box = input.closest('.sp-upload');
    const img = box.querySelector('img');
    const ph = box.querySelector('.sp-upload-ph');
    const f = input.files && input.files[0];
    if (!f) return;
    if (f.size > <?= SHOP_REWARD_IMG_MAX_MB ?> * 1024 * 1024) {
      alert('รูปต้องไม่เกิน <?= SHOP_REWARD_IMG_MAX_MB ?>MB');
      input.value = '';
      return;
    }
    img.src = URL.createObjectURL(f);
    img.hidden = false;
    if (ph) ph.hidden = true;
  }
  </script>
</body>
</html>
