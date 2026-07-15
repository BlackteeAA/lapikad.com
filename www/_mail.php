<?php
require_once __DIR__ . "/lib/PHPMailer/Exception.php";
require_once __DIR__ . "/lib/PHPMailer/PHPMailer.php";
require_once __DIR__ . "/lib/PHPMailer/SMTP.php";
require_once __DIR__ . "/_mail_config.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// Sends a plain-text email via the configured SMTP mailbox. Returns true/false;
// never throws, so callers (e.g. forgot_password.php) don't need to catch anything.
// Pass $error by reference to capture the reason when it fails (debugging aid).
function sendAppMail($toEmail, $toName, $subject, $body, &$error = null) {
    global $smtpHost, $smtpPort, $smtpSecure, $smtpUser, $smtpPass, $smtpFrom, $smtpFromName;

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
