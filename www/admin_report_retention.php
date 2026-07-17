<?php
require_once "_auth.php";

$role = $_SESSION["role"] ?? "";
if (!in_array($role, ["admin", "shop"], true)) {
    redirect("dashboard.php");
}

$isShop = $role === "shop";
$ownPlaceId = 0;
if ($isShop) {
    $shopStmt = $conn->prepare("SELECT id, name FROM places WHERE owner_user_id=?");
    $shopStmt->bind_param("i", $_SESSION["user_id"]);
    $shopStmt->execute();
    $ownPlace = $shopStmt->get_result()->fetch_assoc();
    if (!$ownPlace) redirect("dashboard.php");
    $ownPlaceId = (int)$ownPlace["id"];
}

$selectedPlaceId = $isShop ? $ownPlaceId : intval($_GET["place_id"] ?? 0);

// Bound the report to a date range by default so it doesn't scan the site's
// entire history on every view; "ดูข้อมูลทั้งหมด" opts back into all-time.
$dateFrom = $_GET["from"] ?? date("Y-m-d", strtotime("-90 days"));
$dateTo   = $_GET["to"] ?? date("Y-m-d");
$allTime  = isset($_GET["all"]) && $_GET["all"] === "1";

// A "visit" is one distinct (place, customer, day) combination, coming from either
// a completed quest at that place or a completed reward redemption at that place.
$visitSql = "
    SELECT v.place_id, v.user_id, u.name AS user_name, v.visit_date
    FROM (
        SELECT q.place_id AS place_id, uq.user_id AS user_id, uq.completed_date AS visit_date
        FROM user_quests uq JOIN quests q ON q.id = uq.quest_id
        UNION
        SELECT sr.place_id AS place_id, sr.user_id AS user_id, DATE(sr.created_at) AS visit_date
        FROM shop_redemptions sr WHERE sr.status = 'completed'
    ) v
    JOIN users u ON u.id = v.user_id
";

$visitConditions = [];
$visitTypes  = "";
$visitParams = [];
if ($selectedPlaceId > 0) {
    $visitConditions[] = "v.place_id = ?";
    $visitTypes  .= "i";
    $visitParams[] = $selectedPlaceId;
}
if (!$allTime) {
    $visitConditions[] = "v.visit_date BETWEEN ? AND ?";
    $visitTypes  .= "ss";
    $visitParams[] = $dateFrom;
    $visitParams[] = $dateTo;
}

$visitWhere = $visitConditions ? " WHERE " . implode(" AND ", $visitConditions) : "";
$stmt = $conn->prepare($visitSql . $visitWhere);
if ($visitParams) {
    $stmt->bind_param($visitTypes, ...$visitParams);
}
$stmt->execute();
$visitRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$byPlace = [];
$userNames = [];
foreach ($visitRows as $r) {
    $pid = (int)$r["place_id"];
    $uid = (int)$r["user_id"];
    $userNames[$uid] = $r["user_name"];
    $byPlace[$pid][$uid][$r["visit_date"]] = true;
}

$placeSummary = [];
foreach ($byPlace as $pid => $users) {
    $total = count($users);
    $repeat = 0;
    $sumVisits = 0;
    foreach ($users as $dates) {
        $c = count($dates);
        $sumVisits += $c;
        if ($c >= 2) $repeat++;
    }
    $placeSummary[$pid] = [
        "total"     => $total,
        "repeat"    => $repeat,
        "rate"      => $total > 0 ? round($repeat / $total * 100, 1) : 0.0,
        "avg"       => $total > 0 ? round($sumVisits / $total, 2) : 0.0,
        "sumVisits" => $sumVisits,
    ];
}

$placeNames = [];
$namesRes = $conn->query("SELECT id, name FROM places");
while ($row = $namesRes->fetch_assoc()) $placeNames[(int)$row["id"]] = $row["name"];

$overallTotal = 0; $overallRepeat = 0; $overallSumVisits = 0;
foreach ($placeSummary as $s) {
    $overallTotal     += $s["total"];
    $overallRepeat    += $s["repeat"];
    $overallSumVisits += $s["sumVisits"];
}
$overallRate = $overallTotal > 0 ? round($overallRepeat / $overallTotal * 100, 1) : 0.0;
$overallAvg  = $overallTotal > 0 ? round($overallSumVisits / $overallTotal, 2) : 0.0;

$customerDetail = [];
if ($selectedPlaceId > 0 && isset($byPlace[$selectedPlaceId])) {
    foreach ($byPlace[$selectedPlaceId] as $uid => $dates) {
        $customerDetail[] = [
            "name"  => $userNames[$uid],
            "count" => count($dates),
            "last"  => max(array_keys($dates)),
        ];
    }
    usort($customerDetail, fn($a, $b) => $b["count"] <=> $a["count"]);
}

$placeSummaryRows = [];
if ($selectedPlaceId === 0) {
    foreach ($placeSummary as $pid => $s) {
        $placeSummaryRows[] = array_merge($s, ["place_id" => $pid, "place_name" => $placeNames[$pid] ?? "-"]);
    }
    usort($placeSummaryRows, fn($a, $b) => $b["total"] <=> $a["total"]);
}

$placeOptions = [];
if (!$isShop) {
    foreach ($placeSummary as $pid => $s) {
        $placeOptions[] = ["id" => $pid, "name" => $placeNames[$pid] ?? "-"];
    }
    usort($placeOptions, fn($a, $b) => strcmp($a["name"], $b["name"]));
}

$selectedPlaceName = $selectedPlaceId > 0 ? ($placeNames[$selectedPlaceId] ?? "") : "";

$reportQuery = [];
if ($selectedPlaceId > 0) $reportQuery["place_id"] = $selectedPlaceId;
if ($allTime) {
    $reportQuery["all"] = "1";
} else {
    $reportQuery["from"] = $dateFrom;
    $reportQuery["to"]   = $dateTo;
}
$reportQueryString = http_build_query($reportQuery);
$pdfHref = "admin_report_retention_pdf.php" . ($reportQueryString ? "?" . $reportQueryString : "");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>รายงานลูกค้ากลับมาซ้ำ | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }

    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px;flex-wrap:wrap; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:10px 16px;border:none;border-radius:999px;text-decoration:none;
      font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;transition:opacity .2s;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }

    .filter-card {
      background:#fff;border-radius:16px;padding:14px;box-shadow:0 2px 10px rgba(15,23,42,.06);
      margin-bottom:14px;display:flex;gap:10px;align-items:end;flex-wrap:wrap;justify-content:space-between;
    }
    .filter-card label { display:block;font-size:11.5px;color:#64748b;margin-bottom:4px; }
    .filter-card select {
      border:1.5px solid #e2e8f0;border-radius:10px;padding:9px 12px;font-family:inherit;font-size:13px;min-width:200px;
    }
    .shop-scope-note { font-size:13px;color:#64748b; }
    .shop-scope-note strong { color:#0f172a; }

    .stat-grid { display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:14px; }
    @media (min-width:480px) { .stat-grid { grid-template-columns:repeat(4,1fr); } }
    .stat-tile { background:#fff;border-radius:16px;padding:16px 8px;text-align:center;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .stat-tile .icon-circle { width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 8px; }
    .stat-tile .icon-circle svg { width:17px;height:17px; }
    .stat-tile .num { font-size:19px;font-weight:700;color:#0f172a;line-height:1.1; }
    .stat-tile .label { font-size:10.5px;color:#64748b;margin-top:3px;line-height:1.3; }

    .sec-head { display:flex;align-items:center;gap:8px;margin:20px 0 10px; }
    .sec-head h2 { font-size:15px;font-weight:700;color:#0f172a;margin:0; }

    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:14px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    table.rpt-table { width:100%;border-collapse:collapse; }
    table.rpt-table th, table.rpt-table td { padding:10px 8px;font-size:12.5px;text-align:center;border-bottom:1px solid #f1f5f9; }
    table.rpt-table th { color:#64748b;font-weight:600;font-size:11.5px; }
    table.rpt-table td:first-child, table.rpt-table th:first-child { text-align:left; }
    table.rpt-table tbody tr:last-child td { border-bottom:none; }
    table.rpt-table a { color:#2563eb;font-weight:600;text-decoration:none; }
    table.rpt-table a:hover { text-decoration:underline; }

    .empty-note { text-align:center;padding:20px;color:#94a3b8;font-size:13px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="<?= $isShop ? 'shop.php' : 'admin.php' ?>" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>รายงานลูกค้ากลับมาซ้ำ</h1>
      <a href="<?= e($pdfHref) ?>" class="adm-btn primary">ดาวน์โหลด PDF</a>
    </div>

    <?php if ($isShop): ?>
      <div class="filter-card">
        <span class="shop-scope-note">ร้านค้า: <strong><?= e($ownPlace["name"]) ?></strong></span>
      </div>
    <?php else: ?>
      <div class="filter-card">
        <div>
          <label>เลือกดูรายงาน</label>
          <select onchange="location.href = this.value ? 'admin_report_retention.php?place_id=' + this.value : 'admin_report_retention.php';">
            <option value="">ทุกร้านค้า</option>
            <?php foreach ($placeOptions as $p): ?>
              <option value="<?= $p['id'] ?>" <?= $selectedPlaceId === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($selectedPlaceId > 0): ?>
          <a href="admin_report_retention.php" class="adm-btn" style="background:#eff6ff;color:#2563eb">← ดูสรุปทุกร้าน</a>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <form method="get" class="filter-card">
      <?php if ($selectedPlaceId > 0): ?>
        <input type="hidden" name="place_id" value="<?= $selectedPlaceId ?>">
      <?php endif; ?>
      <div>
        <label>จากวันที่</label>
        <input type="date" name="from" value="<?= e($dateFrom) ?>" <?= $allTime ? 'disabled' : '' ?>>
      </div>
      <div>
        <label>ถึงวันที่</label>
        <input type="date" name="to" value="<?= e($dateTo) ?>" <?= $allTime ? 'disabled' : '' ?>>
      </div>
      <button type="submit" class="adm-btn primary">กรอง</button>
      <?php if ($allTime): ?>
        <a href="?<?= e(http_build_query($selectedPlaceId > 0 ? ["place_id" => $selectedPlaceId] : [])) ?>" class="adm-btn" style="background:#eff6ff;color:#2563eb">แสดงแค่ช่วงวันที่</a>
      <?php else: ?>
        <a href="?<?= e(http_build_query(($selectedPlaceId > 0 ? ["place_id" => $selectedPlaceId] : []) + ["all" => "1"])) ?>" class="adm-btn" style="background:#eff6ff;color:#2563eb">ดูข้อมูลทั้งหมด</a>
      <?php endif; ?>
    </form>

    <div class="stat-grid">
      <div class="stat-tile">
        <div class="icon-circle" style="background:#dbeafe">
          <svg viewBox="0 0 24 24" style="fill:#2563eb"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
        </div>
        <div class="num"><?= number_format($overallTotal) ?></div>
        <div class="label">ลูกค้าทั้งหมด (คน)</div>
      </div>
      <div class="stat-tile">
        <div class="icon-circle" style="background:#dcfce7">
          <svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><path d="M4 4v6h6M20 20v-6h-6"/><path d="M4.5 15a8 8 0 0 0 13.9 3.4L20 20M20 9A8 8 0 0 0 6.1 5.6L4 6"/></svg>
        </div>
        <div class="num"><?= number_format($overallRepeat) ?></div>
        <div class="label">ลูกค้ามาซ้ำ (คน)</div>
      </div>
      <div class="stat-tile">
        <div class="icon-circle" style="background:#ede9fe">
          <svg viewBox="0 0 24 24" style="fill:#7c3aed"><path d="M4 4h16v4H4V4zm0 6h16v10H4V10zm3 2v2h6v-2H7z"/></svg>
        </div>
        <div class="num"><?= $overallRate ?>%</div>
        <div class="label">อัตรากลับมาซ้ำ</div>
      </div>
      <div class="stat-tile">
        <div class="icon-circle" style="background:#fef9c3">
          <svg viewBox="0 0 24 24" style="fill:#f59e0b"><path d="M3 3v18h18M7 15l4-4 3 3 5-6"/></svg>
        </div>
        <div class="num"><?= $overallAvg ?></div>
        <div class="label">ความถี่เฉลี่ยต่อคน (ครั้ง/คน)</div>
      </div>
    </div>

    <?php if ($selectedPlaceId === 0): ?>
      <div class="sec-head"><h2>สรุปต่อร้านค้า</h2></div>
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="place-search" placeholder="ค้นหาชื่อร้านค้า" oninput="filterRows('place-search','place-row','places-empty')">
      </div>
      <div class="pg-card">
        <table class="rpt-table">
          <thead>
            <tr><th>ร้านค้า</th><th>ลูกค้าทั้งหมด</th><th>ลูกค้ามาซ้ำ</th><th>% กลับมาซ้ำ</th><th>ความถี่เฉลี่ย</th></tr>
          </thead>
          <tbody>
            <?php if (empty($placeSummaryRows)): ?>
              <tr><td colspan="5" class="empty-note">ยังไม่มีข้อมูลลูกค้า</td></tr>
            <?php else: foreach ($placeSummaryRows as $row): ?>
              <tr class="place-row" data-search="<?= e(mb_strtolower($row['place_name'], 'UTF-8')) ?>">
                <td><a href="admin_report_retention.php?place_id=<?= $row['place_id'] ?>"><?= e($row['place_name']) ?></a></td>
                <td><?= number_format($row['total']) ?></td>
                <td><?= number_format($row['repeat']) ?></td>
                <td><?= $row['rate'] ?>%</td>
                <td><?= $row['avg'] ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div id="places-empty" class="empty-note" style="display:none">ไม่พบร้านค้าที่ค้นหา</div>
      </div>
    <?php else: ?>
      <div class="sec-head"><h2>รายละเอียดลูกค้าต่อร้าน<?= $selectedPlaceName ? ' — ' . e($selectedPlaceName) : '' ?></h2></div>
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" id="cust-search" placeholder="ค้นหาชื่อลูกค้า" oninput="filterRows('cust-search','cust-row','cust-empty')">
      </div>
      <div class="pg-card">
        <table class="rpt-table">
          <thead>
            <tr><th>ชื่อลูกค้า</th><th>จำนวนครั้งที่มา</th><th>ครั้งล่าสุด</th></tr>
          </thead>
          <tbody>
            <?php if (empty($customerDetail)): ?>
              <tr><td colspan="3" class="empty-note">ยังไม่มีลูกค้าของร้านนี้</td></tr>
            <?php else: foreach ($customerDetail as $c): ?>
              <tr class="cust-row" data-search="<?= e(mb_strtolower($c['name'], 'UTF-8')) ?>">
                <td><?= e($c['name']) ?></td>
                <td><?= number_format($c['count']) ?></td>
                <td><?= date('j M Y', strtotime($c['last'])) ?></td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
        <div id="cust-empty" class="empty-note" style="display:none">ไม่พบลูกค้าที่ค้นหา</div>
      </div>
    <?php endif; ?>

  </main>
  <script>
  function filterRows(inputId, rowClass, emptyId) {
    const q = document.getElementById(inputId).value.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('.' + rowClass).forEach(row => {
      const match = !q || row.dataset.search.includes(q);
      row.style.display = match ? '' : 'none';
      if (match) visible++;
    });
    const emptyEl = document.getElementById(emptyId);
    if (emptyEl) emptyEl.style.display = (visible === 0 && q) ? 'block' : 'none';
  }
  </script>
</body>
</html>
