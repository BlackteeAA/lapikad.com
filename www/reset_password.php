<?php
require_once "_db.php";

$msg     = "";
$msgType = "bad";
$token   = $_POST["token"] ?? $_GET["token"] ?? "";
$userId  = null;

if ($token !== "") {
    $hash = hash("sha256", $token);
    $stmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token_hash=? AND expires_at > NOW()");
    $stmt->bind_param("s", $hash);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) $userId = $row["user_id"];
}

$validToken = $userId !== null;
$done       = false;

if ($validToken && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("reset_password.php?token=" . urlencode($token));

    $password  = $_POST["password"] ?? "";
    $password2 = $_POST["password2"] ?? "";

    if (mb_strlen($password) < 6) {
        $msg = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } elseif ($password !== $password2) {
        $msg = "รหัสผ่านทั้งสองช่องไม่ตรงกัน";
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->bind_param("si", $newHash, $userId);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM password_resets WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        // Log out every device: force re-login with the new password everywhere.
        $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id=?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $done = true;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ตั้งรหัสผ่านใหม่ | ล่าพิกัด.com</title>
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

    .auth-alert { background: #fee2e2; color: #dc2626; padding: 10px 14px; border-radius: 12px; font-size: 14px; margin-bottom: 16px; }
    .auth-alert.good { background: #f0fdf4; color: #16a34a; line-height: 1.5; }

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

      <h1>ตั้งรหัสผ่านใหม่</h1>

      <?php if (!$validToken): ?>
        <div class="auth-alert">ลิงก์นี้ไม่ถูกต้องหรือหมดอายุแล้ว กรุณาขอลิงก์ใหม่</div>
        <div class="auth-bottom">
          <a href="forgot_password.php">ขอลิงก์รีเซ็ตรหัสผ่านใหม่</a>
        </div>
      <?php elseif ($done): ?>
        <div class="auth-alert good">ตั้งรหัสผ่านใหม่สำเร็จแล้ว เข้าสู่ระบบด้วยรหัสผ่านใหม่ได้เลย</div>
        <div class="auth-bottom">
          <a href="login.php">ไปหน้าเข้าสู่ระบบ</a>
        </div>
      <?php else: ?>
        <p class="hint">ตั้งรหัสผ่านใหม่สำหรับบัญชีของคุณ</p>

        <?php if ($msg): ?>
          <div class="auth-alert"><?= e($msg) ?></div>
        <?php endif; ?>

        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="token" value="<?= e($token) ?>">

          <label>รหัสผ่านใหม่</label>
          <input name="password" type="password" placeholder="อย่างน้อย 6 ตัวอักษร" required>

          <label>ยืนยันรหัสผ่านใหม่</label>
          <input name="password2" type="password" placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" required>

          <button class="auth-btn" type="submit">บันทึกรหัสผ่านใหม่</button>
        </form>
      <?php endif; ?>

    </div>
  </div>

</body>
</html>
