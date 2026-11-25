<?php
// Shop scans a customer's personal QR (my_points.php) to redeem a reward.
require_once "_shop.php";

$scannerTitle        = "สแกนหักคะแนน | ล่าพิกัด.com";
$scannerBackHref     = "shop.php";
$scannerHint         = "สแกน QR ของลูกค้า (เมนู แต้มร้านค้าของฉัน)";
$scannerFormAction   = "shop_deduct.php";
$scannerFormMethod   = "post";
$scannerFieldName    = "scanned_code";
$scannerHiddenFields = ["csrf_token" => csrf_token()];

require "includes/qr_scanner.php";
