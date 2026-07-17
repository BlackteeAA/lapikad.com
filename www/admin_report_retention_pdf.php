<?php
// Buffer everything from here on: several shared includes below end with a
// trailing newline after their closing "?>", which would otherwise be flushed
// to the browser before tFPDF can send its Content-Type/Content-Disposition
// headers and corrupt the PDF (shows as a blank page). Discarded in pdf_send().
ob_start();

require_once "_auth.php";
require_once "includes/pdf_report.php";

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
if ($selectedPlaceId > 0) {
    $stmt = $conn->prepare($visitSql . " WHERE v.place_id = ?");
    $stmt->bind_param("i", $selectedPlaceId);
    $stmt->execute();
    $visitRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $visitRows = $conn->query($visitSql)->fetch_all(MYSQLI_ASSOC);
}

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

$selectedPlaceName = $selectedPlaceId > 0 ? ($placeNames[$selectedPlaceId] ?? "") : "";
$scopeLabel = $selectedPlaceId > 0 ? $selectedPlaceName : "ทุกร้านค้า";

$pdf = pdf_init("รายงานลูกค้ากลับมาซ้ำ (" . $scopeLabel . ")");

pdf_summary_boxes($pdf, [
    ["value" => number_format($overallTotal), "label" => "ลูกค้าทั้งหมด (คน)"],
    ["value" => number_format($overallRepeat), "label" => "ลูกค้ามาซ้ำ (คน)"],
    ["value" => $overallRate . "%", "label" => "อัตรากลับมาซ้ำ"],
    ["value" => (string)$overallAvg, "label" => "ความถี่เฉลี่ยต่อคน (ครั้ง/คน)"],
]);

if ($selectedPlaceId === 0) {
    pdf_section_title($pdf, "สรุปต่อร้านค้า");
    $rows = [];
    foreach ($placeSummaryRows as $r) {
        $rows[] = [$r["place_name"], number_format($r["total"]), number_format($r["repeat"]), $r["rate"] . "%", $r["avg"]];
    }
    pdf_table(
        $pdf,
        ["ร้านค้า", "ลูกค้าทั้งหมด", "ลูกค้ามาซ้ำ", "% กลับมาซ้ำ", "ความถี่เฉลี่ย"],
        $rows,
        [70, 30, 30, 30, 30],
        [0 => "L"]
    );
} else {
    pdf_section_title($pdf, "รายละเอียดลูกค้าต่อร้าน");
    $rows = [];
    foreach ($customerDetail as $c) {
        $rows[] = [$c["name"], number_format($c["count"]), date("j M Y", strtotime($c["last"]))];
    }
    pdf_table(
        $pdf,
        ["ชื่อลูกค้า", "จำนวนครั้งที่มา", "ครั้งล่าสุด"],
        $rows,
        [90, 50, 50],
        [0 => "L"]
    );
}

pdf_send($pdf, "retention-report-" . date("Ymd-His") . ".pdf");
?>
