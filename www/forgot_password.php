<?php
require_once "_db.php";
require_once "_mail.php";
require_once "_ratelimit.php";

$msg  = "";
// Shown whenever a submit was accepted — identical for an address that has an
// account and one that doesn't, so this page can't be used to find out which
// email addresses belong to students. SMTP failures go to the error log only;
// telling the visitor "sending failed" would give the answer away again.
$done = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("forgot_password.php");

    $email = strtolower(trim($_POST["email"] ?? ""));

    if ($email !== "") {
        // Per address (stops mail-bombing one person) and per IP (stops one
        // attacker working through a list). Counted before we look the address
        // up, so a throttled response doesn't depend on whether it exists.
        rateLimitSweep($conn);
        $emailKey = "email:" . $email;
        $ipKey    = "ip:" . client_ip();

        if (rateLimitExceeded($conn, "forgot_email", $emailKey)) {
            $wait = rateLimitWaitText(rateLimitRetryAfter($conn, "forgot_email", $emailKey));
            $msg  = "ขอลิงก์รีเซ็ตบ่อยเกินไป กรุณารออีก " . $wait . " แล้วลองใหม่";
        } elseif (rateLimitExceeded($conn, "forgot_ip", $ipKey)) {
            $wait = rateLimitWaitText(rateLimitRetryAfter($conn, "forgot_ip", $ipKey));
            $msg  = "ขอลิงก์รีเซ็ตบ่อยเกินไป กรุณารออีก " . $wait . " แล้วลองใหม่";
        } else {
            rateLimitHit($conn, "forgot_email", $emailKey);
            rateLimitHit($conn, "forgot_ip", $ipKey);
            $done = true;
        }
    }

    if ($done) {
        $stmt = $conn->prepare("SELECT id, name FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user) {
            $token   = bin2hex(random_bytes(32));
            $hash    = hash("sha256", $token);
            $expires = date("Y-m-d H:i:s", time() + 3600);

            $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id=?");
            $stmt->bind_param("i", $user["id"]);
            $stmt->execute();

            $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $user["id"], $hash, $expires);
            $stmt->execute();

            // Built from APP_ALLOWED_HOSTS, not from the request's Host header:
            // a forged "Host: evil.com" must not end up in a link we email out.
            $resetUrl = app_base_url() . "/reset_password.php?token=" . $token;

            $subject = "รีเซ็ตรหัสผ่าน ล่าพิกัด.com";
            $body    = "คลิกลิงก์นี้เพื่อตั้งรหัสผ่านใหม่ (ใช้ได้ 1 ชั่วโมง):\n\n$resetUrl\n\nถ้าคุณไม่ได้ขอเปลี่ยนรหัสผ่าน สามารถเพิกเฉยอีเมลนี้ได้";
            // The failure reason stays in the server error log (sendAppMail
            // writes it there); it must never reach this public page, because
            // PHPMailer's ErrorInfo carries the SMTP host and username.
            if (!sendAppMail($email, $user["name"], $subject, $body)) {
                error_log("forgot_password: reset mail to $email failed");
            }
        }
        // No else: an unknown address gets exactly the same page.
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ลืมรหัสผ่าน | ล่าพิกัด.com</title>
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Kanit', sans-serif;
      font-weight: 400;
      min-height: 100vh;
      background:
        linear-gradient(rgba(240,249,255,.45), rgba(240,249,255,.45)),
        url("assets/images/lapikadbg.png");
      background-size: cover;
      background-position: center bottom;
    }

    .auth-bar {
      position: sticky; top: 0; z-index: 99;
      display: flex; align-items: center;
      padding: 0 18px; height: 58px;
      background: #fff; box-shadow: 0 1px 0 rgba(0,0,0,.07);
    }

    .auth-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }

    .auth-wrap { max-width: 390px; margin: 0 auto; padding: 32px 18px 60px; }

    .auth-card {
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(8px);
      border-radius: 28px;
      padding: 36px 26px 32px;
      box-shadow: 0 12px 40px rgba(15,23,42,.12);
    }

    .auth-logo { display: flex; justify-content: center; margin-bottom: 18px; }
    .auth-logo img { height: 72px; width: auto; }

    .auth-card h1 { font-size: 22px; font-weight: 700; color: #0f172a; text-align: center; margin-bottom: 8px; }
    .auth-card p.hint { font-size: 13.5px; color: #64748b; text-align: center; margin-bottom: 24px; line-height: 1.5; }

    .auth-card label { display: block; font-size: 14px; font-weight: 500; color: #374151; margin-bottom: 6px; }

    .auth-card input {
      width: 100%; padding: 13px 16px;
      border: 1.5px solid #bfdbfe; border-radius: 14px;
      background: #eff6ff; font-family: 'Kanit', sans-serif;
      font-size: 15px; color: #0f172a; margin-bottom: 16px;
      outline: none; transition: border-color .2s, box-shadow .2s;
    }
    .auth-card input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,.12); background: #fff; }
    .auth-card input::placeholder { color: #94a3b8; }

    .auth-btn {
      display: block; width: 100%; padding: 15px;
      background: #2563eb; color: #fff; border: none; border-radius: 999px;
      font-family: 'Kanit', sans-serif; font-size: 16px; font-weight: 600;
      cursor: pointer; margin-top: 4px; transition: opacity .2s;
    }
    .auth-btn:hover { opacity: .88; }

    .auth-alert {
      background: #f0fdf4; color: #16a34a;
      padding: 12px 14px; border-radius: 12px; font-size: 14px;
      margin-bottom: 16px; line-height: 1.5;
    }
    .auth-alert.bad { background: #fee2e2; color: #dc2626; }

    .auth-bottom { text-align: center; margin-top: 20px; font-size: 14px; color: #374151; }
    .auth-bottom a { color: #2563eb; font-weight: 600; text-decoration: none; margin-left: 4px; }
  </style>
</head>
<body>

  <header class="auth-bar">
    <a href="index.php" class="auth-brand">
      <img src="assets/images/logo.png" alt="ล่าพิกัด.com" style="height:36px;width:auto;">
    </a>
  </header>

  <div class="auth-wrap">
    <div class="auth-card">

      <div class="auth-logo">
        <img src="assets/images/logo.png" alt="ล่าพิกัด.com">
      </div>

      <h1>ลืมรหัสผ่าน</h1>

      <?php if ($done): ?>
        <p class="hint">กรอกอีเมลที่ใช้สมัคร แล้วเราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้</p>
        <div class="auth-alert">ถ้าอีเมลนี้มีบัญชีอยู่ในระบบ เราได้ส่งลิงก์ตั้งรหัสผ่านใหม่ไปให้แล้ว กรุณาตรวจสอบกล่องจดหมาย (รวมถึงถังขยะ/สแปม)</div>
        <div class="auth-bottom">
          <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
      <?php else: ?>
        <p class="hint">กรอกอีเมลที่ใช้สมัคร แล้วเราจะส่งลิงก์สำหรับตั้งรหัสผ่านใหม่ไปให้</p>

        <?php if ($msg !== ""): ?>
          <div class="auth-alert bad"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <label>อีเมล</label>
          <input name="email" type="email" placeholder="กรอกอีเมล" value="<?= e($_POST["email"] ?? "") ?>" required>
          <button class="auth-btn" type="submit">ส่งลิงก์รีเซ็ตรหัสผ่าน</button>
        </form>
        <div class="auth-bottom">
          <a href="login.php">กลับไปหน้าเข้าสู่ระบบ</a>
        </div>
      <?php endif; ?>

    </div>
  </div>

</body>
</html>
