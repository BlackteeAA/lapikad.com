<?php
require_once "_db.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = strtolower(trim($_POST["email"] ?? ""));
    $password = $_POST["password"] ?? "";

    if ($name === "" || $email === "" || $password === "") {
        $msg = "กรุณากรอกข้อมูลให้ครบ";
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
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
</head>
<body>
  <main class="app">
    <?php include "includes/topbar.php"; ?>

    <section class="panel">
      <h1>สมัครสมาชิก</h1>
      <p class="muted">สร้างบัญชีเพื่อเริ่มสะสมคะแนน</p>

      <?php if ($msg): ?><div class="alert bad"><?= e($msg) ?></div><?php endif; ?>

      <form method="post" class="form">
        <input name="name" placeholder="ชื่อผู้ใช้" required>
        <input name="email" type="email" placeholder="อีเมล" required>
        <input name="password" type="password" placeholder="รหัสผ่าน" required>
        <button class="btn" type="submit">สมัครสมาชิก</button>
      </form>
    </section>
  </main>
</body>
</html>
