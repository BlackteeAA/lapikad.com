<?php
require_once "_events.php";

$scannerTitle        = "สแกนเช็คชื่อกิจกรรม | ล่าพิกัด.com";
$scannerBackHref     = "events.php";
$scannerHint         = "สแกน QR กิจกรรมที่แสดงบนจอ";
$scannerFormAction   = "event_checkin.php";
$scannerFormMethod   = "get";
$scannerFieldName    = "raw";
$scannerHiddenFields = [];

require "includes/qr_scanner.php";
