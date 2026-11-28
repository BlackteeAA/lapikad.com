<?php
require_once __DIR__ . "/lib/PHPMailer/Exception.php";
require_once __DIR__ . "/lib/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/lib/PHPMailer/SMTP.php";

// Same two sources as the database config in _db.php: the copied config file
// first, then the environment. This used to be an unconditional require, so a
// checkout without _mail_config.php made forgot_password.php die with a fatal
// error — and print the full server path and stack trace to the visitor.
// Mail being unconfigured now just means sendAppMail() returns false.
if (is_file(__DIR__ . "/_mail_config.php")) {
    require_once __DIR__ . "/_mail_config.php";
}

$smtpHost     = $smtpHost     ?? (getenv("SMTP_HOST") ?: "");
$smtpPort     = $smtpPort     ?? (getenv("SMTP_PORT") ?: 587);
$smtpSecure   = $smtpSecure   ?? (getenv("SMTP_SECURE") ?: "tls");
$smtpUser     = $smtpUser     ?? (getenv("SMTP_USER") ?: "");
$smtpPass     = $smtpPass     ?? (getenv("SMTP_PASS") ?: "");
$smtpFrom     = $smtpFrom     ?? (getenv("SMTP_FROM") ?: "");
$smtpFromName = $smtpFromName ?? (getenv("SMTP_FROM_NAME") ?: "ล่าพิกัด.com");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Sends a plain-text email via the configured SMTP mailbox. Returns true/false;
// never throws, so callers (e.g. forgot_password.php) don't need to catch anything.
// Pass $error by reference to capture the reason when it fails (debugging aid).
function sendAppMail($toEmail, $toName, $subject, $body, &$error = null) {
    global $smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName;

    if ($smtpHost === "" || $smtpFrom === "") {
        $error = "SMTP is not configured";
        error_log("sendAppMail: no SMTP settings — copy www/_mail_config.example.php to www/_mail_config.php");
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = $smtpSecure;
        $mail->Port       = $smtpPort;
        $mail->CharSet    = "UTF-8";
        $mail->Timeout    = 10; // seconds — fail fast instead of hanging on a bad/placeholder host

        $mail->setFrom($smtpFrom, $smtpFromName);
        $mail->addAddress($toEmail, $toName);

        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        $error = $mail->ErrorInfo;
        error_log("sendAppMail failed: " . $error);
        return false;
    }
}
?>
