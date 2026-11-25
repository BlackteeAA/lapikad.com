<?php
// Attendance report ("รายงานการเข้าร่วมกิจกรรม"): how often each student of a
// school came to its events. "มา" = checked in (event_attendances); entering
// through a link without being checked in counts as not coming.
// For council/teachers of the school and site admins. ?export=1 downloads CSV,
// ?pdf=1 opens a print-friendly page to save as PDF.
require_once "_events.php";

$userId   = intval($_SESSION["user_id"]);
$mySchool = getUserSchool($conn, $userId);

$schoolId = isSiteAdmin() ? intval($_GET["school"] ?? ($mySchool["school_id"] ?? 0)) : intval($mySchool["school_id"] ?? 0);
if (isSiteAdmin() && $schoolId <= 0) {
    $all = getAllSchools($conn);
    $schoolId = $all ? intval($all[0]["id"]) : 0;
}
if ($schoolId <= 0 || !canManageSchoolContent($conn, $userId, $schoolId)) redirect("events.php");
$school = getSchool($conn, $schoolId);
if (!$school) redirect("events.php");

$events   = getSchoolAttendanceEvents($conn, $schoolId);
$eventIds = array_map(fn($e) => intval($e["id"]), $events);
$map      = getAttendanceMap($conn, $eventIds);
$students = getSchoolStudents($conn, $schoolId);
$total    = count($events);

foreach ($students as &$st) {
    $mine = $map[intval($st["id"])] ?? [];
    $st["attended"] = count($mine);
    $st["missed"]   = array_values(array_filter($events, fn($e) => !isset($mine[intval($e["id"])])));
    $st["pct"]      = attendancePercent($st["attended"], $total);
}
unset($st);

// Per event: how many of this school's students came.
$studentIds = array_flip(array_map(fn($s) => intval($s["id"]), $students));
foreach ($events as &$ev) {
    $n = 0;
    foreach ($map as $uid => $evs) if (isset($evs[intval($ev["id"])]) && isset($studentIds[$uid])) $n++;
    $ev["came"] = $n;
}
unset($ev);

// Class filter options, natural order.
$classes = array_values(array_unique(array_filter(array_map(fn($s) => splitClassRoom($s["class_room"] ?? "")[1] ?? splitClassRoom($s["class_room"] ?? "")[0], $students))));
usort($classes, "strnatcmp");
$classFilter = trim($_GET["class"] ?? "");
$sort        = ($_GET["sort"] ?? "low") === "class" ? "class" : "low";

$shown = array_values(array_filter($students, function ($s) use ($classFilter) {
    if ($classFilter === "") return true;
    [$g, $r] = splitClassRoom($s["class_room"] ?? "");
    return ($r ?? $g) === $classFilter;
}));
$byClass = function ($a, $b) {
    [$ga, $ra] = splitClassRoom($a["class_room"] ?? "");
    [$gb, $rb] = splitClassRoom($b["class_room"] ?? "");
    return strnatcmp($ra ?? $ga ?? "~", $rb ?? $gb ?? "~")
        ?: (intval($a["student_no"] ?? 0) ?: PHP_INT_MAX) <=> (intval($b["student_no"] ?? 0) ?: PHP_INT_MAX)
        ?: strcmp($a["display_name"], $b["display_name"]);
};
usort($shown, $sort === "class" ? $byClass : fn($a, $b) => ($a["attended"] <=> $b["attended"]) ?: $byClass($a, $b));

// ── CSV: one row per student, one column per event (✓ / ✗) ──
if (!empty($_GET["export"])) {
    $csvSafe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
    $csvText = fn($v) => '="' . str_replace('"', '""', (string)$v) . '"';
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"attendance_school_{$schoolId}_" . date("Ymd") . ".csv\"");
    $out = fopen("php://output", "w");
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [$csvSafe("รายงานการเข้าร่วมกิจกรรม · " . $school["name"])]);
    $head = ["ลำดับ", "ชื่อ", "ชั้น", "เลขที่", "มา", "ไม่มา", "ทั้งหมด", "% การมา"];
    foreach ($events as $ev) {
        $head[] = $csvSafe($ev["title"] . ($ev["starts_at"] ? " (" . date("d/m/Y", strtotime($ev["starts_at"])) . ")" : ""));
    }
    fputcsv($out, $head);
    foreach ($shown as $i => $st) {
        $mine = $map[intval($st["id"])] ?? [];
        $row = [$i + 1, $csvSafe($st["display_name"]), $csvText($st["class_room"] ?? ""), $csvText($st["student_no"] ?? ""),
                $st["attended"], $total - $st["attended"], $total, $st["pct"] === null ? "" : $st["pct"] . "%"];
        foreach ($events as $ev) $row[] = isset($mine[intval($ev["id"])]) ? "✓" : "✗";
        fputcsv($out, $row);
    }
    fclose($out);
    exit;
}

$rateClass = fn($p) => $p === null ? "" : ($p >= 80 ? "rate-good" : ($p >= 60 ? "rate-mid" : "rate-low"));
$avg = $students && $total ? (int)round(array_sum(array_column($students, "attended")) * 100 / (count($students) * $total)) : null;
$lowCount = count(array_filter($students, fn($s) => $s["pct"] !== null && $s["pct"] < 80));
$qs = fn($extra) => "event_attendance.php?" . http_build_query(array_merge(
    isSiteAdmin() ? ["school" => $schoolId] : [], $classFilter !== "" ? ["class" => $classFilter] : [], ["sort" => $sort], $extra));

// ── Print / PDF view (saved via the browser's print dialog) ──
if (!empty($_GET["pdf"])):
    $shownAtt = array_sum(array_column($shown, "attended"));
    $shownAvg = $shown && $total ? (int)round($shownAtt * 100 / (count($shown) * $total)) : null;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รายงานการเข้าร่วมกิจกรรม · <?= e($school["name"]) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { font-family: 'Kanit', sans-serif; color: #0f172a; margin: 0; padding: 24px; }
    .toolbar { display: flex; gap: 10px; justify-content: flex-end; margin-bottom: 18px; }
    .toolbar button, .toolbar a {
      font-family: inherit; font-size: 14px; font-weight: 600; cursor: pointer;
      border: none; border-radius: 10px; padding: 10px 18px; text-decoration: none;
    }
    .toolbar .print { background: #4f46e5; color: #fff; }
    .toolbar .back { background: #f1f5f9; color: #334155; }
    h1 { font-size: 20px; margin: 0 0 4px; }
    h2 { font-size: 16px; margin: 22px 0 8px; }
    .meta { font-size: 13px; color: #64748b; margin-bottom: 14px; }
    .summary { display: flex; gap: 10px; margin-bottom: 16px; }
    .summary div { flex: 1; border: 1px solid #cbd5e1; border-radius: 8px; padding: 8px 10px; font-size: 12px; color: #64748b; }
    .summary b { display: block; font-size: 18px; color: #0f172a; }
    table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    thead { display: table-header-group; }
    th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; vertical-align: top; }
    th { background: #f1f5f9; }
    td.num, th.num { text-align: center; white-space: nowrap; }
    tr { break-inside: avoid; }
    .low { color: #dc2626; font-weight: 600; }
    .missed { font-size: 11.5px; color: #475569; }
    @media print {
      body { padding: 0; }
      .toolbar { display: none; }
      @page { size: A4; margin: 12mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a class="back" href="<?= e($qs([])) ?>">กลับ</a>
    <button class="print" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
  </div>

  <h1>รายงานการเข้าร่วมกิจกรรม · <?= e($school["name"]) ?></h1>
  <div class="meta">
    <?= $classFilter !== "" ? "ชั้น " . e($classFilter) : "ทุกชั้น" ?>
    · นับว่า "มา" เมื่อเช็คชื่อแล้ว · นับเฉพาะกิจกรรมที่เริ่มไปแล้ว
    · พิมพ์เมื่อ <?= e(thaiTimestampText(time())) ?>
  </div>

  <div class="summary">
    <div><b><?= $total ?></b>กิจกรรมที่นับ</div>
    <div><b><?= count($shown) ?></b>นักเรียน</div>
    <div><b><?= $shownAvg === null ? "–" : $shownAvg . "%" ?></b>อัตราการมาเฉลี่ย</div>
    <div><b><?= count(array_filter($shown, fn($s) => $s["pct"] !== null && $s["pct"] < 80)) ?></b>มาน้อยกว่า 80%</div>
  </div>

  <h2>รายนักเรียน</h2>
  <table>
    <thead>
      <tr>
        <th class="num">ลำดับ</th>
        <th>ชื่อ</th>
        <th class="num">ชั้น</th>
        <th class="num">เลขที่</th>
        <th class="num">มา</th>
        <th class="num">ไม่มา</th>
        <th class="num">%</th>
        <th>กิจกรรมที่ไม่มา</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($shown as $i => $st): ?>
        <tr>
          <td class="num"><?= $i + 1 ?></td>
          <td><?= e($st["display_name"]) ?><?= $st["school_role"] === "council" ? " (สภา)" : "" ?></td>
          <td class="num"><?= e($st["class_room"] ?? "") ?></td>
          <td class="num"><?= e($st["student_no"] ?? "") ?></td>
          <td class="num"><?= $st["attended"] ?></td>
          <td class="num"><?= $total - $st["attended"] ?></td>
          <td class="num <?= $st["pct"] !== null && $st["pct"] < 80 ? "low" : "" ?>"><?= $st["pct"] === null ? "–" : $st["pct"] . "%" ?></td>
          <td class="missed"><?= $st["missed"] ? e(implode(", ", array_map(fn($m) => $m["title"], $st["missed"]))) : "มาครบ" ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$shown): ?>
        <tr><td colspan="8" style="text-align:center;color:#94a3b8">ไม่มีนักเรียน</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>รายกิจกรรม</h2>
  <table>
    <thead>
      <tr>
        <th class="num">ลำดับ</th>
        <th>กิจกรรม</th>
        <th>วันเวลา</th>
        <th class="num">มา</th>
        <th class="num">ไม่มา</th>
        <th class="num">%</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($events as $i => $ev):
        // Only count the students shown (respects the class filter).
        $came = count(array_filter($shown, fn($s) => isset($map[intval($s["id"])][intval($ev["id"])])));
        $p = attendancePercent($came, count($shown));
      ?>
        <tr>
          <td class="num"><?= $i + 1 ?></td>
          <td><?= e($ev["title"]) ?><?= $ev["school_id"] === null ? " (ทุกโรงเรียน)" : "" ?></td>
          <td><?= $ev["starts_at"] ? e(eventDateText($ev["starts_at"])) : "ไม่ระบุ" ?></td>
          <td class="num"><?= $came ?></td>
          <td class="num"><?= count($shown) - $came ?></td>
          <td class="num <?= $p !== null && $p < 80 ? "low" : "" ?>"><?= $p === null ? "–" : $p . "%" ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?>
        <tr><td colspan="6" style="text-align:center;color:#94a3b8">ยังไม่มีกิจกรรมที่เริ่มไปแล้ว</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
<?php
    exit;
endif;
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>รายงานการเข้าร่วมกิจกรรม | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <link rel="stylesheet" href="css/events.css">
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="events.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="ev-hero">
      <div class="ev-school">
        <?= schoolLogoHtml($school, 34) ?>
        <span><?= e($school["name"]) ?></span>
      </div>
      <h1>รายงานการเข้าร่วมกิจกรรม</h1>
      <p>นับว่า "มา" เมื่อเช็คชื่อแล้ว · นับเฉพาะกิจกรรมที่เริ่มไปแล้ว</p>
    </div>

    <?php if (isSiteAdmin()): ?>
    <form method="get" class="att-filter">
      <select name="school" onchange="this.form.submit()">
        <?php foreach (getAllSchools($conn) as $sc): ?>
          <option value="<?= intval($sc["id"]) ?>" <?= intval($sc["id"]) === $schoolId ? "selected" : "" ?>><?= e($sc["name"]) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>

    <div class="stat-grid">
      <div class="stat"><b><?= $total ?></b><span>กิจกรรมที่นับ</span></div>
      <div class="stat"><b class="<?= $rateClass($avg) ?>"><?= $avg === null ? "–" : $avg . "%" ?></b><span>อัตราการมาเฉลี่ย</span></div>
      <div class="stat"><b class="rate-low"><?= $total ? $lowCount : "–" ?></b><span>มาน้อยกว่า 80%</span></div>
    </div>

    <?php if (!$total): ?>
      <div class="pg-card"><div class="empty">ยังไม่มีกิจกรรมที่เริ่มไปแล้ว รายงานจะขึ้นเมื่อมีกิจกรรมและมีการเช็คชื่อ</div></div>
    <?php else: ?>

    <form method="get" class="att-filter">
      <?php if (isSiteAdmin()): ?><input type="hidden" name="school" value="<?= $schoolId ?>"><?php endif; ?>
      <select name="class" onchange="this.form.submit()">
        <option value="">ทุกชั้น (<?= count($students) ?> คน)</option>
        <?php foreach ($classes as $c): ?>
          <option value="<?= e($c) ?>" <?= $c === $classFilter ? "selected" : "" ?>><?= e($c) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="sort" onchange="this.form.submit()">
        <option value="low" <?= $sort === "low" ? "selected" : "" ?>>เรียง: มาน้อยสุดก่อน</option>
        <option value="class" <?= $sort === "class" ? "selected" : "" ?>>เรียง: ตามชั้น / เลขที่</option>
      </select>
    </form>

    <div class="btn-grid" style="margin-bottom:14px">
      <a class="adm-btn ghost" href="<?= e($qs(["export" => 1])) ?>">ดาวน์โหลด (Excel)</a>
      <a class="adm-btn ghost" href="<?= e($qs(["pdf" => 1])) ?>" target="_blank">พิมพ์ / PDF</a>
    </div>

    <div class="ev-section">
      <span>รายนักเรียน (<?= count($shown) ?> คน)</span>
    </div>
    <div class="pg-card">
      <div class="search-box">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="text" placeholder="ค้นหาชื่อ" oninput="filterStudents(this.value)">
      </div>
      <?php if (!$shown): ?>
        <div class="empty">ไม่มีนักเรียนในชั้นนี้</div>
      <?php endif; ?>
      <?php foreach ($shown as $st): $rc = $rateClass($st["pct"]); ?>
        <details class="absent-room st-item" data-search="<?= e(mb_strtolower($st["display_name"], "UTF-8")) ?>">
          <summary style="gap:12px;font-weight:400">
            <div class="avatar">
              <?php if (!empty($st["avatar_url"])): ?>
                <img src="<?= e($st["avatar_url"]) ?>" alt="">
              <?php else: ?>
                <?= e(mb_substr($st["display_name"], 0, 1, "UTF-8")) ?>
              <?php endif; ?>
            </div>
            <div class="row-info">
              <strong><?= e($st["display_name"]) ?><?= $st["school_role"] === "council" ? ' <small style="color:#94a3b8;font-weight:400">(สภา)</small>' : "" ?></strong>
              <span>
                <?= !empty($st["class_room"]) ? "ชั้น " . e($st["class_room"]) : "ไม่ระบุชั้น" ?><?= !empty($st["student_no"]) ? " · เลขที่ " . e($st["student_no"]) : "" ?>
                · มา <?= $st["attended"] ?>/<?= $total ?> · ไม่มา <?= $total - $st["attended"] ?>
              </span>
              <div class="rate-bar"><i class="<?= $rc ?>" style="width:<?= intval($st["pct"]) ?>%"></i></div>
            </div>
            <div class="rate-pct <?= $rc ?>"><?= $st["pct"] ?>%</div>
          </summary>
          <div class="missed-list">
            <?php if (!$st["missed"]): ?>
              มาครบทุกกิจกรรม ✓
            <?php else: ?>
              <b>ไม่มา <?= count($st["missed"]) ?> กิจกรรม:</b>
              <?php foreach ($st["missed"] as $m): ?>
                <div>· <?= e($m["title"]) ?><?= $m["starts_at"] ? " (" . e(eventDateText($m["starts_at"])) . ")" : "" ?></div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </details>
      <?php endforeach; ?>
    </div>

    <div class="ev-section">รายกิจกรรม</div>
    <div class="pg-card">
      <?php foreach (array_reverse($events) as $ev):
        $p = attendancePercent($ev["came"], count($students));
        $canOpen = eventRole($conn, getEvent($conn, intval($ev["id"])), $userId);
      ?>
        <<?= $canOpen ? 'a href="event_admin.php?id=' . intval($ev["id"]) . '"' : "div" ?> class="row-item">
          <div class="row-info">
            <strong><?= e($ev["title"]) ?><?= $ev["school_id"] === null ? ' <small style="color:#94a3b8;font-weight:400">(ทุกโรงเรียน)</small>' : "" ?></strong>
            <span><?= $ev["starts_at"] ? e(eventDateText($ev["starts_at"])) : "ไม่ระบุวันเวลา" ?> · มา <?= $ev["came"] ?>/<?= count($students) ?> คน · ไม่มา <?= count($students) - $ev["came"] ?> คน</span>
            <div class="rate-bar"><i class="<?= $rateClass($p) ?>" style="width:<?= intval($p) ?>%"></i></div>
          </div>
          <div class="rate-pct <?= $rateClass($p) ?>"><?= $p === null ? "–" : $p . "%" ?></div>
        </<?= $canOpen ? "a" : "div" ?>>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </main>
  <script>
  function filterStudents(q) {
    q = q.trim().toLowerCase();
    document.querySelectorAll('.st-item').forEach(el => {
      el.style.display = !q || el.dataset.search.includes(q) ? '' : 'none';
    });
  }
  </script>
</body>
</html>
