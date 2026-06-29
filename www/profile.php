<?php
require_once "_db.php";

$isLoggedIn = isset($_SESSION["user_id"]);
$user = null;
$msg = "";

if ($isLoggedIn) {
    $userId = intval($_SESSION["user_id"]);
    $stmt = $conn->prepare("SELECT name, email, role, points, bio, avatar_url FROM users WHERE id=?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if (!$user) { session_destroy(); redirect("login.php"); }

    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!csrf_verify()) redirect("profile.php");
        $newName   = trim($_POST["name"] ?? "");
        $newBio    = trim($_POST["bio"] ?? "");
        $avatarUrl = $user["avatar_url"] ?? "";

        if (isset($_FILES["avatar"]) && $_FILES["avatar"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["avatar"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext,["jpg","jpeg","png","webp"]) && $_FILES["avatar"]["size"] < 5*1024*1024) {
                $fname = "avatar_{$userId}_" . time() . ".$ext";
                $dest  = __DIR__ . "/assets/images/avatars/$fname";
                if (!is_dir(__DIR__ . "/assets/images/avatars/")) mkdir(__DIR__ . "/assets/images/avatars/", 0755, true);
                if (move_uploaded_file($_FILES["avatar"]["tmp_name"], $dest))
                    $avatarUrl = "assets/images/avatars/$fname";
            }
        }

        if ($newName !== "") {
            $stmt = $conn->prepare("UPDATE users SET name=?, bio=?, avatar_url=? WHERE id=?");
            $stmt->bind_param("sssi", $newName, $newBio, $avatarUrl, $userId);
            $stmt->execute();
            $_SESSION["name"]    = $newName;
            $user["name"]        = $newName;
            $user["bio"]         = $newBio;
            $user["avatar_url"]  = $avatarUrl;
            $msg = "บันทึกเรียบร้อย";
        }
    }

    $doneQ = $conn->query("SELECT COUNT(*) AS c FROM user_quests WHERE user_id=$userId")->fetch_assoc()["c"];
    $doneP = $conn->query("SELECT COUNT(DISTINCT q.place_id) AS c FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=$userId")->fetch_assoc()["c"];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ฉัน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .pf-header { display:flex;align-items:center;gap:12px;padding:14px 0 16px; }
    .pf-back {
      width:36px;height:36px;border-radius:50%;background:#fff;border:none;
      cursor:pointer;display:flex;align-items:center;justify-content:center;
      box-shadow:0 2px 8px rgba(15,23,42,.08);text-decoration:none;flex-shrink:0;
    }
    .pf-back svg { width:18px;height:18px;fill:#0f172a; }
    .pf-header-text h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }
    .pf-header-text p  { font-size:13px;color:#64748b;margin:0;font-weight:300; }

    .pf-hero {
      border-radius:20px;overflow:visible;
      background: linear-gradient(rgba(15,30,60,.3),rgba(15,30,60,.3)),
        url("assets/images/lapikadbg.png") center/cover no-repeat;
      height:160px;position:relative;margin-bottom:60px;
    }

    .pf-avatar-wrap {
      position:absolute;bottom:-48px;left:50%;transform:translateX(-50%);
    }

    .pf-avatar {
      width:96px;height:96px;border-radius:50%;border:4px solid #fff;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      display:flex;align-items:center;justify-content:center;
      font-size:36px;font-weight:700;color:#fff;overflow:hidden;
      box-shadow:0 4px 16px rgba(15,23,42,.15);
    }

    .pf-avatar img { width:100%;height:100%;object-fit:cover; }

    .pf-cam-btn {
      position:absolute;bottom:2px;right:2px;
      width:30px;height:30px;border-radius:50%;
      background:#2563eb;border:2px solid #fff;
      display:flex;align-items:center;justify-content:center;cursor:pointer;
    }
    .pf-cam-btn svg { width:14px;height:14px;fill:#fff; }

    .pf-stats {
      display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;
    }
    .pf-stat {
      background:#fff;border-radius:16px;padding:14px;
      text-align:center;box-shadow:0 2px 8px rgba(15,23,42,.05);
    }
    .pf-stat strong { display:block;font-size:20px;font-weight:700;color:#0f172a; }
    .pf-stat span   { font-size:11px;color:#64748b; }

    .pf-card { background:#fff;border-radius:20px;padding:20px;box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:14px; }
    .pf-card h2 { font-size:16px;font-weight:700;color:#0f172a;margin:0 0 16px; }

    .pf-grid { display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px; }

    .pf-field label { display:block;font-size:13px;font-weight:500;color:#374151;margin-bottom:6px; }

    .pf-input-wrap { position:relative; }
    .pf-input-wrap svg {
      position:absolute;left:12px;top:50%;transform:translateY(-50%);
      width:16px;height:16px;fill:#94a3b8;
    }
    .pf-input-wrap input, .pf-input-wrap .pf-ro {
      display:block;width:100%;padding:12px 12px 12px 36px;
      border:1.5px solid #bfdbfe;border-radius:12px;
      background:#eff6ff;font-family:'Kanit',sans-serif;font-size:14px;color:#0f172a;outline:none;
    }
    .pf-ro { color:#94a3b8;background:#f8fafc;border-color:#e2e8f0; }
    .pf-input-wrap input:focus {
      border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);background:#fff;
    }

    .pf-bio-wrap textarea {
      width:100%;padding:12px 14px;border:1.5px solid #bfdbfe;border-radius:12px;
      background:#eff6ff;font-family:'Kanit',sans-serif;font-size:14px;color:#0f172a;
      outline:none;resize:none;min-height:90px;
    }
    .pf-bio-wrap textarea:focus {
      border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1);background:#fff;
    }
    .pf-counter { text-align:right;font-size:11px;color:#94a3b8;margin-top:4px; }

    .pf-save {
      display:block;width:100%;padding:15px;background:#2563eb;color:#fff;
      border:none;border-radius:999px;font-family:'Kanit',sans-serif;
      font-size:16px;font-weight:600;cursor:pointer;margin-top:16px;transition:opacity .2s;
    }
    .pf-save:hover { opacity:.88; }

    .pf-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:14px;background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }

    .pf-logout {
      display:block;width:100%;padding:14px;background:#fff1f2;color:#e11d48;
      border:1px solid #fecdd3;border-radius:999px;font-family:'Kanit',sans-serif;
      font-size:15px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9;padding-bottom:90px">
    <?php include "includes/topbar.php"; ?>

    <?php if (!$isLoggedIn): ?>
      <div style="text-align:center;padding:40px 20px">
        <p style="color:#64748b;margin-bottom:16px">เข้าสู่ระบบเพื่อดูโปรไฟล์</p>
        <a href="login.php" style="display:inline-block;padding:12px 28px;background:#2563eb;color:#fff;border-radius:999px;font-weight:600;text-decoration:none">เข้าสู่ระบบ</a>
      </div>
    <?php else: ?>

    <div class="pf-header">
      <a href="dashboard.php" class="pf-back">
        <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      </a>
      <div class="pf-header-text">
        <h1>แก้ไขโปรไฟล์</h1>
        <p>อัพเดตข้อมูลของคุณ</p>
      </div>
    </div>

    <?php if ($msg): ?><div class="pf-alert"><?= e($msg) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="file" name="avatar" id="avatar-input" accept="image/*" style="display:none">

      <div class="pf-hero">
        <div class="pf-avatar-wrap">
          <div class="pf-avatar">
            <?php if (!empty($user["avatar_url"])): ?>
              <img src="<?= e($user["avatar_url"]) ?>" alt="" id="avatar-preview">
            <?php else: ?>
              <span id="avatar-letter"><?= e(mb_substr($user["name"],0,1,"UTF-8")) ?></span>
              <img src="" alt="" id="avatar-preview" style="display:none">
            <?php endif; ?>
          </div>
          <label for="avatar-input" class="pf-cam-btn">
            <svg viewBox="0 0 24 24"><path d="M12 15.2A3.2 3.2 0 1 1 12 8.8a3.2 3.2 0 0 1 0 6.4zM9 2L7.17 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2h-3.17L15 2H9z"/></svg>
          </label>
        </div>
      </div>

      <div class="pf-stats">
        <div class="pf-stat"><strong><?= number_format($user["points"]) ?></strong><span>คะแนน</span></div>
        <div class="pf-stat"><strong><?= $doneQ ?></strong><span>ภารกิจ</span></div>
        <div class="pf-stat"><strong><?= $doneP ?></strong><span>สถานที่</span></div>
      </div>

      <div class="pf-card">
        <h2>ข้อมูลส่วนตัว</h2>
        <div class="pf-grid">
          <div class="pf-field">
            <label>ชื่อเล่น</label>
            <div class="pf-input-wrap">
              <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-5 0-9 2.5-9 5.5V22h18v-2.5C21 16.5 17 14 12 14z"/></svg>
              <input name="name" value="<?= e($user["name"]) ?>" required maxlength="50">
            </div>
          </div>
          <div class="pf-field">
            <label>อีเมล</label>
            <div class="pf-input-wrap">
              <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
              <div class="pf-ro"><?= e($user["email"]) ?></div>
            </div>
          </div>
        </div>
        <div class="pf-field">
          <label>เกี่ยวกับตัวคุณ</label>
          <div class="pf-bio-wrap">
            <textarea name="bio" id="bio-input" maxlength="200" placeholder="บอกเล่าเกี่ยวกับตัวคุณ..."><?= e($user["bio"] ?? "") ?></textarea>
          </div>
          <div class="pf-counter"><span id="bio-count"><?= mb_strlen($user["bio"] ?? "") ?></span>/200</div>
        </div>
        <button class="pf-save" type="submit">บันทึก</button>
      </div>
    </form>

    <?php if ($user["role"] === "admin"): ?>
      <a href="admin.php" style="display:block;text-align:center;padding:14px;background:#fff;border-radius:999px;font-weight:600;color:#2563eb;text-decoration:none;margin-bottom:14px;box-shadow:0 2px 8px rgba(15,23,42,.06)">
        แผงควบคุม Admin
      </a>
    <?php endif; ?>

    <a href="logout.php" class="pf-logout">ออกจากระบบ</a>

    <?php endif; ?>
  </main>

  <script>
  document.getElementById('avatar-input')?.addEventListener('change', e => {
    const f = e.target.files[0]; if (!f) return;
    const prev = document.getElementById('avatar-preview');
    const ltr  = document.getElementById('avatar-letter');
    prev.src = URL.createObjectURL(f);
    prev.style.display = 'block';
    if (ltr) ltr.style.display = 'none';
  });
  document.getElementById('bio-input')?.addEventListener('input', function() {
    document.getElementById('bio-count').textContent = this.value.length;
  });
  </script>
</body>
</html>
