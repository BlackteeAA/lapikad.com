<?php
require_once "_db.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    $stmt = $conn->prepare("SELECT id, name, password_hash, role, points FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && password_verify($password, $user["password_hash"])) {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["name"] = $user["name"];
        $_SESSION["role"] = $user["role"];
        redirect("dashboard.php");
    } else {
        $msg = "อีเมลหรือรหัสผ่านไม่ถูกต้อง";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>เข้าสู่ระบบ | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <main class="app centered">
    <section class="panel">
      <h1>ล่าพิกัด.com</h1>
      <p class="muted">เข้าสู่ระบบเพื่อเริ่มการเดินทาง</p>

      <?php if ($msg): ?><div class="alert bad"><?= e($msg) ?></div><?php endif; ?>

      <form method="post" class="form">
        <input name="email" type="email" placeholder="อีเมล" required>
        <input name="password" type="password" placeholder="รหัสผ่าน" required>
        <button class="btn" type="submit">เข้าสู่ระบบ</button>
        <a class="btn ghost" href="register.php">สมัครสมาชิก</a>
      </form>
    </section>
  </main>
</body>
</html>
