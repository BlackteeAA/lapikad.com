<?php
// Polled every few seconds by event_admin.php so names appear as students enter
// the event (เข้างาน) and are checked in (เช็คชื่อ).
//   ?id=<event id>&join=<newest join id shown>&att=<newest attendance id shown>
// Returns the changed rows (ready-made HTML, newest first), the counts, every
// student currently in the list (so rows removed by another council member
// disappear too) and how many students entered through each link.
require_once "_events.php";

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

$userId    = intval($_SESSION["user_id"]);
$eventId   = intval($_GET["id"] ?? 0);
$afterJoin = intval($_GET["join"] ?? 0);
$afterAtt  = intval($_GET["att"] ?? 0);
$event     = getEvent($conn, $eventId);
$myRole    = eventRole($conn, $event, $userId);

if (!$myRole) {
    http_response_code(403);
    echo json_encode(["error" => "forbidden"]);
    exit;
}
$isOwner = $myRole === "owner";

// Make sure the CSRF token exists (the rows' remove buttons use it), then release
// the session lock so polling doesn't block the user's other requests.
csrf_token();
session_write_close();

$rows = [];
foreach (getEventParticipants($conn, $eventId, $afterJoin, $afterAtt) as $p) {
    $rows[] = [
        "user" => intval($p["user_id"]),
        "join" => intval($p["join_id"] ?? 0),
        "att"  => intval($p["att_id"] ?? 0),
        "html" => eventParticipantRowHtml($p, $isOwner, $eventId),
    ];
}

$linkUsed = [];
try {
    $stmt = $conn->prepare("
        SELECT l.id, (SELECT COUNT(*) FROM event_joins j WHERE j.link_id = l.id) AS used
        FROM event_links l WHERE l.event_id=?
    ");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $l) $linkUsed[$l["id"]] = intval($l["used"]);
} catch (mysqli_sql_exception $e) {
    // migrations not run yet
}

echo json_encode([
    "rows"     => $rows,
    "counts"   => eventParticipantCounts($conn, $eventId),
    "users"    => getEventParticipantIds($conn, $eventId),
    "linkUsed" => (object)$linkUsed,
], JSON_UNESCAPED_UNICODE);
