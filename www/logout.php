<?php
require_once "_db.php";

if (!empty($_COOKIE["remember_token"])) {
    $parts = explode(":", $_COOKIE["remember_token"], 2);
    if (count($parts) === 2) {
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE selector=?");
        $stmt->bind_param("s", $parts[0]);
        $stmt->execute();
    }
    clearRememberCookie();
}

session_destroy();
redirect("index.php");
?>
