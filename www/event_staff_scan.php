<?php
// Council member scans a student's personal QR (event_my_qr.php).
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = getEvent($conn, $eventId);

if (!eventRole($conn, $event, $userId)) redirect("event_manage.php");

$scannerTitle        = "สแกนเช็คชื่อ: " . $event["title"];
$scannerBackHref     = "event_admin.php?id=" . $eventId;
$scannerHint         = $event["is_open"]
    ? "สแกน QR ประจำตัวนักเรียน · " . $event["title"]
    : "⚠ กิจกรรมยังปิดเช็คชื่ออยู่";
$scannerFormAction   = "event_staff_checkin.php";
$scannerFormMethod   = "post";
$scannerFieldName    = "scanned_code";
$scannerHiddenFields = ["csrf_token" => csrf_token(), "event_id" => $eventId];

require "includes/qr_scanner.php";
