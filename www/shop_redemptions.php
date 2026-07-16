<?php
require_once "_shop.php";

$placeId = intval($shopPlace["id"]);

// Lazily resolve any overdue pending redemptions for this shop (no cron in this app).
$pendingRows = $conn->query("SELECT * FROM shop_redemptions WHERE place_id=$placeId AND status='pending'")->fetch_all(MYSQLI_ASSOC);
foreach ($pendingRows as $pr) {
    expireIfNeeded($conn, $pr);
}

$dateFrom = $_GET["from"] ?? date("Y-m-d", strtotime("-30 days"));
$dateTo   = $_GET["to"] ?? date("Y-m-d");

$stmt = $conn->prepare("
    SELECT sr.*, r.name AS reward_name, u.name AS customer_name
    FROM shop_redemptions sr
    JOIN rewards r ON r.id = sr.reward_id
    JOIN users u ON u.id = sr.user_id
    WHERE sr.place_id=? AND DATE(sr.created_at) BETWEEN ? AND ?
    ORDER BY sr.created_at DESC
");
$stmt->bind_param("iss", $placeId, $dateFrom, $dateTo);
$stmt->execute();
$redemptions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusLabel = ["pending" => "รอสแกน", "completed" => "สำเร็จ", "expired" => "หมดเวลา", "cancelled" => "ยกเลิก"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ประวัติการแลกรางวัล | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .filter-card {
      background:#fff;border-radius:16px;padding:14px;box-shadow:0 2px 10px rgba(15,23,42,.06);
      margin-bottom:14px;display:flex;gap:8px;align-items:end;flex-wrap:wrap;
    }
    .filter-card label { display:block;font-size:11.5px;color:#64748b;margin-bottom:4px; }
    .filter-card input[type=date] {
      border:1.5px solid #e2e8f0;border-radius:10px;padding:8px 10px;font-family:inherit;font-size:13px;
    }
    .filter-card button {
      background:#2563eb;color:#fff;border:none;border-radius:10px;padding:9px 16px;
      font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;
    }

    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span { font-size:11.5px;color:#94a3b8;display:block;margin-top:2px; }

    .row-side { text-align:right;flex-shrink:0; }
    .row-side .pts { font-size:14px;font-weight:700;color:#e11d48; }
    .row-side .code { font-size:10.5px;color:#94a3b8;font-family:monospace;margin-top:2px; }

    .status-pill { font-size:10.5px;font-weight:600;padding:3px 9px;border-radius:99px;white-space:nowrap;margin-top:4px;display:inline-block; }
    .status-pending   { background:#fef9c3;color:#a16207; }
    .status-completed { background:#f0fdf4;color:#16a34a; }
    .status-expired   { background:#f1f5f9;color:#64748b; }
    .status-cancelled { background:#fff1f2;color:#e11d48; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="shop.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>ประวัติการแลกรางวัล</h1>
    </div>

    <form method="get" class="filter-card">
      <div>
        <label>จากวันที่</label>
        <input type="date" name="from" value="<?= e($dateFrom) ?>">
      </div>
      <div>
        <label>ถึงวันที่</label>
        <input type="date" name="to" value="<?= e($dateTo) ?>">
      </div>
      <button type="submit">กรอง</button>
    </form>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="redemption-search" placeholder="ค้นหาชื่อลูกค้า / ของรางวัล" oninput="filterRedemptions(this.value)">
    </div>

    <div class="pg-card" id="redemptions-list">
      <?php if (empty($redemptions)): ?>
        <div class="row-item"><span style="font-size:13px;color:#94a3b8">ไม่พบรายการในช่วงเวลานี้</span></div>
      <?php else: ?>
        <?php foreach ($redemptions as $r): ?>
          <div class="row-item" data-search="<?= e(mb_strtolower($r["customer_name"] . " " . $r["reward_name"], "UTF-8")) ?>">
            <div class="row-info">
              <strong><?= e($r["customer_name"]) ?></strong>
              <span><?= e($r["reward_name"]) ?> · <?= date("j M Y H:i", strtotime($r["created_at"])) ?></span>
              <span class="status-pill status-<?= e($r["status"]) ?>"><?= $statusLabel[$r["status"]] ?? $r["status"] ?></span>
            </div>
            <div class="row-side">
              <div class="pts">-<?= number_format($r["points_cost"]) ?></div>
              <div class="code"><?= e($r["code"]) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
      <div id="redemptions-empty" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบรายการที่ค้นหา</div>
    </div>

  </main>
  <script>
  function filterRedemptions(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#redemptions-list .row-item[data-search]').forEach(row => {
      const match = !q || row.dataset.search.includes(q);
      row.style.display = match ? 'flex' : 'none';
      if (match) visible++;
    });
    document.getElementById('redemptions-empty').style.display = (visible === 0 && q) ? 'block' : 'none';
  }
  </script>
</body>
</html>
