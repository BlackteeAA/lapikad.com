<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);
$tab    = $_GET["tab"] ?? "foryou";

// Post creation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"])) {
    if (!csrf_verify()) redirect("community.php");

    if ($_POST["action"] === "create_post") {
        $content  = trim($_POST["content"] ?? "");
        $placeId  = intval($_POST["place_id"] ?? 0) ?: null;
        $imgUrl   = "";

        if (isset($_FILES["post_image"]) && $_FILES["post_image"]["error"] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES["post_image"]["name"], PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg","jpeg","png","webp","heic"]) && $_FILES["post_image"]["size"] < 10*1024*1024) {
                $fname = "post_" . $userId . "_" . time() . "." . $ext;
                $dest  = __DIR__ . "/assets/images/posts/" . $fname;
                if (!is_dir(__DIR__ . "/assets/images/posts/")) mkdir(__DIR__ . "/assets/images/posts/", 0755, true);
                if (move_uploaded_file($_FILES["post_image"]["tmp_name"], $dest)) {
                    $imgUrl = "assets/images/posts/" . $fname;
                }
            }
        }

        if ($content !== "" || $imgUrl !== "") {
            $stmt = $conn->prepare("INSERT INTO posts (user_id, place_id, content, image_url) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiss", $userId, $placeId, $content, $imgUrl);
            $stmt->execute();
        }
        redirect("community.php");
    }
}

// Feed query
$tabSql = match($tab) {
    "following" => "AND EXISTS (SELECT 1 FROM user_follows f WHERE f.follower_id=$userId AND f.following_id=p.user_id)",
    "nearby"    => "AND pl.lat IS NOT NULL AND pl.lng IS NOT NULL",
    default     => ""
};

// "foryou" pulls a wider candidate pool so the ranking algorithm has posts to sort through
$poolLimit = $tab === "foryou" ? 60 : 30;

$posts = $conn->query("
    SELECT p.*,
           u.name AS uname, u.avatar_url AS uavatar,
           pl.name AS pname, pl.category AS pcat,
           pl.district, pl.province, pl.lat, pl.lng,
           (SELECT COUNT(*) FROM post_likes l WHERE l.post_id=p.id) AS likes,
           (SELECT COUNT(*) FROM post_likes l WHERE l.post_id=p.id AND l.user_id=$userId) AS my_like,
           (SELECT COUNT(*) FROM user_follows f WHERE f.follower_id=$userId AND f.following_id=p.user_id) AS i_follow,
           (SELECT COUNT(*) FROM post_comments c WHERE c.post_id=p.id) AS comments
    FROM posts p
    JOIN users u ON u.id=p.user_id
    LEFT JOIN places pl ON pl.id=p.place_id
    WHERE 1=1 $tabSql
    ORDER BY p.created_at DESC
    LIMIT $poolLimit
")->fetch_all(MYSQLI_ASSOC);

// Hacker-News-style "hot" score: engagement decayed by age, so fresh popular
// posts float up without letting old posts dominate forever.
function hotScore($likes, $comments, $createdAt) {
    $ageHours = max(0, (time() - strtotime($createdAt)) / 3600);
    $points   = $likes + ($comments * 2);
    return $points / pow($ageHours + 2, 1.5);
}

if ($tab === "foryou") {
    // Categories the user actually engages with, inferred from completed quests
    $topCats = array_column($conn->query("
        SELECT pl.category, COUNT(*) AS c
        FROM user_quests uq
        JOIN quests q  ON q.id = uq.quest_id
        JOIN places pl ON pl.id = q.place_id
        WHERE uq.user_id = $userId AND pl.category IS NOT NULL
        GROUP BY pl.category
        ORDER BY c DESC
        LIMIT 3
    ")->fetch_all(MYSQLI_ASSOC), "category");

    foreach ($posts as &$p) {
        $score = hotScore($p["likes"], $p["comments"], $p["created_at"]);
        if ($p["i_follow"] > 0) $score *= 1.4;                          // ติดตามผู้เขียนอยู่
        if ($p["pcat"] && in_array($p["pcat"], $topCats)) $score *= 1.2; // ตรงหมวดที่สนใจ
        $p["_score"] = $score;
    }
    unset($p);
    usort($posts, fn($a, $b) => $b["_score"] <=> $a["_score"]);
    $posts = array_slice($posts, 0, 30);
}

// Places for select
$places = $conn->query("SELECT id, name FROM places ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// User info
$stmt = $conn->prepare("SELECT name, points FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();

function timeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60)   return "เมื่อกี้";
    if ($d < 3600) return intval($d/60) . " นาที ที่แล้ว";
    if ($d < 86400)return intval($d/3600) . " ชม. ที่แล้ว";
    return intval($d/86400) . " วัน ที่แล้ว";
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>ชุมชน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }

    /* ── Hero ── */
    .cm-hero {
      border-radius: 20px;
      overflow: hidden;
      margin-bottom: 14px;
      background:
        linear-gradient(rgba(15,30,60,.45), rgba(15,30,60,.45)),
        url("assets/images/lapikadbg.png") center/cover no-repeat;
      padding: 24px 18px 20px;
    }

    .cm-hero h1 { font-size:22px;font-weight:700;color:#fff;margin:0 0 4px; }
    .cm-hero p  { font-size:13px;color:rgba(255,255,255,.8);margin:0 0 16px;font-weight:300; }

    .cm-hero-btns { display:grid;grid-template-columns:1fr 1fr;gap:10px; }

    .cm-hero-btn {
      display:flex;align-items:center;justify-content:space-between;
      background:rgba(255,255,255,.97);
      border-radius:16px;padding:14px 16px;
      text-decoration:none;color:#0f172a;
      border:none;font-family:inherit;cursor:pointer;
      font-size:15px;font-weight:600;width:100%;text-align:left;
      box-shadow:0 2px 8px rgba(0,0,0,.08);
    }

    .cm-hero-btn .icon {
      width:38px;height:38px;border-radius:50%;
      display:flex;align-items:center;justify-content:center;flex-shrink:0;
    }

    .cm-hero-btn .icon svg { width:20px;height:20px; }
    .cm-hero-btn small { display:block;font-size:11px;font-weight:400;color:#64748b;margin-top:2px; }

    /* ── Tabs ── */
    .cm-tabs {
      display:flex;gap:0;
      background:#fff;border-radius:14px;padding:4px;
      margin-bottom:16px;box-shadow:0 2px 8px rgba(15,23,42,.06);
    }

    .cm-tab {
      flex:1;padding:9px 8px;border:none;border-radius:10px;
      font-family:inherit;font-size:13px;font-weight:500;
      cursor:pointer;background:transparent;color:#64748b;
      transition:all .2s;
    }

    .cm-tab.active { background:#2563eb;color:#fff;font-weight:600; }

    /* ── Post card ── */
    .cm-card {
      background:#fff;border-radius:20px;
      margin-bottom:14px;overflow:hidden;
      box-shadow:0 2px 10px rgba(15,23,42,.06);
    }

    .cm-card-head {
      display:flex;align-items:center;gap:10px;
      padding:14px 14px 0;
    }

    .cm-avatar {
      width:40px;height:40px;border-radius:50%;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      display:flex;align-items:center;justify-content:center;
      color:#fff;font-size:16px;font-weight:700;flex-shrink:0;
    }

    .cm-meta { flex:1;min-width:0; }
    .cm-meta strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .cm-meta span   { font-size:12px;color:#94a3b8; }

    .cm-gps-status {
      display:flex;align-items:center;gap:8px;
      padding:10px 14px;border-radius:12px;
      font-size:13px;font-weight:500;margin-bottom:14px;
    }
    .cm-gps-status.loading { background:#eff6ff;color:#2563eb; }
    .cm-gps-status.ok      { background:#f0fdf4;color:#16a34a; }
    .cm-gps-status.error   { background:#fff7ed;color:#ea580c; }
    .cm-gps-dot { width:8px;height:8px;border-radius:50%;background:currentColor;flex-shrink:0; }

    .cm-dist {
      font-size:11px;font-weight:600;color:#16a34a;
      background:#f0fdf4;padding:3px 8px;border-radius:99px;flex-shrink:0;
    }
    .cm-dist.far  { background:#fff1f2;color:#e11d48; }
    .cm-dist.none { background:#f8fafc;color:#94a3b8; }

    .cm-follow-btn {
      padding:6px 14px;border-radius:999px;
      font-size:12px;font-weight:600;font-family:inherit;cursor:pointer;
      border:1.5px solid #2563eb;background:#2563eb;color:#fff;
      transition:all .2s;flex-shrink:0;
    }

    .cm-follow-btn.following {
      background:#fff;color:#2563eb;border-color:#2563eb;
    }

    /* Badge */
    .cm-badge {
      display:inline-block;background:#2563eb;color:#fff;
      font-size:11px;font-weight:700;padding:3px 8px;
      border-radius:6px;margin:10px 14px 0;
    }

    .cm-img {
      width:100%;aspect-ratio:16/9;object-fit:cover;display:block;
      margin-top:10px;
    }

    .cm-body { padding:12px 14px 0; }

    .cm-place {
      display:flex;align-items:center;gap:5px;
      font-size:18px;font-weight:700;color:#0f172a;margin:0 0 4px;
    }

    .cm-place svg { width:16px;height:16px;fill:#2563eb;flex-shrink:0; }

    .cm-loc {
      font-size:13px;color:#64748b;margin:0 0 8px;
    }

    .cm-content { font-size:14px;color:#374151;margin:0 0 10px;line-height:1.5; }

    .cm-tags { display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px; }

    .cm-tag {
      font-size:12px;font-weight:500;padding:4px 10px;
      border-radius:999px;background:#eff6ff;color:#2563eb;
    }

    .cm-actions {
      display:flex;align-items:center;gap:0;
      border-top:1px solid #f1f5f9;padding:10px 10px;
    }

    .cm-action {
      flex:1;display:flex;align-items:center;justify-content:center;gap:5px;
      border:none;background:transparent;font-family:inherit;
      font-size:13px;font-weight:500;color:#64748b;cursor:pointer;
      padding:6px;border-radius:10px;transition:background .15s;
    }

    .cm-action:hover { background:#f8fafc; }
    .cm-action svg { width:18px;height:18px; }
    .cm-action.liked { color:#ef4444; }
    .cm-action.liked svg { fill:#ef4444; }

    .cm-bookmark {
      border:none;background:transparent;cursor:pointer;
      padding:6px 10px;color:#94a3b8;
    }

    .cm-bookmark svg { width:18px;height:18px; }

    /* Create post modal */
    .cm-modal-bg {
      display:none;position:fixed;inset:0;
      background:rgba(0,0,0,.5);z-index:500;
      align-items:flex-end;
    }

    .cm-modal-bg.open { display:flex; }

    .cm-modal {
      background:#fff;border-radius:24px 24px 0 0;
      padding:20px;width:100%;max-height:90vh;overflow-y:auto;
    }

    .cm-modal h2 { font-size:18px;font-weight:700;margin:0 0 14px; }

    .cm-modal textarea {
      width:100%;min-height:90px;border:1.5px solid #bfdbfe;
      border-radius:14px;padding:12px;font-family:inherit;
      font-size:14px;resize:none;
    }

    .cm-photo-preview {
      width:100%;border-radius:14px;margin-top:10px;
      max-height:200px;object-fit:cover;display:none;
    }

    .cm-empty {
      text-align:center;padding:40px 20px;
      background:#fff;border-radius:20px;color:#94a3b8;font-size:14px;
    }

    .cm-comments {
      border-top:1px solid #f1f5f9;
      padding:12px 14px;
    }

    .cmt-item {
      display:flex;gap:8px;margin-bottom:10px;
    }

    .cmt-av {
      width:28px;height:28px;border-radius:50%;
      background:linear-gradient(135deg,#2563eb,#7c3aed);
      color:#fff;font-size:11px;font-weight:700;
      display:flex;align-items:center;justify-content:center;flex-shrink:0;
    }

    .cmt-bubble {
      background:#f1f5f9;border-radius:0 12px 12px 12px;
      padding:8px 12px;flex:1;
    }

    .cmt-bubble strong { font-size:12px;font-weight:700;color:#0f172a; }
    .cmt-bubble p { font-size:13px;color:#374151;margin:2px 0 0; }

    .cmt-input {
      display:flex;gap:8px;align-items:center;margin-top:8px;
    }

    .cmt-input input {
      flex:1;border-radius:999px;padding:9px 14px;font-size:13px;
      border:1.5px solid #bfdbfe;background:#eff6ff;
    }

    .cmt-input button {
      width:36px;height:36px;border-radius:50%;
      background:#eff6ff;border:none;cursor:pointer;
      display:flex;align-items:center;justify-content:center;flex-shrink:0;
    }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9;padding-bottom:90px">
    <?php include "includes/topbar.php"; ?>

    <!-- Hero -->
    <div class="cm-hero">
      <h1>ชุมชนล่าพิกัด</h1>
      <p>แบ่งปันสถานที่สวยงาม ค้นพบแรงบันดาลใจให้การเดินทาง</p>
      <div class="cm-hero-btns">
        <button class="cm-hero-btn" onclick="document.getElementById('create-modal').classList.add('open')">
          <div>โพสต์ใหม่<small>แชร์สถานที่ของคุณ</small></div>
          <div class="icon" style="background:#16a34a">
            <svg viewBox="0 0 24 24" style="fill:#fff"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
          </div>
        </button>
        <a href="places.php" class="cm-hero-btn">
          <div>ค้นหาสถานที่<small>ค้นหาตามความสนใจ</small></div>
          <div class="icon" style="background:#2563eb">
            <svg viewBox="0 0 24 24" style="fill:#fff"><path d="M15.5 14h-.79l-.28-.27A6.47 6.47 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
          </div>
        </a>
      </div>
    </div>

    <!-- Tabs -->
    <div class="cm-tabs">
      <button class="cm-tab <?= $tab==='foryou'?'active':'' ?>" onclick="switchTab('foryou')">สำหรับคุณ</button>
      <button class="cm-tab <?= $tab==='following'?'active':'' ?>" onclick="switchTab('following')">กำลังติดตาม</button>
      <button class="cm-tab <?= $tab==='nearby'?'active':'' ?>" onclick="switchTab('nearby')">ใกล้ตัวคุณ</button>
    </div>

    <?php if ($tab === "nearby"): ?>
      <div class="cm-gps-status loading" id="cm-gps-status">
        <span class="cm-gps-dot"></span><span>กำลังระบุตำแหน่ง GPS...</span>
      </div>
    <?php endif; ?>

    <!-- Feed -->
    <?php if (empty($posts)): ?>
      <div class="cm-empty">
        ยังไม่มีโพสต์<br><small>เป็นคนแรกที่แชร์สถานที่ของคุณ!</small>
      </div>
    <?php else: ?>
      <?php foreach ($posts as $p):
        $isMe    = $p["user_id"] == $userId;
        $liked   = $p["my_like"] > 0;
        $follows = $p["i_follow"] > 0;
        $hot     = hotScore($p["likes"], $p["comments"], $p["created_at"]) >= 2.5;
        $tags    = array_filter([$p["pcat"], $p["district"] ? "อ.".$p["district"] : null]);
      ?>
        <div class="cm-card" data-post="<?= $p["id"] ?>" data-lat="<?= $p["lat"] ?: '' ?>" data-lng="<?= $p["lng"] ?: '' ?>">
          <div class="cm-card-head">
            <a href="user_profile.php?id=<?= $p["user_id"] ?>" style="text-decoration:none;flex-shrink:0">
              <div class="cm-avatar">
                <?php if ($p["uavatar"]): ?>
                  <img src="<?= e($p["uavatar"]) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
                <?php else: ?>
                  <?= mb_substr($p["uname"],0,1,"UTF-8") ?>
                <?php endif; ?>
              </div>
            </a>
            <div class="cm-meta">
              <strong><?= e($p["uname"]) ?></strong>
              <span><?= $p["pname"] ? e($p["pname"])." · " : "" ?><?= timeAgo($p["created_at"]) ?></span>
            </div>
            <?php if ($tab === "nearby"): ?>
              <span class="cm-dist" data-dist>...</span>
            <?php endif; ?>
            <?php if (!$isMe): ?>
              <button class="cm-follow-btn <?= $follows?'following':'' ?>"
                      onclick="toggleFollow(<?= $p["user_id"] ?>, this)">
                <?= $follows ? "ติดตามอยู่" : "ติดตาม" ?>
              </button>
            <?php endif; ?>
          </div>

          <?php if ($hot): ?>
            <span class="cm-badge">แนะนำ</span>
          <?php endif; ?>

          <?php if ($p["image_url"]): ?>
            <img src="<?= e($p["image_url"]) ?>" class="cm-img" alt="">
          <?php endif; ?>

          <div class="cm-body">
            <?php if ($p["pname"]): ?>
              <p class="cm-place">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/></svg>
                <?= e($p["pname"]) ?>
              </p>
              <?php if ($p["district"] || $p["province"]): ?>
                <p class="cm-loc">
                  <?= implode(" ", array_filter([$p["district"]?"อ.".$p["district"]:null, $p["province"]?"จ.".$p["province"]:null])) ?>
                </p>
              <?php endif; ?>
            <?php endif; ?>

            <?php if ($p["content"]): ?>
              <p class="cm-content"><?= e($p["content"]) ?></p>
            <?php endif; ?>

            <?php if ($tags): ?>
              <div class="cm-tags">
                <?php foreach ($tags as $t): ?>
                  <span class="cm-tag"><?= e($t) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="cm-actions">
            <button class="cm-action <?= $liked?'liked':'' ?>" onclick="toggleLike(<?= $p["id"] ?>, this)">
              <svg viewBox="0 0 24 24" style="fill:<?= $liked?'#ef4444':'none' ?>;stroke:<?= $liked?'#ef4444':'#64748b' ?>;stroke-width:2">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
              <span class="like-count"><?= $p["likes"] ?></span>
            </button>
            <button class="cm-action" onclick="toggleComments(<?= $p["id"] ?>, this)">
              <svg viewBox="0 0 24 24" style="fill:none;stroke:#64748b;stroke-width:2">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
              <span class="cmt-count"><?= $p["comments"] ?></span>
            </button>
            <button class="cm-action" onclick="sharePost(<?= $p["id"] ?>, '<?= e(addslashes($p["pname"] ?? "ล่าพิกัด.com")) ?>')">
              <svg viewBox="0 0 24 24" style="fill:none;stroke:#64748b;stroke-width:2">
                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
              </svg>
              แชร์
            </button>
            <button class="cm-bookmark">
              <svg viewBox="0 0 24 24" style="fill:none;stroke:#94a3b8;stroke-width:2">
                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
              </svg>
            </button>
          </div>

          <!-- Comment section -->
          <div class="cm-comments" id="cmt-<?= $p["id"] ?>" style="display:none">
            <div class="cmt-list" id="cmt-list-<?= $p["id"] ?>"></div>
            <div class="cmt-input">
              <input type="text" placeholder="เขียนความคิดเห็น..." id="cmt-txt-<?= $p["id"] ?>"
                     onkeydown="if(event.key==='Enter')addComment(<?= $p["id"] ?>)">
              <button onclick="addComment(<?= $p["id"] ?>)">
                <svg viewBox="0 0 24 24" style="width:18px;height:18px;fill:#2563eb"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
              </button>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

  </main>

  <!-- Create post modal -->
  <div class="cm-modal-bg" id="create-modal" onclick="if(event.target===this)this.classList.remove('open')">
    <div class="cm-modal">
      <h2>สร้างโพสต์ใหม่</h2>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="action" value="create_post">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

        <textarea name="content" placeholder="แบ่งปันประสบการณ์ของคุณ..."></textarea>

        <select name="place_id" style="margin-top:10px;font-size:13px">
          <option value="">-- เลือกสถานที่ (ถ้ามี) --</option>
          <?php foreach ($places as $pl): ?>
            <option value="<?= $pl["id"] ?>"><?= e($pl["name"]) ?></option>
          <?php endforeach; ?>
        </select>

        <div style="margin-top:10px">
          <label style="display:flex;align-items:center;gap:8px;background:#eff6ff;border:1.5px dashed #bfdbfe;border-radius:14px;padding:12px;cursor:pointer;font-size:13px;color:#2563eb;font-weight:600">
            <svg viewBox="0 0 24 24" style="width:20px;height:20px;fill:#2563eb">
              <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
            </svg>
            เพิ่มรูปภาพ
            <input type="file" name="post_image" accept="image/*" style="display:none" id="post-img-input">
          </label>
          <img id="post-img-preview" class="cm-photo-preview">
        </div>

        <button type="submit" style="display:block;width:100%;padding:14px;background:#2563eb;color:#fff;border:none;border-radius:999px;font-family:inherit;font-size:15px;font-weight:600;margin-top:14px;cursor:pointer">
          โพสต์
        </button>
      </form>
    </div>
  </div>

  <script>
  function switchTab(tab) {
    location.href = 'community.php?tab=' + tab;
  }

  function haversine(lat1, lng1, lat2, lng2) {
    const R = 6371;
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2)**2 +
              Math.cos(lat1*Math.PI/180) * Math.cos(lat2*Math.PI/180) * Math.sin(dLng/2)**2;
    return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }

  function setGpsStatus(type, msg) {
    const el = document.getElementById('cm-gps-status');
    if (!el) return;
    el.className = 'cm-gps-status ' + type;
    el.innerHTML = '<span class="cm-gps-dot"></span><span>' + msg + '</span>';
  }

  function sortNearby(userLat, userLng) {
    const cards  = Array.from(document.querySelectorAll('.cm-card'));
    const parent = cards[0] && cards[0].parentElement;
    if (!parent) { setGpsStatus('ok', 'ไม่มีโพสต์ที่ระบุตำแหน่งไว้'); return; }

    cards.forEach(card => {
      const dist  = haversine(userLat, userLng, parseFloat(card.dataset.lat), parseFloat(card.dataset.lng));
      card.dataset.distance = dist;
      const badge = card.querySelector('[data-dist]');
      if (badge) {
        badge.textContent = dist.toFixed(1) + ' กม.';
        badge.classList.toggle('far', dist > 50);
      }
    });

    cards.sort((a, b) => parseFloat(a.dataset.distance) - parseFloat(b.dataset.distance));
    cards.forEach(card => parent.appendChild(card));

    setGpsStatus('ok', 'ระบุตำแหน่งสำเร็จ · เรียงตามระยะทางใกล้สุด');
  }

  <?php if ($tab === "nearby"): ?>
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      pos => sortNearby(pos.coords.latitude, pos.coords.longitude),
      ()  => setGpsStatus('error', 'ไม่สามารถระบุตำแหน่งได้ — แสดงโพสต์ตามลำดับล่าสุด')
    );
  } else {
    setGpsStatus('error', 'อุปกรณ์ไม่รองรับ GPS');
  }
  <?php endif; ?>

  async function toggleLike(postId, btn) {
    const res  = await fetch('api_like.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `post_id=${postId}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    const cnt  = btn.querySelector('.like-count');
    if (data.liked) {
      btn.classList.add('liked');
      btn.querySelector('path').setAttribute('fill','#ef4444');
      btn.querySelector('path').setAttribute('stroke','#ef4444');
    } else {
      btn.classList.remove('liked');
      btn.querySelector('path').setAttribute('fill','none');
      btn.querySelector('path').setAttribute('stroke','#64748b');
    }
    cnt.textContent = data.count;
  }

  async function toggleFollow(targetId, btn) {
    const res  = await fetch('api_follow.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `target_id=${targetId}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    btn.textContent = data.following ? 'ติดตามอยู่' : 'ติดตาม';
    btn.classList.toggle('following', data.following);
  }

  async function toggleComments(postId, btn) {
    const box  = document.getElementById('cmt-' + postId);
    const list = document.getElementById('cmt-list-' + postId);
    const open = box.style.display === 'none';
    box.style.display = open ? 'block' : 'none';
    if (!open) return;
    list.innerHTML = '<div style="font-size:12px;color:#94a3b8;padding:4px 0">กำลังโหลด...</div>';
    const res  = await fetch('api_comment.php?action=get&post_id=' + postId);
    const data = await res.json();
    list.innerHTML = data.length === 0
      ? '<div style="font-size:12px;color:#94a3b8;padding:4px 0">ยังไม่มีความคิดเห็น</div>'
      : data.map(c => `
          <div class="cmt-item">
            <div class="cmt-av">${c.name.charAt(0)}</div>
            <div class="cmt-bubble">
              <strong>${c.name}</strong>
              <p>${c.content}</p>
            </div>
          </div>`).join('');
  }

  async function addComment(postId) {
    const input = document.getElementById('cmt-txt-' + postId);
    const text  = input.value.trim();
    if (!text) return;
    input.value = '';
    const res  = await fetch('api_comment.php', {
      method: 'POST',
      headers: {'Content-Type':'application/x-www-form-urlencoded'},
      body: `action=add&post_id=${postId}&content=${encodeURIComponent(text)}&csrf_token=<?= csrf_token() ?>`
    });
    const data = await res.json();
    if (data.ok) {
      const list = document.getElementById('cmt-list-' + postId);
      const noMsg = list.querySelector('div');
      if (noMsg && noMsg.style.color === 'rgb(148, 163, 184)') noMsg.remove();
      list.insertAdjacentHTML('beforeend', `
        <div class="cmt-item">
          <div class="cmt-av">${data.name.charAt(0)}</div>
          <div class="cmt-bubble">
            <strong>${data.name}</strong>
            <p>${data.content}</p>
          </div>
        </div>`);
      // Update count
      const btn = document.querySelector(`[data-post="${postId}"] .cmt-count`);
      if (btn) btn.textContent = data.count;
    }
  }

  function sharePost(postId, placeName) {
    const url   = location.origin + '/community.php';
    const text  = 'เช็คอินที่ ' + placeName + ' บน ล่าพิกัด.com';
    if (navigator.share) {
      navigator.share({ title: 'ล่าพิกัด.com', text, url })
        .catch(() => {});
    } else {
      navigator.clipboard.writeText(url).then(() => {
        alert('คัดลอกลิงก์แล้ว');
      });
    }
  }

  document.getElementById('post-img-input').addEventListener('change', e => {
    const f = e.target.files[0];
    if (!f) return;
    const prev = document.getElementById('post-img-preview');
    prev.src = URL.createObjectURL(f);
    prev.style.display = 'block';
  });
  </script>
</body>
</html>
