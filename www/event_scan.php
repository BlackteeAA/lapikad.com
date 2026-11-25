<?php
// "สแกนเช็คชื่อ" — opened from the event page (event_room.php?id=…). Only
// students who entered the event can use it. The scan is sent with room=<event id>,
// which event_checkin.php requires, so the event's check-in QR can't be used
// from the camera button in the bottom nav.
require_once "_events.php";

$userId  = intval($_SESSION["user_id"]);
$eventId = intval($_GET["id"] ?? 0);
$event   = $eventId > 0 ? getEvent($conn, $eventId) : null;

if (!$event || !hasJoinedEvent($conn, $eventId, $userId)) redirect("dashboard.php");

$scannerTitle        = "สแกนเช็คชื่อ | ล่าพิกัด.com";
$scannerBackHref     = "event_room.php?id=" . $eventId;
$scannerHint         = "สแกน QR บนจอ · " . $event["title"];
$scannerFormAction   = "event_checkin.php";
$scannerFormMethod   = "get";
$scannerFieldName    = "raw";
$scannerHiddenFields = ["room" => $eventId];

require "includes/qr_scanner.php";
