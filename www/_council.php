<?php
// Gate for student-council pages ("สภานักเรียน"): manages school-wide rewards
// (rewards.place_id IS NULL). Reuses the existing is_organizer flag, which is
// already labeled "สภา" in admin_users.php for the school-events feature.
require_once __DIR__ . "/_auth.php";

$councilStmt = $conn->prepare("SELECT is_organizer FROM users WHERE id=?");
$councilStmt->bind_param("i", $_SESSION["user_id"]);
$councilStmt->execute();
$councilRow = $councilStmt->get_result()->fetch_assoc();
$isCouncil = ($_SESSION["role"] ?? "") === "admin" || ($councilRow && !empty($councilRow["is_organizer"]));

if (!$isCouncil) {
    redirect("dashboard.php");
}
?>
