<?php
require_once "_shop.php";

$scannerTitle        = "สแกนแลกรางวัล | ล่าพิกัด.com";
$scannerBackHref      = "shop.php";
$scannerHint          = "ให้ลูกค้าโชว์ QR Code จากหน้าจอแลกของรางวัล";
$scannerFormAction    = "shop_redeem_confirm.php";
$scannerFormMethod    = "get";
$scannerFieldName     = "code";
$scannerHiddenFields  = [];

require "includes/qr_scanner.php";
