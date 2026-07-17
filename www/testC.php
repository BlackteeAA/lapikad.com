<?php
ob_start();
require_once "_auth.php";
echo "made it past _auth.php ok, role=" . ($_SESSION["role"] ?? "none");
