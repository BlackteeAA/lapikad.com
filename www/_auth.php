<?php
require_once __DIR__ . "/_db.php";

if (!isset($_SESSION["user_id"])) {
    tryRememberLogin($conn);
}

if (!isset($_SESSION["user_id"])) {
    redirect("login.php");
}

$banStmt = $conn->prepare("SELECT role, is_banned FROM users WHERE id=?");
$banStmt->bind_param("i", $_SESSION["user_id"]);
$banStmt->execute();
$banRow = $banStmt->get_result()->fetch_assoc();

if (!$banRow || !empty($banRow["is_banned"])) {
    session_destroy();
    clearRememberCookie();
    redirect("login.php?banned=1");
}

$_SESSION["role"] = $banRow["role"];
?>
