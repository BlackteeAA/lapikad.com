<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

$stmt = $conn->prepare("SELECT name, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM user_quests WHERE user_id=?");
$stmt2->bind_param("i", $userId);
$stmt2->execute();
$completed = $stmt2->get_result()->fetch_assoc()["total"];

$visitedPlaces = $conn
  ->query("SELECT COUNT(DISTINCT q.place_id) AS total FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=$userId")
  ->fetch_assoc()["total"];

$stmt3 = $conn->prepare("SELECT COUNT(*)+1 AS rank FROM users WHERE points > (SELECT points FROM users WHERE id=?)");
$stmt3->bind_param("i", $userId);
$stmt3->execute();
$userRank = $stmt3->get_result()->fetch_assoc()["rank"];

$stmt4 = $conn->prepare("
  SELECT uq.completed_at, q.title, q.reward_points, p.name AS place_name
  FROM user_quests uq
  JOIN quests q ON q.id = uq.quest_id
  JOIN places p ON p.id = q.place_id
  WHERE uq.user_id = ?
  ORDER BY uq.completed_at DESC
  LIMIT 6
");
$stmt4->bind_param("i", $userId);
$stmt4->execute();
$activities = $stmt4->get_result()->fetch_all(MYSQLI_ASSOC);

function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60) return "เมื่อกี้";
    if ($d < 3600) return intval($d/60) . " นาทีที่แล้ว";
    if ($d < 86400) return intval($d/3600) . " ชั่วโมงที่แล้ว";
    return intval($d/86400) . " วันที่แล้ว";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>หน้าหลัก | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body {
      font-family: 'Kanit', sans-serif;
      background: #f1f5f9 !important;
      background-image: none !important;
    }

    /* ── Hero ── */
    .db-hero {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-bottom: 20px;
    }

    .db-hero-text h1 {
      font-size: 32px;
      font-weight: 700;
      color: #0f172a;
      margin: 0 0 4px;
      line-height: 1.1;
    }

    .db-hero-text p {
      font-size: 14px;
      font-weight: 400;
      color: #64748b;
      margin: 0;
    }

    .db-rank {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #fff;
      border-radius: 16px;
      padding: 12px 16px;
      box-shadow: 0 2px 10px rgba(15,23,42,.08);
      flex-shrink: 0;
    }

    .db-rank-icon {
      width: 46px;
      height: 46px;
      background: #fef3c7;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .db-rank-icon svg { width: 26px; height: 26px; fill: #f59e0b; }

    .db-rank-text { line-height: 1.2; }
    .db-rank-text small { font-size: 11px; color: #94a3b8; display: block; margin-bottom: 2px; }
    .db-rank-text strong { font-size: 22px; font-weight: 700; color: #0f172a; display: block; }
    .db-rank-text span { font-size: 11px; color: #64748b; display: block; margin-top: 1px; }

    /* ── Stats ── */
    .db-stats {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin-bottom: 10px;
    }

    .db-stat {
      border-radius: 20px;
      padding: 18px 16px;
      color: #fff;
      position: relative;
      overflow: hidden;
    }

    .db-stat p { font-size: 13px; font-weight: 400; opacity: .9; margin: 0 0 6px; }

    .db-stat h2 {
      font-size: 34px;
      font-weight: 700;
      margin: 0 0 4px;
      line-height: 1;
    }

    .db-stat span { font-size: 13px; font-weight: 400; opacity: .85; }

    .db-stat-icon {
      position: absolute;
      right: 14px;
      bottom: 14px;
      width: 36px;
      height: 36px;
      opacity: .3;
      fill: #fff;
    }

    .db-stat-wide {
      grid-column: 1 / -1;
    }

    .s-blue   { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .s-green  { background: linear-gradient(135deg, #22c55e, #16a34a); }
    .s-purple { background: linear-gradient(135deg, #a78bfa, #7c3aed); }

    /* ── Activity ── */
    .db-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin: 20px 0 12px;
    }

    .db-section h3 { font-size: 16px; font-weight: 700; color: #0f172a; margin: 0; }
    .db-section a  { font-size: 13px; font-weight: 500; color: #2563eb; text-decoration: none; }

    .act-list { display: grid; gap: 9px; margin-bottom: 24px; }

    .act-item {
      display: flex;
      align-items: center;
      gap: 12px;
      background: rgba(255,255,255,.78);
      backdrop-filter: blur(6px);
      border-radius: 16px;
      padding: 12px 14px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
    }

    .act-dot {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .act-dot svg { width: 18px; height: 18px; fill: #fff; }

    .act-body { flex: 1; min-width: 0; }

    .act-body strong {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .act-body span { font-size: 12px; color: #64748b; }

    .act-right { text-align: right; flex-shrink: 0; }
    .act-pts { font-size: 13px; font-weight: 700; color: #16a34a; display: block; }
    .act-time { font-size: 11px; color: #94a3b8; }

    .act-empty {
      text-align: center;
      padding: 28px 16px;
      color: #94a3b8;
      font-size: 14px;
      background: rgba(255,255,255,.6);
      border-radius: 16px;
    }
  </style>
</head>
<body>
  <main class="app dashboard-page" style="max-width:100%;">
    <?php include "includes/topbar.php"; ?>

    <!-- Hero -->
    <div class="db-hero">
      <div class="db-hero-text">
        <h1>หน้าหลัก</h1>
        <p>ยินดีต้อนรับคุณ <?= e($user["name"]) ?></p>
      </div>
      <div class="db-rank">
        <div class="db-rank-icon">
          <svg viewBox="0 0 24 24"><path d="M19 5h-2V3H7v2H5C3.9 5 3 5.9 3 7v1c0 2.55 1.92 4.63 4.39 4.94.63 1.5 1.98 2.63 3.61 2.96V18H9v2h6v-2h-2v-2.1c1.63-.33 2.98-1.46 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM5 8V7h2v3.82C5.84 10.4 5 9.3 5 8zm14 0c0 1.3-.84 2.4-2 2.82V7h2v1z"/></svg>
        </div>
        <div class="db-rank-text">
          <small>อันดับของคุณ</small>
          <strong>#<?= $userRank ?></strong>
          <span>จากผู้เล่นทั้งหมด</span>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <section class="db-stats">
      <div class="db-stat s-blue">
        <p>คะแนนของฉัน</p>
        <h2><?= number_format($user["points"]) ?></h2>
        <span>คะแนน</span>
        <svg class="db-stat-icon" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/></svg>
      </div>
      <div class="db-stat s-green">
        <p>ภารกิจที่ทำสำเร็จ</p>
        <h2><?= $completed ?></h2>
        <span>ภารกิจ</span>
        <svg class="db-stat-icon" viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      </div>
      <div class="db-stat s-purple db-stat-wide">
        <p>สถานที่ที่เช็คอิน</p>
        <h2><?= $visitedPlaces ?></h2>
        <span>แห่ง</span>
        <svg class="db-stat-icon" viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
      </div>
    </section>

    <!-- Recent Activities -->
    <div class="db-section">
      <h3>กิจกรรมล่าสุด</h3>
      <a href="places.php">ดูทั้งหมด</a>
    </div>

    <?php if (empty($activities)): ?>
      <div class="act-empty">ยังไม่มีกิจกรรม — ออกล่าพิกัดเลย!</div>
    <?php else: ?>
      <div class="act-list">
        <?php
        $dotColors = ['#22c55e','#8b5cf6','#f59e0b','#3b82f6','#ef4444','#06b6d4'];
        foreach ($activities as $i => $a):
          $c = $dotColors[$i % count($dotColors)];
        ?>
          <div class="act-item">
            <div class="act-dot" style="background:<?= $c ?>">
              <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="act-body">
              <strong><?= e($a["title"]) ?></strong>
              <span><?= e($a["place_name"]) ?></span>
            </div>
            <div class="act-right">
              <span class="act-pts">+<?= $a["reward_points"] ?> คะแนน</span>
              <span class="act-time"><?= timeAgo($a["completed_at"]) ?></span>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</body>
</html>
