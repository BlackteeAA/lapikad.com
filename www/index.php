<?php
require_once "_bootstrap.php";
require_once "_db.php";

$topUsers = $conn->query("SELECT name, points FROM users ORDER BY points DESC LIMIT 5");
$topUsersData = [];
while ($u = $topUsers->fetch_assoc()) {
    $topUsersData[] = $u;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ล่าพิกัด.com</title>
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
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
        linear-gradient(rgba(240,249,255,0.45), rgba(240,249,255,0.45)),
        url("assets/images/lapikadbg.png");
      background-size: cover;
      background-position: center bottom;
      background-attachment: scroll;
    }

    /* ── Topbar ── */
    .lp-bar {
      position: sticky;
      top: 0;
      z-index: 99;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 18px;
      height: 58px;
      overflow: visible;
      background: #fff;
      box-shadow: 0 1px 0 rgba(0,0,0,.07);
    }

    .lp-brand {
      display: flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: #0f172a;
      font-weight: 600;
      font-size: 15px;
    }

    .lp-login {
      background: #2563eb;
      color: #fff;
      padding: 8px 20px;
      border-radius: 999px;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      white-space: nowrap;
    }

    /* ── Content ── */
    .lp-wrap {
      max-width: 430px;
      margin: 0 auto;
      padding: 28px 18px 60px;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }

    /* ── Hero card ── */
    .lp-hero {
      background: transparent;
      padding: 38px 8px 30px;
      text-align: center;
    }

    .lp-hero h1 {
      font-size: 42px;
      font-weight: 700;
      line-height: 1.25;
      margin-bottom: 12px;
      background: linear-gradient(135deg, #2563eb, #22c55e);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .lp-hero p {
      font-size: 15px;
      font-weight: 400;
      color: #1e3a5f;
      line-height: 1.75;
      margin-bottom: 28px;
      text-shadow: 0 1px 3px rgba(255,255,255,.6);
    }

    .lp-start {
      display: block;
      background: #2563eb;
      color: #fff;
      text-align: center;
      padding: 15px;
      border-radius: 999px;
      font-size: 16px;
      font-weight: 500;
      text-decoration: none;
      margin-bottom: 14px;
    }

    .lp-register {
      display: block;
      text-align: center;
      color: #374151;
      font-size: 14px;
      font-weight: 400;
      text-decoration: underline;
    }

    /* ── Ranking card ── */
    .lp-rank {
      background: rgba(255,255,255,.86);
      backdrop-filter: blur(6px);
      border-radius: 24px;
      padding: 22px 20px;
      box-shadow: 0 10px 36px rgba(15,23,42,.10);
    }

    .lp-rank h2 {
      font-size: 17px;
      font-weight: 600;
      color: #0f172a;
      margin-bottom: 14px;
    }

    .rank-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 11px 12px;
      border-radius: 14px;
      background: #f8fafc;
      margin-bottom: 8px;
    }

    .rank-row:last-child { margin-bottom: 0; }

    .rn {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 13px;
      font-weight: 600;
      flex-shrink: 0;
      background: #e2e8f0;
      color: #475569;
    }

    .rn-1 { background: #f59e0b; color: #fff; }
    .rn-2 { background: #94a3b8; color: #fff; }
    .rn-3 { background: #b45309; color: #fff; }

    .rname { flex: 1; font-size: 14px; font-weight: 500; color: #0f172a; }
    .rpts  { font-size: 13px; font-weight: 600; color: #2563eb; }

    /* ── Animations ── */
    .anim {
      opacity: 0;
      transform: translateY(22px);
      transition: opacity .55s ease, transform .55s ease;
    }
    .anim.in {
      opacity: 1;
      transform: none;
    }
  </style>
</head>
<body>

  <header class="lp-bar">
    <a href="index.php" class="lp-brand">
      <img src="assets/images/logo.png" alt="ล่าพิกัด.com" style="height:36px;width:auto;">
    </a>
    <a href="login.php" class="lp-login">เข้าสู่ระบบ</a>
  </header>

  <div class="lp-wrap">

    <div class="lp-hero anim">
      <h1>ล่าพิกัด<br>พิชิตภารกิจ</h1>
      <p>สแกน QR Code ทำภารกิจ<br>ถ่ายรูปตอบคำถาม แลกของรางวัล!!</p>
      <a href="login.php" class="lp-start">เริ่มใช้งาน &rarr;</a>
      <a href="register.php" class="lp-register">สมัครสมาชิก</a>
    </div>

    <div class="lp-rank anim" style="transition-delay:.14s">
      <h2>อันดับผู้ล่าพิกัด</h2>

      <?php if (empty($topUsersData)): ?>
        <p style="color:#94a3b8;text-align:center;font-size:14px;padding:10px 0">ยังไม่มีข้อมูล</p>
      <?php else: ?>
        <?php foreach ($topUsersData as $i => $u): ?>
          <div class="rank-row anim" style="transition-delay:<?= 0.2 + $i * 0.07 ?>s">
            <span class="rn rn-<?= $i + 1 ?>"><?= $i + 1 ?></span>
            <span class="rname"><?= e($u['name']) ?></span>
            <span class="rpts"><?= number_format($u['points']) ?> pts</span>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <script>
    const io = new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('in'); });
    }, { threshold: 0.1 });
    document.querySelectorAll('.anim').forEach(el => io.observe(el));
  </script>

</body>
</html>
