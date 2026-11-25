<?php
// Routes a QR scanned in scan_any.php to the page that handles it:
//   - shop table QR (shop_checkin.php?s=<token>)    -> shop_checkin.php
//   - event check-in QR (URL with ?e=<id>&t=<token>) -> event_checkin.php
//   - anything else is treated as a quest code      -> qr_claim.php
require_once "_auth.php";

$raw = trim($_GET["raw"] ?? "");
if ($raw === "") redirect("scan_any.php");

$parts = parse_url($raw);
if (!empty($parts["query"])) {
    parse_str($parts["query"], $q);
    // Council-made check-in link (event_link.php?k=<token>)
    if (!empty($q["k"]) && str_ends_with($parts["path"] ?? "", "event_link.php")) {
        redirect("event_link.php?k=" . urlencode($q["k"]));
    }
    // Shop table QR (shop_checkin.php?s=<token>)
    if (!empty($q["s"]) && str_ends_with($parts["path"] ?? "", "shop_checkin.php")) {
        redirect("shop_checkin.php?s=" . urlencode($q["s"]));
    }
    if (!empty($q["e"]) && isset($q["t"])) {
        redirect("event_checkin.php?raw=" . urlencode($raw));
    }
}

redirect("qr_claim.php?code=" . urlencode(extractQrCode($raw)));
