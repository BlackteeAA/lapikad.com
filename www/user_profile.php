<?php
require_once "_auth.php";

$myId      = intval($_SESSION["user_id"]);
$targetId  = intval($_GET["id"] ?? 0);

if (!$targetId) redirect("community.php");

$stmt = $conn->prepare("SELECT id, name, bio, avatar_url, points, role FROM users WHERE id=?");
$stmt->bind_param("i", $targetId);
$stmt->execute();
$target = $stmt->get_result()->fetch_assoc();

if (!$target) redirect("community.php");

$doneQ = $conn->query("SELECT COUNT(*) AS c FROM user_quests WHERE user_id=$targetId")->fetch_assoc()["c"];
$doneP = $conn->query("SELECT COUNT(DISTINCT q.place_id) AS c FROM user_quests uq JOIN quests q ON q.id=uq.quest_id WHERE uq.user_id=$targetId")->fetch_assoc()["c"];

$iFollow = $conn->query("SELECT id FROM user_follows WHERE follower_id=$myId AND following_id=$targetId")->fetch_assoc();

$posts = $conn->query("
    SELECT p.*, pl.name AS pname
    FROM posts p
    LEFT JOIN places pl ON pl.id=p.place_id
    WHERE p.user_id=$targetId
    ORDER BY p.created_at DESC LIMIT 12
")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER["REQUEST_METHOD"] === "POST" && csrf_verify()) {
    if ($iFollow) {
        $conn->query("DELETE FROM user_follows WHERE follower_id=$myId AND following_id=$targetId");
    } else {
        $conn->query("INSERT INTO user_follows (follower_id, following_id) VALUES ($myId, $targetId)");
    }
    redirect("user_profile.php?id=$targetId");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($target["name"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    .up-hero {
      border-radius:20px;overflow:visible;
      background: linear-gradient(rgba(15,30,60,.35),rgba(15,30,60,.35)),
        url("assets/images/lapikadbg.png") center/cover no-repeat;
      height:150px;position:relative;margin-bottom:56px;
    }

    .up-avatar-wrap {
      position:absolute;bottom:-44px;left:50%;transform:translateX(-50%);
      display:flex;flex-direction:column;align-items:center;gap:6px;
    }

    .up-avatar {
      width:88px;height:88px;border-radius:50%;border:4px solid #fff;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      display:flex;align-items:center;justify-content:center;
      font-size:32px;font-weight:700;color:#fff;overflow:hidden;
      box-shadow:0 4px 16px rgba(15,23,42,.15);
    }

    .up-avatar img { width:100%;height:100%;object-fit:cover; }

    .up-name {
      font-size:18px;font-weight:700;color:#0f172a;margin:0;text-align:center;
    }

    .up-bio {
      font-size:13px;color:#64748b;text-align:center;margin:4px 0 0;font-weight:300;
    }

    .up-stats {
      display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;
    }

    .up-stat {
      background:#fff;border-radius:16px;padding:14px;
      text-align:center;box-shadow:0 2px 8px rgba(15,23,42,.05);
    }

    .up-stat strong { display:block;font-size:20px;font-weight:700;color:#0f172a; }
    .up-stat span   { font-size:11px;color:#64748b; }

    .up-follow-btn {
      display:block;width:100%;padding:13px;border-radius:999px;
      font-family:'Kanit',sans-serif;font-size:15px;font-weight:600;
      cursor:pointer;border:none;margin-bottom:14px;
    }

    .up-follow-btn.follow     { background:#2563eb;color:#fff; }
    .up-follow-btn.unfollow   { background:#f1f5f9;color:#374151;border:1.5px solid #e2e8f0; }

    .up-posts-grid {
      display:grid;grid-template-columns:repeat(3,1fr);gap:3px;
    }

    .up-post-thumb {
      aspect-ratio:1;background:#e2e8f0;overflow:hidden;border-radius:4px;
      display:flex;align-items:center;justify-content:center;
    }

    .up-post-thumb img { width:100%;height:100%;object-fit:cover; }

    .up-post-thumb-no {
      font-size:11px;color:#94a3b8;text-align:center;padding:4px;
    }

    .up-section-title {
      font-size:15px;font-weight:700;color:#0f172a;
      margin:16px 0 10px;
    }

    .back-btn {
      display:inline-flex;align-items:center;gap:6px;
      color:#2563eb;font-size:14px;font-weight:500;
      text-decoration:none;margin-bottom:14px;
    }

    .back-btn svg { width:18px;height:18px;fill:currentColor; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9;padding-bottom:90px">
    <?php include "includes/topbar.php"; ?>

    <a href="community.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับชุมชน
    </a>

    <!-- Hero + Avatar -->
    <div class="up-hero">
      <div class="up-avatar-wrap">
        <div class="up-avatar">
          <?php if ($target["avatar_url"]): ?>
            <img src="<?= e($target["avatar_url"]) ?>" alt="">
          <?php else: ?>
            <?= mb_substr($target["name"],0,1,"UTF-8") ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div style="text-align:center;margin-bottom:16px">
      <h1 class="up-name"><?= e($target["name"]) ?></h1>
      <?php if ($target["bio"]): ?>
        <p class="up-bio"><?= e($target["bio"]) ?></p>
      <?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="up-stats">
      <div class="up-stat"><strong><?= number_format($target["points"]) ?></strong><span>คะแนน</span></div>
      <div class="up-stat"><strong><?= $doneQ ?></strong><span>ภารกิจ</span></div>
      <div class="up-stat"><strong><?= $doneP ?></strong><span>สถานที่</span></div>
    </div>

    <!-- Follow button (ไม่แสดงถ้าดูโปรไฟล์ตัวเอง) -->
    <?php if ($targetId !== $myId): ?>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <button class="up-follow-btn <?= $iFollow ? 'unfollow' : 'follow' ?>">
          <?= $iFollow ? 'กำลังติดตาม' : 'ติดตาม' ?>
        </button>
      </form>
    <?php endif; ?>

    <!-- Posts grid -->
    <p class="up-section-title">โพสต์ทั้งหมด</p>
    <?php if (empty($posts)): ?>
      <div style="text-align:center;padding:24px;background:#fff;border-radius:16px;color:#94a3b8;font-size:14px">
        ยังไม่มีโพสต์
      </div>
    <?php else: ?>
      <div class="up-posts-grid">
        <?php foreach ($posts as $po): ?>
          <div class="up-post-thumb">
            <?php if ($po["image_url"]): ?>
              <img src="<?= e($po["image_url"]) ?>" alt="">
            <?php else: ?>
              <div class="up-post-thumb-no"><?= e(mb_substr($po["pname"] ?? $po["content"] ?? "📍", 0, 10, "UTF-8")) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</body>
</html>
