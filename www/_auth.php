<?php
require_once __DIR__ . "/_db.php";

if (!isset($_SESSION["user_id"])) {
    redirect("login.php");
}
?>
