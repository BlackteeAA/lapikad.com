<?php
require_once __DIR__ . "/_auth.php";

if (($_SESSION["role"] ?? "") !== "shop") {
    redirect("dashboard.php");
}

$shopStmt = $conn->prepare("SELECT * FROM places WHERE owner_user_id=?");
$shopStmt->bind_param("i", $_SESSION["user_id"]);
$shopStmt->execute();
$shopPlace = $shopStmt->get_result()->fetch_assoc();

if (!$shopPlace) {
    redirect("dashboard.php");
}
?>
