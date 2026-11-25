<?php
// Admin: add (or take away) a user's points at a specific shop. Each shop's
// points are separate (user_shop_points), so the admin picks user + shop.
require_once "_admin.php";
require_once "_shop_points.php";

$msg = "";
$msgType = "good";
$userId = intval($_GET["user_id"] ?? $_POST["user_id"] ?? 0);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_shop_points.php?user_id=" . $userId);
    $placeId = intval($_POST["place_id"] ?? 0);
    $points  = intval($_POST["points"] ?? 0);
    $sign    = ($_POST["mode"] ?? "add") === "sub" ? -1 : 1;
    $place   = $placeId ? getShopPlace($conn, $placeId) : null;
    if (!$userId || !$place || empty($place["owner_user_id"]) || $points <= 0) {
        $msg = "กรุณาเลือกร้านและใส่จำนวนคะแนนมากกว่า 0"; $msgType = "bad";
    } else {
        try {
            $r = adminAdjustShopPoints($conn, $placeId, $userId, $sign * $points, $_POST["note"] ?? "", intval($_SESSION["user_id"]));
        } catch (mysqli_sql_exception $e) { $r = "error"; }
        if ($r === "ok") {
            $_SESSION["asp_flash"] = ($sign > 0 ? "เพิ่ม " : "ลด ") . number_format($points) . " คะแนน ที่ร้าน " . $place["name"] . " แล้ว";
            redirect("admin_shop_points.php?user_id=" . $userId); // no double-add on refresh
        }
        $msgType = "bad";
        $msg = $r === "insufficient" ? "ลดไม่ได้ คะแนนที่ร้านนี้จะติดลบ"
             : ($r === "invalid" ? "จำนวนคะแนนไม่ถูกต้อง"
             : "บันทึกไม่สำเร็จ (ถ้ายังไม่ได้รัน sql/add_admin_shop_points.sql ให้รันก่อน)");
    }
}
if (!empty($_SESSION["asp_flash"])) { $msg = $_SESSION["asp_flash"]; unset($_SESSION["asp_flash"]); }

$users = $conn->query("SELECT id, name, email FROM users ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$shops = $conn->query("SELECT id, name, image_url, province, district FROM places WHERE owner_user_id IS NOT NULL ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$user = null;
$balances = [];
$history = [];
if ($userId) {
    $stmt = $conn->prepare("SELECT id, name, email, avatar_url FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
}
if ($user) try {
    $stmt = $conn->prepare("SELECT place_id, points FROM user_shop_points WHERE user_id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $b) $balances[intval($b["place_id"])] = intval($b["points"]);

    $stmt = $conn->prepare("
        SELECT t.kind, t.points, t.note, t.created_at, p.name AS shop_name
        FROM shop_point_txns t JOIN places p ON p.id = t.place_id
        WHERE t.user_id=? ORDER BY t.id DESC LIMIT 20
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (mysqli_sql_exception $e) {
    $msg = $msg ?: "ระบบแต้มร้านค้ายังไม่พร้อม (ยังไม่ได้รัน sql/add_shop_checkins.sql)"; $msgType = "bad";
}
// Shops this user already has points at first.
usort($shops, fn($a, $b) => ($balances[$b["id"]] ?? -1) <=> ($balances[$a["id"]] ?? -1));
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>แต้มร้านค้าของผู้ใช้ | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/shop_points.css?v=<?= @filemtime(__DIR__ . '/css/shop_points.css') ?>">
  <style>
    .asp-seg { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
    .asp-seg label { display:flex; align-items:center; justify-content:center; gap:6px; padding:10px; border:1px solid #e2e8f0; border-radius:12px; font-size:14px; font-weight:600; cursor:pointer; }
    .asp-seg input { width:auto; margin:0; }
    .asp-seg label:has(input:checked) { border-color:#2563eb; background:#eff6ff; color:#1d4ed8; }
    .asp-seg label.sub:has(input:checked) { border-color:#dc2626; background:#fef2f2; color:#b91c1c; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div class="sp-wrap">
      <a href="admin_users.php" style="display:inline-block;color:#2563eb;text-decoration:none;margin-bottom:12px">‹ กลับ</a>
      <h1 style="font-size:21px;margin:0 0 4px;color:#0f172a">แต้มร้านค้าของผู้ใช้</h1>
      <p style="margin:0 0 14px;color:#64748b;font-size:13.5px">แต้มแต่ละร้านแยกกัน เลือกผู้ใช้และร้านที่จะเพิ่มคะแนนให้ (ไม่นับเป็นคะแนนความนิยมของร้าน)</p>

      <?php if ($msg): ?><div class="sp-alert <?= $msgType ?>"><?= e($msg) ?></div><?php endif; ?>

      <section class="sp-card">
        <form method="get" class="sp-form">
          <input type="search" id="user-q" placeholder="ค้นหาชื่อ / อีเมล" oninput="filterUsers(this.value)">
          <select name="user_id" id="user-sel" onchange="this.form.submit()">
            <option value="">เลือกผู้ใช้</option>
            <?php foreach ($users as $u): ?>
              <option value="<?= $u["id"] ?>" <?= intval($u["id"]) === $userId ? "selected" : "" ?>><?= e($u["name"]) ?> · <?= e($u["email"]) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </section>

      <?php if ($user): ?>
        <section class="sp-card sp-customer">
          <div class="sp-avatar">
            <?php if (!empty($user["avatar_url"])): ?>
              <img src="<?= e($user["avatar_url"]) ?>" alt="">
            <?php else: ?>
              <?= e(mb_substr($user["name"], 0, 1, "UTF-8")) ?>
            <?php endif; ?>
          </div>
          <div style="flex:1;min-width:0">
            <strong style="display:block;font-size:17px;color:#0f172a"><?= e($user["name"]) ?></strong>
            <span style="font-size:12.5px;color:#64748b">รวมทุกร้าน <?= number_format(array_sum($balances)) ?> คะแนน</span>
          </div>
        </section>

        <section class="sp-card">
          <?php if (!$shops): ?>
            <div class="sp-empty">ยังไม่มีร้านค้าในระบบ</div>
          <?php else: ?>
          <form method="post" class="sp-form" onsubmit="return confirmAdjust(this)">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="user_id" value="<?= $userId ?>">
            <select name="place_id" required>
              <option value="">เลือกร้าน</option>
              <?php foreach ($shops as $s): ?>
                <option value="<?= $s["id"] ?>"><?= e($s["name"]) ?><?= !empty($s["province"]) ? " (" . e($s["province"]) . ")" : "" ?> · มี <?= number_format($balances[$s["id"]] ?? 0) ?> คะแนน</option>
              <?php endforeach; ?>
            </select>
            <div class="asp-seg">
              <label><input type="radio" name="mode" value="add" checked> + เพิ่มคะแนน</label>
              <label class="sub"><input type="radio" name="mode" value="sub"> − ลดคะแนน</label>
            </div>
            <input type="number" name="points" min="1" max="100000" placeholder="จำนวนคะแนน เช่น 50" required>
            <input name="note" maxlength="150" placeholder="เหตุผล (ไม่ใส่ก็ได้) เช่น ชดเชยสแกนไม่ติด">
            <button class="sp-btn" type="submit" style="margin:0">บันทึก</button>
          </form>
          <?php endif; ?>
        </section>

        <div class="sp-section">คะแนนแต่ละร้าน</div>
        <section class="sp-card" style="padding-top:6px;padding-bottom:6px">
          <?php $has = array_filter($shops, fn($s) => isset($balances[$s["id"]])); ?>
          <?php if (!$has): ?><div class="sp-empty">ยังไม่มีแต้มที่ร้านไหน</div><?php endif; ?>
          <?php foreach ($has as $s): ?>
            <div class="sp-txn">
              <span><?= e($s["name"]) ?><?php if (!empty($s["district"]) || !empty($s["province"])): ?><small><?= e(trim(($s["district"] ?? "") . " " . ($s["province"] ?? ""))) ?></small><?php endif; ?></span>
              <b><?= number_format($balances[$s["id"]]) ?></b>
            </div>
          <?php endforeach; ?>
        </section>

        <?php if ($history): ?>
          <div class="sp-section">ประวัติล่าสุด</div>
          <section class="sp-card" style="padding-top:6px;padding-bottom:6px">
            <?php foreach ($history as $t): $plus = intval($t["points"]) >= 0; ?>
              <div class="sp-txn">
                <span><?= e($t["shop_name"]) ?> · <?= e(shopTxnLabel($t)) ?><small><?= e(thaiTimestampText(strtotime($t["created_at"]))) ?></small></span>
                <b class="<?= $plus ? "plus" : "minus" ?>"><?= $plus ? "+" : "" ?><?= intval($t["points"]) ?></b>
              </div>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
  <script>
  function filterUsers(q) {
    q = q.trim().toLowerCase();
    const sel = document.getElementById('user-sel');
    let first = null;
    [...sel.options].forEach((o, i) => {
      if (!i) return;
      const show = !q || o.text.toLowerCase().includes(q);
      o.hidden = !show;
      if (show && !first) first = o;
    });
  }
  function confirmAdjust(f) {
    const shop = f.place_id.options[f.place_id.selectedIndex].text.split(' · ')[0];
    const sub = f.querySelector('input[name="mode"]:checked').value === 'sub';
    return confirm((sub ? 'ลด ' : 'เพิ่ม ') + f.points.value + ' คะแนน ที่ร้าน ' + shop + ' ใช่ไหม?');
  }
  </script>
</body>
</html>
