<?php
require_once "_auth.php";

$userId  = intval($_SESSION["user_id"]);
$msg     = "";
$msgType = "bad";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("rewards.php");

    $rewardId = intval($_POST["reward_id"] ?? 0);

    $stmt = $conn->prepare("SELECT points FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userRow = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT * FROM rewards WHERE id=?");
    $stmt->bind_param("i", $rewardId);
    $stmt->execute();
    $reward = $stmt->get_result()->fetch_assoc();

    $isEligible = true;
    if ($reward && $reward["place_id"]) {
        $elg = $conn->prepare("
            SELECT 1 FROM user_quests uq JOIN quests q ON q.id = uq.quest_id
            WHERE uq.user_id=? AND q.place_id=? LIMIT 1
        ");
        $elg->bind_param("ii", $userId, $reward["place_id"]);
        $elg->execute();
        $isEligible = (bool) $elg->get_result()->fetch_assoc();
    }

    if (!$reward) {
        $msg = "ไม่พบของรางวัล";
    } elseif (!$isEligible) {
        $msg = "ต้องทำภารกิจที่ร้านนี้ก่อนจึงจะแลกรางวัลนี้ได้";
    } elseif ($reward["stock"] <= 0) {
        $msg = "ของรางวัลหมดแล้ว";
    } elseif ($userRow["points"] < $reward["cost_points"]) {
        $msg = "คะแนนไม่เพียงพอ";
    } else {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id=?");
            $stmt->bind_param("ii", $reward["cost_points"], $userId);
            if (!$stmt->execute()) throw new Exception();
            $stmt = $conn->prepare("UPDATE rewards SET stock = stock - 1 WHERE id=?");
            $stmt->bind_param("i", $rewardId);
            if (!$stmt->execute()) throw new Exception();
            $stmt = $conn->prepare("INSERT INTO reward_redemptions (user_id, reward_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $userId, $rewardId);
            if (!$stmt->execute()) throw new Exception();
            $conn->commit();
            $msg     = "แลกรางวัลสำเร็จ";
            $msgType = "good";
        } catch (Exception $e) {
            $conn->rollback();
            $msg = "เกิดข้อผิดพลาด กรุณาลองใหม่";
        }
    }
}

$stmt = $conn->prepare("SELECT points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM reward_redemptions WHERE user_id=?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$redeemCount = $stmt2->get_result()->fetch_assoc()["total"];

$rewards = $conn->query("
    SELECT r.*, p.name AS place_name
    FROM rewards r LEFT JOIN places p ON p.id = r.place_id
    ORDER BY r.cost_points ASC
")->fetch_all(MYSQLI_ASSOC);

$eligiblePlaceIds = array_column($conn->query("
    SELECT DISTINCT q.place_id FROM user_quests uq JOIN quests q ON q.id = uq.quest_id WHERE uq.user_id=$userId
")->fetch_all(MYSQLI_ASSOC), "place_id");

$tab = $_GET["tab"] ?? "list";

$stmt_h = $conn->prepare("
    SELECT rr.redeemed_at, r.name, r.cost_points, r.image_url
    FROM reward_redemptions rr
    JOIN rewards r ON r.id = rr.reward_id
    WHERE rr.user_id = ?
    ORDER BY rr.redeemed_at DESC
");
$stmt_h->bind_param("i", $userId);
$stmt_h->execute();
$history = $stmt_h->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .rw-page { max-width:100%; background:#f1f5f9; }

    /* Stats row */
    .rw-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin-bottom: 20px;
    }

    .rw-stat {
      background: #fff;
      border-radius: 20px;
      padding: 16px;
      display: flex;
      align-items: center;
      gap: 12px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
    }

    .rw-stat-icon {
      width: 44px; height: 44px;
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 22px;
      flex-shrink: 0;
    }

    .rw-stat-icon.blue   { background: #dbeafe; }
    .rw-stat-icon.green  { background: #dcfce7; }

    .rw-stat-val {
      font-size: 22px;
      font-weight: 700;
      color: #2563eb;
      line-height: 1;
      display: block;
    }

    .rw-stat-lbl {
      font-size: 12px;
      color: #64748b;
      display: block;
      margin-top: 3px;
    }

    /* Alert */
    .rw-alert {
      padding: 13px 16px;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 16px;
    }
    .rw-alert.good { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
    .rw-alert.bad  { background:#fff1f2; color:#e11d48; border:1px solid #fecdd3; }

    /* Section head */
    .rw-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
    }
    .rw-head h2 { font-size:17px; font-weight:700; color:#0f172a; margin:0; }
    .rw-head span { font-size:13px; color:#64748b; }

    /* Reward card — horizontal */
    .rw-list { display: grid; gap: 12px; }

    .rw-card {
      background: #fff;
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
    }

    .rw-card-top {
      display: flex;
      align-items: flex-start;
      padding: 14px;
      gap: 14px;
    }

    .rw-img {
      width: 100px;
      height: 100px;
      flex-shrink: 0;
      border-radius: 14px;
      overflow: hidden;
      background: #f8fafc;
    }

    .rw-img img {
      width: 100%; height: 100%;
      object-fit: cover;
      display: block;
    }

    .rw-img-ph {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
    }

    .rw-body {
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 5px;
      min-width: 0;
    }

    .rw-name {
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
      margin: 0;
    }

    .rw-desc {
      font-size: 12px;
      color: #64748b;
      margin: 0;
      line-height: 1.4;
    }

    .rw-pts {
      display: flex;
      align-items: center;
      gap: 5px;
      font-size: 15px;
      font-weight: 700;
      color: #0f172a;
    }

    .rw-pts svg { width:16px; height:16px; fill:#f59e0b; flex-shrink:0; }
    .rw-pts small { font-size:12px; color:#64748b; font-weight:400; }

    .rw-stock { font-size: 13px; color: #16a34a; font-weight: 500; }

    .rw-card-btn {
      padding: 0 14px 14px;
    }

    .rw-btn {
      display: block;
      width: 100%;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 999px;
      padding: 14px;
      font-family: inherit;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      text-align: center;
      transition: opacity .2s;
    }

    .rw-btn:disabled {
      background: #e2e8f0;
      color: #94a3b8;
      cursor: not-allowed;
    }

    .rw-btn.out { background: #f1f5f9; color: #94a3b8; }
    .rw-btn:not(:disabled):not(.out):hover { opacity:.85; }

    .bg-teal   { background: #e0f2fe; }
    .bg-blue   { background: #dbeafe; }
    .bg-green  { background: #dcfce7; }
    .bg-purple { background: #ede9fe; }
    .bg-orange { background: #fef3c7; }
  </style>
</head>
<body>
  <main class="app rw-page" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <?php if ($msg): ?>
      <div class="rw-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <!-- Header -->
    <div style="margin-bottom:16px">
      <h1 style="font-size:26px;font-weight:700;color:#0f172a;margin:0 0 4px">แลกรางวัล</h1>
      <p style="font-size:13px;color:#64748b;margin:0">ใช้คะแนนของคุณแลกของรางวัลสุดพิเศษ</p>
    </div>

    <!-- Stats card -->
    <div style="background:#fff;border-radius:20px;padding:18px 20px;margin-bottom:20px;box-shadow:0 2px 10px rgba(15,23,42,.07);display:flex;align-items:center">
      <div style="flex:1;border-right:1px solid #f1f5f9;padding-right:16px">
        <p style="font-size:12px;color:#64748b;margin:0 0 10px">คะแนนของคุณ</p>
        <div style="display:flex;align-items:center;gap:10px">
          <svg viewBox="0 0 24 24" style="width:34px;height:34px;fill:#2563eb;flex-shrink:0"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
          <div>
            <div style="font-size:28px;font-weight:700;color:#2563eb;line-height:1"><?= number_format($user["points"]) ?></div>
            <div style="font-size:12px;color:#2563eb;margin-top:2px">คะแนน</div>
          </div>
        </div>
      </div>
      <div style="flex:1;padding-left:16px">
        <p style="font-size:12px;color:#64748b;margin:0 0 10px">สิทธิ์แลกรางวัล</p>
        <div style="display:flex;align-items:center;gap:10px">
          <svg viewBox="0 0 24 24" style="width:34px;height:34px;fill:#16a34a;flex-shrink:0"><path d="M22 10V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v4c1.1 0 2 .9 2 2s-.9 2-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4c-1.1 0-2-.9-2-2s.9-2 2-2zm-2-1.46A4 4 0 0 0 18 12a4 4 0 0 0 2 3.46V18H4v-2.54A4 4 0 0 0 6 12a4 4 0 0 0-2-3.46V6h16v2.54z"/></svg>
          <div>
            <div style="font-size:28px;font-weight:700;color:#0f172a;line-height:1"><?= $redeemCount ?></div>
            <div style="font-size:12px;color:#64748b;margin-top:2px">สิทธิ์</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div style="display:flex;border-bottom:2px solid #e2e8f0;margin-bottom:18px">
      <a href="rewards.php?tab=list" style="flex:1;text-align:center;padding:12px 0;font-size:14px;font-weight:600;text-decoration:none;color:<?= $tab==='list'?'#2563eb':'#94a3b8' ?>;border-bottom:<?= $tab==='list'?'2px solid #2563eb':'2px solid transparent' ?>;margin-bottom:-2px">
        รายการรางวัล
      </a>
      <a href="rewards.php?tab=history" style="flex:1;text-align:center;padding:12px 0;font-size:14px;font-weight:600;text-decoration:none;color:<?= $tab==='history'?'#2563eb':'#94a3b8' ?>;border-bottom:<?= $tab==='history'?'2px solid #2563eb':'2px solid transparent' ?>;margin-bottom:-2px">
        แลกไปแล้ว <?php if(count($history)>0): ?><span style="background:#ef4444;color:#fff;border-radius:99px;padding:1px 6px;font-size:11px"><?= count($history) ?></span><?php endif; ?>
      </a>
    </div>

    <?php if ($tab === 'history'): ?>
      <!-- History tab -->
      <?php if (empty($history)): ?>
        <div style="text-align:center;padding:40px 20px;background:#fff;border-radius:20px;color:#94a3b8;font-size:14px">
          ยังไม่มีประวัติการแลกรางวัล
        </div>
      <?php else: ?>
        <div style="display:grid;gap:10px">
          <?php foreach ($history as $h): ?>
            <div style="background:#fff;border-radius:18px;padding:14px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 8px rgba(15,23,42,.06)">
              <div style="width:60px;height:60px;border-radius:14px;overflow:hidden;flex-shrink:0;background:#f1f5f9;display:flex;align-items:center;justify-content:center">
                <?php if ($h["image_url"]): ?>
                  <img src="<?= e($h["image_url"]) ?>" style="width:100%;height:100%;object-fit:cover">
                <?php else: ?>
                  <svg viewBox="0 0 24 24" style="width:28px;height:28px;fill:#94a3b8"><path d="M20 7h-4V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v2H4a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1zm-10-2h4v2h-4V5zm9 14H5V9h14v10z"/></svg>
                <?php endif; ?>
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:15px;font-weight:600;color:#0f172a"><?= e($h["name"]) ?></div>
                <div style="font-size:13px;color:#64748b;margin-top:2px"><?= number_format($h["cost_points"]) ?> คะแนน</div>
              </div>
              <div style="text-align:right;flex-shrink:0">
                <div style="font-size:12px;color:#16a34a;font-weight:600">แลกเมื่อ</div>
                <div style="font-size:12px;color:#64748b;margin-top:2px">
                  <?= date("j M Y", strtotime($h["redeemed_at"])) ?>
                </div>
                <div style="font-size:11px;color:#94a3b8"><?= date("H:i", strtotime($h["redeemed_at"])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php else: ?>
    <!-- Rewards list -->
    <div class="rw-head">
      <h2>รางวัลทั้งหมด</h2>
      <span><?= count($rewards) ?> รายการ</span>
    </div>

    <?php
    $bgs = ['bg-teal','bg-blue','bg-green','bg-purple','bg-orange'];
    $phs = ['🥤','👕','🏷️','🌿','⭐'];
    ?>

    <div class="rw-list">
      <?php foreach ($rewards as $i => $rw):
        $isEligible = !$rw["place_id"] || in_array($rw["place_id"], $eligiblePlaceIds);
        $canRedeem  = $isEligible && $user["points"] >= $rw["cost_points"] && $rw["stock"] > 0;
        $outOfStock = $rw["stock"] <= 0;
        $bg = $bgs[$i % count($bgs)];
        $ph = $phs[$i % count($phs)];
      ?>
        <div class="rw-card">
          <div class="rw-card-top">
            <div class="rw-img <?= !$rw["image_url"] ? $bg : '' ?>">
              <?php if ($rw["image_url"]): ?>
                <img src="<?= e($rw["image_url"]) ?>" alt="<?= e($rw["name"]) ?>">
              <?php else: ?>
                <div class="rw-img-ph">
                  <svg viewBox="0 0 24 24" style="width:42px;height:42px;fill:#94a3b8"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
                </div>
              <?php endif; ?>
            </div>
            <div class="rw-body">
              <p class="rw-name"><?= e($rw["name"]) ?></p>
              <?php if ($rw["place_id"]): ?>
                <span style="font-size:10.5px;font-weight:600;color:#2563eb;background:#eff6ff;padding:2px 8px;border-radius:99px;width:fit-content">จากร้าน: <?= e($rw["place_name"]) ?></span>
              <?php endif; ?>
              <?php if ($rw["description"]): ?>
                <p class="rw-desc"><?= e($rw["description"]) ?></p>
              <?php endif; ?>
              <div class="rw-pts">
                <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
                <?= number_format($rw["cost_points"]) ?>
                <small>คะแนน</small>
              </div>
              <div class="rw-stock">คงเหลือ <?= $rw["stock"] ?> ชิ้น</div>
            </div>
          </div>

          <div class="rw-card-btn">
            <form method="post">
              <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
              <input type="hidden" name="reward_id" value="<?= e($rw["id"]) ?>">
              <button class="rw-btn <?= $outOfStock ? 'out' : '' ?>"
                      type="submit"
                      <?= $canRedeem ? '' : 'disabled' ?>>
                <?= $outOfStock ? 'หมดแล้ว' : (!$isEligible ? 'ต้องทำภารกิจร้านนี้ก่อน' : ($canRedeem ? 'แลกเลย' : 'คะแนนไม่พอ')) ?>
              </button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php endif; // end tab list ?>

  </main>
</body>
</html>
