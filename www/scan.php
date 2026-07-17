<?php
require_once "_auth.php";

$questId = intval($_GET["id"] ?? 0);

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name
    FROM quests q
    JOIN places p ON p.id = q.place_id
    WHERE q.id = ?
");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) redirect("places.php");

$scannerTitle        = "สแกน QR | ล่าพิกัด.com";
$scannerBackHref      = "quest.php?id=" . $questId;
$scannerHint          = "ให้ตำแหน่ง QR Code อยู่ตรงกลางภาพ";
$scannerFormAction    = "complete_quest.php";
$scannerFormMethod    = "post";
$scannerFieldName     = "scanned_code";
$scannerHiddenFields  = ["csrf_token" => csrf_token(), "quest_id" => $questId];

require "includes/qr_scanner.php";
