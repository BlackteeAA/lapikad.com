<?php
// Print-friendly attendee list — user saves as PDF via the browser's print dialog.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!eventRole($conn, $event, $userId)) redirect("event_manage.php");

$stmt = $conn->prepare("
    SELECT u.name, u.student_name, u.class_room, u.student_no, a.method, a.checked_in_at, s.name AS scanner_name
    FROM event_attendances a
    JOIN users u ON u.id = a.user_id
    LEFT JOIN users s ON s.id = a.checked_in_by
    WHERE a.event_id=?
    ORDER BY a.checked_in_at ASC
");
$stmt->bind_param("i", $eventId);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$methodLabel = ["staff_scan" => "สภาสแกน", "self_scan" => "สแกนเอง", "manual" => "เพิ่มเอง"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>รายชื่อเช็คชื่อ · <?= e($event["title"]) ?></title>
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
    .meta { font-size: 13px; color: #64748b; margin-bottom: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    thead { display: table-header-group; }
    th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
    th { background: #f1f5f9; }
    tr { break-inside: avoid; }
    tfoot td { font-weight: 600; border: none; padding-top: 10px; }

    @media print {
      body { padding: 0; }
      .toolbar { display: none; }
      @page { size: A4; margin: 14mm; }
    }
  </style>
</head>
<body>
  <div class="toolbar">
    <a class="back" href="event_admin.php?id=<?= $eventId ?>">กลับ</a>
    <button class="print" onclick="window.print()">พิมพ์ / บันทึกเป็น PDF</button>
  </div>

  <h1><?= e($event["title"]) ?></h1>
  <div class="meta">
    <?= $event["starts_at"] ? e(eventDateText($event["starts_at"])) : "" ?>
    · รายชื่อผู้เช็คชื่อทั้งหมด <?= count($rows) ?> คน
    · พิมพ์เมื่อ <?= date("d/m/Y H:i") ?>
  </div>

  <table>
    <thead>
      <tr>
        <th>ลำดับ</th>
        <th>ชื่อ</th>
        <th>ชั้น</th>
        <th>เลขที่</th>
        <th>เวลาเช็คชื่อ</th>
        <th>วิธี</th>
        <th>ผู้สแกน</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rows as $i => $r): ?>
        <?php $displayName = $r["student_name"] !== "" && $r["student_name"] !== null ? $r["student_name"] : $r["name"]; ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td><?= e($displayName) ?></td>
          <td><?= e($r["class_room"] ?? "") ?></td>
          <td><?= e($r["student_no"] ?? "") ?></td>
          <td><?= e($r["checked_in_at"]) ?></td>
          <td><?= e($methodLabel[$r["method"]] ?? $r["method"]) ?></td>
          <td><?= e($r["scanner_name"] ?? "") ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="7" style="text-align:center;color:#94a3b8">ยังไม่มีผู้เช็คชื่อ</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
