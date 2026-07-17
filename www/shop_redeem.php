<?php
require_once "_auth.php";

$userId  = intval($_SESSION["user_id"]);
$placeId = intval($_GET["place_id"] ?? 0);

$stmt = $conn->prepare("SELECT * FROM places WHERE id=? AND owner_user_id IS NOT NULL");
$stmt->bind_param("i", $placeId);
$stmt->execute();
$place = $stmt->get_result()->fetch_assoc();

if (!$place) redirect("places.php");

$stmt = $conn->prepare("SELECT points FROM user_shop_points WHERE user_id=? AND place_id=?");
$stmt->bind_param("ii", $userId, $placeId);
$stmt->execute();
$balRow   = $stmt->get_result()->fetch_assoc();
$myPoints = $balRow ? intval($balRow["points"]) : 0;

$rewards = $conn->query("SELECT * FROM rewards WHERE place_id=$placeId ORDER BY cost_points ASC")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>แลกของรางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .rw-balance {
      background:#fff;border-radius:20px;padding:18px 20px;margin-bottom:20px;
      box-shadow:0 2px 10px rgba(15,23,42,.07);display:flex;align-items:center;gap:14px;
    }
    .rw-balance .name { font-size:13px;color:#64748b;margin:0 0 6px; }
    .rw-balance .val { font-size:30px;font-weight:700;color:#2563eb;line-height:1; }
    .rw-balance svg { width:36px;height:36px;fill:#2563eb;flex-shrink:0; }

    .rw-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px; }
    .rw-head h2 { font-size:17px;font-weight:700;color:#0f172a;margin:0; }
    .rw-head span { font-size:13px;color:#64748b; }

    .rw-list { display:grid;gap:12px; }
    .rw-card { background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 2px 8px rgba(15,23,42,.06); }
    .rw-card-top { display:flex;align-items:flex-start;padding:14px;gap:14px; }
    .rw-img { width:100px;height:100px;flex-shrink:0;border-radius:14px;overflow:hidden;background:#f8fafc; }
    .rw-img img { width:100%;height:100%;object-fit:cover;display:block; }
    .rw-img-ph { width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:#eff6ff; }

    .rw-body { flex:1;display:flex;flex-direction:column;gap:5px;min-width:0; }
    .rw-name { font-size:15px;font-weight:700;color:#0f172a;margin:0; }
    .rw-desc { font-size:12px;color:#64748b;margin:0;line-height:1.4; }
    .rw-pts { display:flex;align-items:center;gap:5px;font-size:15px;font-weight:700;color:#0f172a; }
    .rw-pts svg { width:16px;height:16px;fill:#f59e0b;flex-shrink:0; }
    .rw-pts small { font-size:12px;color:#64748b;font-weight:400; }
    .rw-stock { font-size:13px;color:#16a34a;font-weight:500; }

    .rw-card-btn { padding:0 14px 14px; }
    .rw-btn {
      display:block;width:100%;background:#2563eb;color:#fff;border:none;border-radius:999px;
      padding:14px;font-family:inherit;font-size:15px;font-weight:600;cursor:pointer;
      text-align:center;text-decoration:none;transition:opacity .2s;
    }
    .rw-btn.disabled { background:#e2e8f0;color:#94a3b8;pointer-events:none; }
    .rw-btn:hover { opacity:.85; }

    .rw-empty { text-align:center;padding:40px 20px;background:#fff;border-radius:20px;color:#94a3b8;font-size:14px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="place.php?id=<?= $placeId ?>" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="rw-balance">
      <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
      <div>
        <p class="name">แต้มร้าน <?= e($place["name"]) ?> ของคุณ</p>
        <div class="val"><?= number_format($myPoints) ?> คะแนน</div>
      </div>
    </div>

    <div class="rw-head">
      <h2>ของรางวัลของร้านนี้</h2>
      <span><?= count($rewards) ?> รายการ</span>
    </div>

    <?php if (empty($rewards)): ?>
      <div class="rw-empty">ร้านนี้ยังไม่มีของรางวัล</div>
    <?php else: ?>
      <div class="rw-list">
        <?php foreach ($rewards as $rw):
          $canRedeem = $myPoints >= $rw["cost_points"] && $rw["stock"] > 0;
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
                <?php if ($rw["description"]): ?>
                  <p class="rw-desc"><?= e($rw["description"]) ?></p>
                <?php endif; ?>
                <div class="rw-pts">
                  <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                  <?= number_format($rw["cost_points"]) ?>
                  <small>คะแนน</small>
                </div>
                <div class="rw-stock"><?= $rw["stock"] > 0 ? "คงเหลือ " . $rw["stock"] . " ชิ้น" : "หมดแล้ว" ?></div>
              </div>
            </div>
            <div class="rw-card-btn">
              <a class="rw-btn <?= $canRedeem ? '' : 'disabled' ?>" href="redeem_confirm.php?reward_id=<?= $rw["id"] ?>">
                <?= $rw["stock"] <= 0 ? "หมดแล้ว" : ($canRedeem ? "แลกเลย" : "คะแนนไม่พอ") ?>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</body>
</html>
