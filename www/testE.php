<?php
ini_set("display_errors", "1");
ini_set("display_startup_errors", "1");
error_reporting(E_ALL);

ob_start();
require_once "includes/pdf_report.php";
$pdf = pdf_init("Test Report");
$pdf->Cell(0, 10, "Hello Sarabun ทดสอบ", 0, 1);
pdf_send($pdf, "test-e.pdf");
