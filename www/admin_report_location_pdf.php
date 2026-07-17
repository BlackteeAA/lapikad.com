<?php
// Buffer everything from here on: several shared includes below end with a
// trailing newline after their closing "?>", which would otherwise be flushed
// to the browser before tFPDF can send its Content-Type/Content-Disposition
// headers and corrupt the PDF (shows as a blank page). Discarded in pdf_send().
ob_start();

require_once "_admin.php";
require_once "includes/pdf_report.php";

$lat = (isset($_GET["lat"]) && $_GET["lat"] !== "") ? floatval($_GET["lat"]) : null;
$lng = (isset($_GET["lng"]) && $_GET["lng"] !== "") ? floatval($_GET["lng"]) : null;
$radius = intval($_GET["radius"] ?? 300);
if (!in_array($radius, [300, 500, 1000, 2000], true)) $radius = 300;

if ($lat === null || $lng === null) {
    redirect("admin_report_location.php");
}

$nearby = [];
$categoryCounts = [];
$places = $conn->query("SELECT id, name, category, lat, lng FROM places WHERE lat IS NOT NULL AND lng IS NOT NULL")->fetch_all(MYSQLI_ASSOC);
foreach ($places as $p) {
    $dist = haversineMeters($lat, $lng, (float)$p["lat"], (float)$p["lng"]);
    if ($dist <= $radius) {
        $cat = ($p["category"] !== null && $p["category"] !== "") ? $p["category"] : "อื่นๆ";
        $nearby[] = ["name" => $p["name"], "category" => $cat, "distance" => $dist];
        $categoryCounts[$cat] = ($categoryCounts[$cat] ?? 0) + 1;
    }
}
usort($nearby, fn($a, $b) => $a["distance"] <=> $b["distance"]);
arsort($categoryCounts);

function fmtDistancePdf(float $m): string {
    return $m >= 1000 ? number_format($m / 1000, 2) . " กม." : number_format($m, 0) . " ม.";
}

$radiusLabel = $radius >= 1000 ? number_format($radius / 1000, 0) . " กม." : $radius . " ม.";

$pdf = pdf_init("วิเคราะห์ทำเลก่อนเปิดร้าน (รัศมี " . $radiusLabel . ")");

pdf_summary_boxes($pdf, [
    ["value" => number_format($lat, 6), "label" => "ละติจูด (Latitude)"],
    ["value" => number_format($lng, 6), "label" => "ลองจิจูด (Longitude)"],
    ["value" => $radiusLabel, "label" => "รัศมีที่เลือก"],
    ["value" => (string)count($nearby), "label" => "สถานที่ในรัศมี (แห่ง)"],
]);

pdf_section_title($pdf, "สรุปตามประเภท");
$catRows = [];
foreach ($categoryCounts as $cat => $cnt) {
    $catRows[] = [$cat, $cnt . " แห่ง"];
}
pdf_table($pdf, ["ประเภท", "จำนวน"], $catRows, [140, 50], [0 => "L"]);

$pdf->Ln(4);
pdf_section_title($pdf, "สถานที่ใกล้เคียง");
$nearRows = [];
foreach ($nearby as $n) {
    $nearRows[] = [$n["name"], $n["category"], fmtDistancePdf($n["distance"])];
}
pdf_table($pdf, ["ชื่อร้าน", "ประเภท", "ระยะทาง"], $nearRows, [90, 60, 40], [0 => "L", 1 => "L"]);

pdf_send($pdf, "location-report-" . date("Ymd-His") . ".pdf");
?>
