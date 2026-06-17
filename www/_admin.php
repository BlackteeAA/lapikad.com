<?php
require_once __DIR__ . "/_auth.php";

if (($_SESSION["role"] ?? "") !== "admin") {
    redirect("dashboard.php");
}
?>
