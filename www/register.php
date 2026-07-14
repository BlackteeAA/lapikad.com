<?php
require_once "_db.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("register.php");

    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $msg = "กรุณากรอกข้อมูลให้ครบ";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "รูปแบบอีเมลไม่ถูกต้อง";
    } elseif (mb_strlen($password) < 6) {
        $msg = "รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'user')");
        $stmt->bind_param("sss", $name, $email, $hash);

        if ($stmt->execute()) {
            redirect("login.php");
        } else {
            $msg = "อีเมลนี้ถูกใช้แล้ว";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>สมัครสมาชิก | ล่าพิกัด.com</title>
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
      position: sticky;
      top: 0;
      z-index: 99;
      display: flex;
      align-items: center;
      padding: 0 18px;
      height: 58px;
      background: #fff;
      box-shadow: 0 1px 0 rgba(0,0,0,.07);
    }

    .auth-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: #0f172a;
      font-weight: 600;
      font-size: 15px;
    }

    .auth-wrap {
      max-width: 390px;
      margin: 0 auto;
      padding: 32px 18px 60px;
    }

    .auth-card {
      background: rgba(255,255,255,.92);
      backdrop-filter: blur(8px);
      border-radius: 28px;
      padding: 36px 26px 32px;
      box-shadow: 0 12px 40px rgba(15,23,42,.12);
    }

    .auth-logo {
      display: flex;
      justify-content: center;
      margin-bottom: 18px;
    }

    .auth-logo img { height: 72px; width: auto; }

    .auth-card h1 {
      font-size: 24px;
      font-weight: 700;
      color: #0f172a;
      text-align: center;
      margin-bottom: 24px;
    }

    .auth-card label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      color: #374151;
      margin-bottom: 6px;
    }

    .auth-card input {
      width: 100%;
      padding: 13px 16px;
      border: 1.5px solid #bfdbfe;
      border-radius: 14px;
      background: #eff6ff;
      font-family: 'Kanit', sans-serif;
      font-size: 15px;
      color: #0f172a;
      margin-bottom: 16px;
      outline: none;
      transition: border-color .2s, box-shadow .2s;
    }

    .auth-card input:focus {
      border-color: #2563eb;
      box-shadow: 0 0 0 3px rgba(37,99,235,.12);
      background: #fff;
    }

    .auth-card input::placeholder { color: #94a3b8; }

    .auth-btn {
      display: block;
      width: 100%;
      padding: 15px;
      background: #2563eb;
      color: #fff;
      border: none;
      border-radius: 999px;
      font-family: 'Kanit', sans-serif;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      margin-top: 4px;
      transition: opacity .2s;
    }

    .auth-btn:hover { opacity: .88; }

    .auth-alert {
      display: flex;
      align-items: center;
      gap: 8px;
      background: #fff1f2;
      color: #dc2626;
      border: 1px solid #fecdd3;
      padding: 12px 14px;
      border-radius: 14px;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: 16px;
    }

    .auth-bottom {
      text-align: center;
      margin-top: 20px;
      font-size: 14px;
      color: #374151;
    }

    .auth-bottom a {
      color: #2563eb;
      font-weight: 600;
      text-decoration: none;
      margin-left: 4px;
    }
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

      <h1>สมัครสมาชิก</h1>

      <?php if ($msg): ?>
        <div class="auth-alert">
          <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#dc2626;flex-shrink:0">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
          </svg>
          <?= e($msg) ?>
        </div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <label>ชื่อผู้ใช้</label>
        <input name="name" placeholder="กรอกชื่อผู้ใช้" required>

        <label>อีเมล</label>
        <input name="email" type="email" placeholder="กรอกอีเมล" required>

        <label>รหัสผ่าน</label>
        <input name="password" type="password" placeholder="กรอกรหัสผ่าน" required>

        <button class="auth-btn" type="submit">สมัครสมาชิก</button>
      </form>

      <div class="auth-bottom">
        มีบัญชีแล้ว?<a href="login.php">เข้าสู่ระบบ</a>
      </div>

    </div>
  </div>

</body>
</html>
