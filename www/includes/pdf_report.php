<?php
require_once __DIR__ . "/../lib/tfpdf/tfpdf.php";
// tfpdf.php calls "new TTFontFile()" for TTF parsing but deliberately leaves the
// require for it commented out (line ~494/~1892), expecting the consuming app
// to load it — this class is what was missing, causing a fatal error on AddFont().
require_once __DIR__ . "/../lib/tfpdf/font/unifont/ttfonts.php";

if (!defined("_SYSTEM_TTFONTS")) {
    define("_SYSTEM_TTFONTS", __DIR__ . "/../assets/fonts/");
}

class ReportPDF extends tFPDF {
    public $reportTitle = "";

    function Header() {
        $y = 8;
        $logo = __DIR__ . "/../assets/images/logo.png";
        if (file_exists($logo)) {
            $this->Image($logo, 10, $y, 22);
        }

        $this->SetXY(36, $y + 1);
        $this->SetFont("Sarabun", "B", 13);
        $this->Cell(160, 6, "ล่าพิกัด.com", 0, 0, "L");

        $this->SetXY(36, $y + 8);
        $this->SetFont("Sarabun", "B", 11);
        $this->Cell(160, 6, $this->reportTitle, 0, 0, "L");

        $this->SetXY(36, $y + 15);
        $this->SetFont("Sarabun", "", 8.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(160, 5, "ออกรายงานเมื่อ " . date("d/m/Y H:i") . " น.", 0, 0, "L");
        $this->SetTextColor(0, 0, 0);

        $this->SetY($y + 22);
        $this->SetDrawColor(210, 210, 210);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY($y + 26);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont("Sarabun", "", 8);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 10, "หน้า " . $this->PageNo() . "/{nb}", 0, 0, "C");
    }
}

function pdf_init(string $title): ReportPDF {
    $pdf = new ReportPDF();
    $pdf->reportTitle = $title;
    $pdf->AliasNbPages();
    $pdf->AddFont("Sarabun", "", "Sarabun-Regular.ttf", true);
    $pdf->AddFont("Sarabun", "B", "Sarabun-Bold.ttf", true);
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(true, 20);
    $pdf->AddPage();
    $pdf->SetFont("Sarabun", "", 10);
    return $pdf;
}

function pdf_section_title(ReportPDF $pdf, string $text) {
    $pdf->SetFont("Sarabun", "B", 11);
    $pdf->SetTextColor(15, 23, 42);
    $pdf->Cell(0, 8, $text, 0, 1, "L");
    $pdf->SetTextColor(0, 0, 0);
}

// Renders a row of "stat tile" style value/label pairs across the full page width.
function pdf_summary_boxes(ReportPDF $pdf, array $items) {
    $n = count($items);
    if ($n === 0) return;
    $w = 190 / $n;

    $pdf->SetFont("Sarabun", "B", 13);
    $pdf->SetTextColor(37, 99, 235);
    foreach ($items as $it) {
        $pdf->Cell($w, 10, (string)$it["value"], 1, 0, "C");
    }
    $pdf->Ln();

    $pdf->SetFont("Sarabun", "", 8.5);
    $pdf->SetTextColor(100, 116, 139);
    foreach ($items as $it) {
        $pdf->Cell($w, 6, (string)$it["label"], 0, 0, "C");
    }
    $pdf->Ln(10);
    $pdf->SetTextColor(0, 0, 0);
}

// $headers/$rows are plain arrays; $colWidths is in mm and must sum to <= 190.
// $aligns (optional) maps column index to an FPDF align code ("L"/"C"/"R"), default "C".
function pdf_table(ReportPDF $pdf, array $headers, array $rows, array $colWidths, array $aligns = []) {
    $rowH = 7;

    $pdf->SetFont("Sarabun", "B", 9);
    $pdf->SetFillColor(37, 99, 235);
    $pdf->SetTextColor(255, 255, 255);
    foreach ($headers as $i => $h) {
        $pdf->Cell($colWidths[$i], $rowH, (string)$h, 1, 0, $aligns[$i] ?? "C", true);
    }
    $pdf->Ln();

    $pdf->SetFont("Sarabun", "", 9);
    $pdf->SetTextColor(30, 30, 30);

    if (empty($rows)) {
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Cell(array_sum($colWidths), $rowH, "ไม่มีข้อมูล", 1, 0, "C", false);
        $pdf->Ln();
        $pdf->SetTextColor(0, 0, 0);
        return;
    }

    $fill = false;
    foreach ($rows as $row) {
        $shade = $fill ? 246 : 255;
        $pdf->SetFillColor($shade, $shade, $fill ? 250 : 255);
        foreach (array_values($row) as $i => $cell) {
            $pdf->Cell($colWidths[$i], $rowH, (string)$cell, 1, 0, $aligns[$i] ?? "C", true);
        }
        $pdf->Ln();
        $fill = !$fill;
    }
    $pdf->SetTextColor(0, 0, 0);
}

function pdf_send(ReportPDF $pdf, string $filename) {
    // Discard any stray whitespace/output buffered before this point (see the
    // ob_start() at the top of the PDF entry scripts) so the response starts
    // cleanly with the PDF's own headers and binary content.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $pdf->Output("D", $filename);
    exit;
}
?>
