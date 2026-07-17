<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$tab    = $_GET["tab"] ?? "list";

$rewardsAll = $conn->query("
    SELECT r.*, p.name AS place_name, p.id AS place_id,
           COALESCE(usp.points, 0) AS my_points
    FROM rewards r
    JOIN places p ON p.id = r.place_id
    LEFT JOIN user_shop_points usp ON usp.place_id = r.place_id AND usp.user_id = $userId
    WHERE r.place_id IS NOT NULL
    ORDER BY r.cost_points ASC
")->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare("
    SELECT sr.*, r.name AS reward_name, r.image_url, p.name AS place_name
    FROM shop_redemptions sr
    JOIN rewards r ON r.id = sr.reward_id
    JOIN places p ON p.id = sr.place_id
    WHERE sr.user_id=?
    ORDER BY sr.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Lazily resolve any of this user's own overdue pending redemptions while they're looking.
foreach ($history as &$h) {
    if ($h["status"] === "pending") {
        $h["status"] = expireIfNeeded($conn, $h);
    }
}
unset($h);

$completedCount = count(array_filter($history, fn($h) => $h["status"] === "completed"));
$pendingCount   = count(array_filter($history, fn($h) => $h["status"] === "pending"));

$statusLabel = [
    "pending"   => "รอสแกนที่ร้าน",
    "completed" => "แลกสำเร็จ",
    "expired"   => "หมดเวลา",
    "cancelled" => "ยกเลิกแล้ว",
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>รางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .rw-page { max-width:100%; background:#f1f5f9; }

    .rw-tabs { display:flex; border-bottom:2px solid #e2e8f0; margin-bottom:18px; }
    .rw-tabs a {
      flex:1; text-align:center; padding:12px 0; font-size:14px; font-weight:600;
      text-decoration:none; color:#94a3b8; border-bottom:2px solid transparent; margin-bottom:-2px;
    }
    .rw-tabs a.active { color:#2563eb; border-bottom-color:#2563eb; }
    .rw-tabs a .badge {
      background:#ef4444; color:#fff; border-radius:99px; padding:1px 6px; font-size:11px; margin-left:4px;
    }

    .rw-head { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
    .rw-head h2 { font-size:17px; font-weight:700; color:#0f172a; margin:0; }
    .rw-head span { font-size:13px; color:#64748b; }

    /* Reward card — browse list */
    .rw-card { background:#fff; border-radius:20px; overflow:hidden; box-shadow:0 2px 8px rgba(15,23,42,.06); margin-bottom:12px; }
    .rw-card-top { display:flex; align-items:flex-start; padding:14px; gap:14px; }
    .rw-img { width:100px; height:100px; flex-shrink:0; border-radius:14px; overflow:hidden; background:#eff6ff; }
    .rw-img img { width:100%; height:100%; object-fit:cover; display:block; }
    .rw-img-ph { width:100%; height:100%; display:flex; align-items:center; justify-content:center; }

    .rw-body { flex:1; display:flex; flex-direction:column; gap:5px; min-width:0; }
    .rw-name { font-size:15px; font-weight:700; color:#0f172a; margin:0; }
    .rw-shop-tag {
      font-size:10.5px; font-weight:600; color:#2563eb; background:#eff6ff;
      padding:2px 8px; border-radius:99px; width:fit-content;
    }
    .rw-desc { font-size:12px; color:#64748b; margin:0; line-height:1.4; }
    .rw-pts { display:flex; align-items:center; gap:5px; font-size:15px; font-weight:700; color:#0f172a; }
    .rw-pts svg { width:16px; height:16px; fill:#f59e0b; flex-shrink:0; }
    .rw-pts small { font-size:12px; color:#64748b; font-weight:400; }
    .rw-stock { font-size:12px; color:#16a34a; font-weight:500; }
    .rw-mypts { font-size:11.5px; color:#94a3b8; }

    .rw-card-btn { padding:0 14px 14px; }
    .rw-btn {
      display:block; width:100%; background:#2563eb; color:#fff; border:none; border-radius:999px;
      padding:13px; font-family:inherit; font-size:14px; font-weight:600; cursor:pointer;
      text-align:center; text-decoration:none; transition:opacity .2s;
    }
    .rw-btn.disabled { background:#e2e8f0; color:#94a3b8; pointer-events:none; }
    .rw-btn:hover { opacity:.85; }

    /* History list */
    .rw-stats { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:20px; }
    .rw-stat {
      background:#fff; border-radius:20px; padding:16px;
      display:flex; align-items:center; gap:12px; box-shadow:0 2px 8px rgba(15,23,42,.06);
    }
    .rw-stat-icon {
      width:44px; height:44px; border-radius:14px;
      display:flex; align-items:center; justify-content:center; flex-shrink:0;
    }
    .rw-stat-icon.blue  { background:#dbeafe; }
    .rw-stat-icon.green { background:#dcfce7; }
    .rw-stat-val { font-size:22px; font-weight:700; color:#0f172a; line-height:1; display:block; }
    .rw-stat-lbl { font-size:12px; color:#64748b; display:block; margin-top:3px; }

    .rw-list { display:grid; gap:10px; }
    .rw-item {
      background:#fff; border-radius:18px; padding:14px;
      display:flex; align-items:center; gap:12px; box-shadow:0 2px 8px rgba(15,23,42,.06);
    }
    .rw-item .thumb {
      width:56px; height:56px; border-radius:14px; overflow:hidden; flex-shrink:0;
      background:#eff6ff; display:flex; align-items:center; justify-content:center;
    }
    .rw-item .thumb img { width:100%; height:100%; object-fit:cover; }
    .rw-item .info { flex:1; min-width:0; }
    .rw-item .info strong { display:block; font-size:14px; font-weight:700; color:#0f172a; }
    .rw-item .info span { display:block; font-size:12px; color:#64748b; margin-top:2px; }

    .rw-item .side { text-align:right; flex-shrink:0; }
    .rw-item .pts { font-size:13px; font-weight:700; color:#e11d48; }
    .rw-item .date { font-size:11px; color:#94a3b8; margin-top:2px; }

    .status-pill { font-size:10.5px; font-weight:600; padding:3px 9px; border-radius:99px; white-space:nowrap; margin-top:4px; display:inline-block; }
    .status-pending   { background:#fef9c3; color:#a16207; }
    .status-completed { background:#f0fdf4; color:#16a34a; }
    .status-expired   { background:#f1f5f9; color:#64748b; }
    .status-cancelled { background:#fff1f2; color:#e11d48; }

    .rw-empty { text-align:center; padding:40px 20px; background:#fff; border-radius:20px; color:#94a3b8; font-size:14px; }
  </style>
</head>
<body>
  <main class="app rw-page" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div style="margin-bottom:16px">
      <h1 style="font-size:26px;font-weight:700;color:#0f172a;margin:0 0 4px">รางวัล</h1>
      <p style="font-size:13px;color:#64748b;margin:0">ของรางวัลจากร้านค้าต่างๆ แลกด้วยแต้มร้านของคุณ</p>
    </div>

    <div class="rw-tabs">
      <a href="rewards.php?tab=list" class="<?= $tab === 'list' ? 'active' : '' ?>">รายการรางวัล</a>
      <a href="rewards.php?tab=history" class="<?= $tab === 'history' ? 'active' : '' ?>">
        ประวัติการแลก
        <?php if ($pendingCount > 0): ?><span class="badge"><?= $pendingCount ?></span><?php endif; ?>
      </a>
    </div>

    <?php if ($tab === 'history'): ?>

      <div class="rw-stats">
        <div class="rw-stat">
          <div class="rw-stat-icon green">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:#16a34a"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
          </div>
          <div>
            <span class="rw-stat-val"><?= $completedCount ?></span>
            <span class="rw-stat-lbl">แลกสำเร็จ</span>
          </div>
        </div>
        <div class="rw-stat">
          <div class="rw-stat-icon blue">
            <svg viewBox="0 0 24 24" style="width:22px;height:22px;fill:#2563eb"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2zm0-8h-2V7h2z"/></svg>
          </div>
          <div>
            <span class="rw-stat-val"><?= $pendingCount ?></span>
            <span class="rw-stat-lbl">รอสแกนที่ร้าน</span>
          </div>
        </div>
      </div>

      <?php if (empty($history)): ?>
        <div class="rw-empty">
          ยังไม่มีประวัติการแลกรางวัล<br>
          <small>ทำภารกิจที่ร้านค้าเพื่อสะสมแต้มร้าน แล้วไปแลกของรางวัลได้เลย</small>
        </div>
      <?php else: ?>
        <div class="rw-list">
          <?php foreach ($history as $h):
            $isPending = $h["status"] === "pending";
            $itemInner = '
              <div class="thumb">
                ' . ($h["image_url"]
                  ? '<img src="' . e($h["image_url"]) . '" alt="">'
                  : '<svg viewBox="0 0 24 24" style="width:26px;height:26px;fill:#2563eb"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>'
                ) . '
              </div>
              <div class="info">
                <strong>' . e($h["reward_name"]) . '</strong>
                <span>ร้าน ' . e($h["place_name"]) . '</span>
                <span class="status-pill status-' . e($h["status"]) . '">' . e($statusLabel[$h["status"]] ?? $h["status"]) . '</span>
              </div>
              <div class="side">
                <div class="pts">-' . number_format($h["points_cost"]) . '</div>
                <div class="date">' . date("j M Y", strtotime($h["created_at"])) . '</div>
              </div>
            ';
          ?>
            <?php if ($isPending): ?>
              <a href="redeem_wait.php?code=<?= urlencode($h["code"]) ?>" class="rw-item" style="text-decoration:none;color:inherit">
                <?= $itemInner ?>
              </a>
            <?php else: ?>
              <div class="rw-item">
                <?= $itemInner ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>

      <div class="rw-head">
        <h2>รางวัลทั้งหมด</h2>
        <span><?= count($rewardsAll) ?> รายการ</span>
      </div>

      <?php if (empty($rewardsAll)): ?>
        <div class="rw-empty">ยังไม่มีของรางวัลจากร้านค้าใดๆ</div>
      <?php else: ?>
        <?php foreach ($rewardsAll as $rw):
          $canRedeem  = $rw["my_points"] >= $rw["cost_points"] && $rw["stock"] > 0;
          $outOfStock = $rw["stock"] <= 0;
        ?>
          <div class="rw-card">
            <div class="rw-card-top">
              <div class="rw-img">
                <?php if ($rw["image_url"]): ?>
                  <img src="<?= e($rw["image_url"]) ?>" alt="<?= e($rw["name"]) ?>">
                <?php else: ?>
                  <div class="rw-img-ph">
                    <svg viewBox="0 0 24 24" style="width:36px;height:36px;fill:#2563eb"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
                  </div>
                <?php endif; ?>
              </div>
              <div class="rw-body">
                <p class="rw-name"><?= e($rw["name"]) ?></p>
                <span class="rw-shop-tag">จากร้าน: <?= e($rw["place_name"]) ?></span>
                <?php if ($rw["description"]): ?>
                  <p class="rw-desc"><?= e($rw["description"]) ?></p>
                <?php endif; ?>
                <div class="rw-pts">
                  <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                  <?= number_format($rw["cost_points"]) ?>
                  <small>แต้มร้าน</small>
                </div>
                <div class="rw-stock"><?= $rw["stock"] > 0 ? "คงเหลือ " . $rw["stock"] . " ชิ้น" : "หมดแล้ว" ?></div>
                <div class="rw-mypts">คุณมีแต้มร้านนี้ <?= number_format($rw["my_points"]) ?></div>
              </div>
            </div>
            <div class="rw-card-btn">
              <a class="rw-btn <?= $canRedeem ? '' : 'disabled' ?>" href="redeem_confirm.php?reward_id=<?= $rw["id"] ?>">
                <?= $outOfStock ? "หมดแล้ว" : ($canRedeem ? "แลกเลย" : "แต้มร้านไม่พอ") ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>

    <?php endif; ?>

  </main>
</body>
</html>
