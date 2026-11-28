<?php
// After the shop scans a customer's QR (shop_deduct_scan.php):
//   1. show who it is and their points at this shop, pick a reward or an amount
//   2. confirm → deduct (redeemShopPoints) → done screen
// The customer is identified by the scanned token on every step (never by a
// posted user id), so the form can't be pointed at someone else.
require_once "_shop.php";
require_once "_shop_points.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_verify()) redirect("shop.php");

$placeId  = intval($shopPlace["id"]);
$token    = extractCustomerToken($_POST["scanned_code"] ?? "");
$customer = null;
$error    = "";
$done     = null; // ["points" => n, "note" => s] after a successful deduction

try {
    $customer = findCustomerByToken($conn, $token);
    if (!$customer) {
        $error = "QR นี้ไม่ใช่ QR ของลูกค้าในระบบ ให้ลูกค้าเปิดเมนู \"แต้มร้านค้าของฉัน\" แล้วสแกนใหม่";
    } elseif (intval($customer["id"]) === intval($_SESSION["user_id"])) {
        $customer = null;
        $error = "นี่คือ QR ของคุณเอง";
    }

    // One-time nonce per form, so refreshing the result page can't deduct twice.
    $nonceOk = ($_POST["step"] ?? "") === "deduct"
        && hash_equals($_SESSION["deduct_nonce"] ?? "", (string)($_POST["nonce"] ?? ""));
    if ($customer && ($_POST["step"] ?? "") === "deduct" && !$nonceOk) {
        $error = "รายการนี้หักคะแนนไปแล้ว หรือหน้านี้เก่าเกินไป ตรวจคะแนนคงเหลือด้านบนก่อนทำใหม่";
    }
    if ($customer && $nonceOk) {
        unset($_SESSION["deduct_nonce"]);
        $rewards = array_column(getShopRewards($conn, $placeId), null, "id");
        $choice  = $_POST["reward"] ?? "";
        if ($choice !== "" && $choice !== "custom" && isset($rewards[intval($choice)])) {
            $points = intval($rewards[intval($choice)]["cost_points"]);
            $note   = $rewards[intval($choice)]["name"];
        } else {
            $points = intval($_POST["points"] ?? 0);
            $note   = trim($_POST["note"] ?? "");
        }
        $r = redeemShopPoints($conn, $placeId, intval($customer["id"]), $points, $note, intval($_SESSION["user_id"]));
        if ($r === "ok")               $done = ["points" => $points, "note" => $note];
        elseif ($r === "insufficient") $error = "คะแนนของลูกค้าไม่พอ";
        elseif ($r === "invalid")      $error = "กรุณาเลือกรางวัลหรือใส่จำนวนคะแนนที่จะหัก";
        elseif ($r === "too_large")    $error = "หักได้ไม่เกิน " . number_format(SHOP_REDEEM_MAX_POINTS) . " คะแนนต่อครั้ง";
        else                           $error = "หักคะแนนไม่สำเร็จ กรุณาลองใหม่";
    }
} catch (mysqli_sql_exception $e) {
    $customer = null;
    $error = "ระบบแต้มร้านค้ายังไม่พร้อมใช้งาน";
}

$balance = $customer ? getShopBalance($conn, intval($customer["id"]), $placeId) : 0;
$rewards = $customer ? getShopRewards($conn, $placeId) : [];
$scanned = $_POST["scanned_code"] ?? "";
if ($customer && !$done) $_SESSION["deduct_nonce"] = bin2hex(random_bytes(16));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>หักคะแนนลูกค้า | ล่าพิกัด.com</title>
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
      <?php if ($done): ?>
        <section class="sp-card sp-result">
          <div class="sp-mark good">✓</div>
          <h1>หักคะแนนแล้ว</h1>
          <p><?= e($customer["name"]) ?><?= $done["note"] !== "" ? " · " . e($done["note"]) : "" ?></p>
          <div class="sp-gain" style="background:#fee2e2;color:#b91c1c">−<?= number_format($done["points"]) ?> คะแนน</div>
          <p style="margin-top:12px">คงเหลือ <?= number_format($balance) ?> คะแนน</p>
        </section>
      <?php elseif (!$customer): ?>
        <section class="sp-card sp-result">
          <div class="sp-mark bad">!</div>
          <h1>สแกนไม่สำเร็จ</h1>
          <p><?= e($error) ?></p>
        </section>
      <?php else: ?>
        <section class="sp-card sp-customer">
          <div class="sp-avatar">
            <?php if (!empty($customer["avatar_url"])): ?>
              <img src="<?= e($customer["avatar_url"]) ?>" alt="">
            <?php else: ?>
              <?= e(mb_substr($customer["name"], 0, 1, "UTF-8")) ?>
            <?php endif; ?>
          </div>
          <div style="flex:1;min-width:0">
            <strong style="display:block;font-size:17px;color:#0f172a"><?= e($customer["name"]) ?></strong>
            <span style="font-size:12.5px;color:#64748b">ตรวจดูว่าตรงกับลูกค้าตัวจริง</span>
          </div>
        </section>

        <section class="sp-card sp-balance">
          <span>คะแนนของลูกค้าที่ร้านคุณ</span>
          <b><?= number_format($balance) ?></b>
        </section>

        <?php if ($error): ?><div class="sp-alert bad"><?= e($error) ?></div><?php endif; ?>

        <form method="post" class="sp-card" onsubmit="return confirmDeduct(this)">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="scanned_code" value="<?= e($scanned) ?>">
          <input type="hidden" name="step" value="deduct">
          <input type="hidden" name="nonce" value="<?= e($_SESSION["deduct_nonce"] ?? "") ?>">
          <div class="sp-section" style="margin-top:0">เลือกรางวัลที่แลก</div>
          <?php foreach ($rewards as $rw): $ok = $balance >= intval($rw["cost_points"]); ?>
            <label class="sp-pick <?= $ok ? "" : "disabled" ?>">
              <input type="radio" name="reward" value="<?= intval($rw["id"]) ?>" data-points="<?= intval($rw["cost_points"]) ?>" <?= $ok ? "" : "disabled" ?>>
              <?= rewardThumbHtml($rw, 52) ?>
              <span class="grow"><?= e($rw["name"]) ?><?= $ok ? "" : ' <small style="color:#94a3b8">(คะแนนไม่พอ)</small>' ?></span>
              <b><?= number_format($rw["cost_points"]) ?></b>
            </label>
          <?php endforeach; ?>
          <label class="sp-pick">
            <input type="radio" name="reward" value="custom" <?= $rewards ? "" : "checked" ?>>
            <span class="grow">ใส่จำนวนเอง</span>
          </label>
          <div id="custom-box" class="sp-form" style="<?= $rewards ? "display:none" : "" ?>">
            <input type="number" name="points" min="1" max="<?= $balance ?>" placeholder="จำนวนคะแนนที่หัก">
            <input type="text" name="note" maxlength="150" placeholder="หมายเหตุ เช่น ส่วนลด 20 บาท">
          </div>
          <button class="sp-btn green" type="submit" style="margin-top:12px" <?= $balance > 0 ? "" : "disabled" ?>>หักคะแนน</button>
        </form>
      <?php endif; ?>

      <a class="sp-btn" href="shop_deduct_scan.php">สแกนลูกค้าคนต่อไป</a>
      <a class="sp-btn ghost" href="shop.php">กลับหน้าร้าน</a>
    </div>
  </main>
  <script>
  document.querySelectorAll('input[name="reward"]').forEach(r => r.addEventListener('change', () => {
    const box = document.getElementById('custom-box');
    if (box) box.style.display = document.querySelector('input[name="reward"]:checked')?.value === 'custom' ? '' : 'none';
  }));
  function confirmDeduct(form) {
    const sel = form.querySelector('input[name="reward"]:checked');
    if (!sel) { alert('เลือกรางวัลก่อน'); return false; }
    const pts = sel.value === 'custom' ? form.points.value : sel.dataset.points;
    if (!pts || pts <= 0) { alert('ใส่จำนวนคะแนน'); return false; }
    return confirm('หัก ' + pts + ' คะแนน ใช่ไหม?');
  }
  </script>
</body>
</html>
