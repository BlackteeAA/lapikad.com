<?php
// Universal scanner opened from the center camera button in the bottom nav.
// The scanned text goes to scan_route.php, which decides what kind of QR it is.
require_once "_auth.php";

$scannerTitle        = "สแกน QR | ล่าพิกัด.com";
$scannerBackHref     = "dashboard.php";
$scannerHint         = "สแกน QR ภารกิจ หรือ QR กิจกรรมจากสภานักเรียน";
$scannerFormAction   = "scan_route.php";
$scannerFormMethod   = "get";
$scannerFieldName    = "raw";
$scannerHiddenFields = [];

require "includes/qr_scanner.php";
