<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

function redirect($path) {
    header("Location: " . $path);
    exit;
}
?>
