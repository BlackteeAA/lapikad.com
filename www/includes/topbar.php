<?php
$currentPage = basename($_SERVER["PHP_SELF"]);

function activeTab($pages) {
    global $currentPage;
    return in_array($currentPage, $pages) ? "active" : "";
}

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["name"] ?? "ผู้ใช้";
$userRole = $_SESSION["role"] ?? "";

// School role ("สภานักเรียน"/"ครู") of the logged-in user, for the side menu.
$navIsCouncil = false;
$navSchool = null;
if ($isLoggedIn && isset($conn)) {
    require_once __DIR__ . "/../_school.php";
    $navSchool = getUserSchool($conn, $_SESSION["user_id"]);
    $navIsCouncil = isSchoolCouncil($conn, $_SESSION["user_id"]);
}

// Students don't see the school menu: they join school activities through the
// council's check-in links / QR (event_link.php). Admins, council/teachers and
// event scan helpers still get it.
$navShowSchool = false;
if ($isLoggedIn && isset($conn)) {
    $navShowSchool = $userRole === "admin" || $navIsCouncil;
    if (!$navShowSchool) {
        $nsStmt = $conn->prepare("SELECT 1 FROM event_staff WHERE user_id=? LIMIT 1");
        $nsUid = intval($_SESSION["user_id"]);
        $nsStmt->bind_param("i", $nsUid);
        $nsStmt->execute();
        $navShowSchool = (bool)$nsStmt->get_result()->fetch_assoc();
    }
}

// Global "น้องพิกัด" AI chat entry point — hidden on the chat page itself.
$aiGuideLink = "ai_guide.php";
if ($currentPage === "place.php" && !empty($_GET["id"])) {
    $aiGuideLink .= "?place_id=" . intval($_GET["id"]);
}
$showAiChatFab = $isLoggedIn && $currentPage !== "ai_guide.php";

$notifications = [];
if ($isLoggedIn && isset($conn)) {
    $notifId = intval($_SESSION["user_id"]);
    $ns = $conn->prepare("
        SELECT uq.completed_at, q.title, q.reward_points
        FROM user_quests uq
        JOIN quests q ON q.id = uq.quest_id
        WHERE uq.user_id = ?
        ORDER BY uq.completed_at DESC
        LIMIT 10
    ");
    $ns->bind_param("i", $notifId);
    $ns->execute();
    $notifications = $ns->get_result()->fetch_all(MYSQLI_ASSOC);
}

function notifTimeAgo($dt) {
    $d = time() - strtotime($dt);
    if ($d < 60) return "เมื่อกี้";
    if ($d < 3600) return intval($d/60) . " นาทีที่แล้ว";
    if ($d < 86400) return intval($d/3600) . " ชั่วโมงที่แล้ว";
    return intval($d/86400) . " วันที่แล้ว";
}
?>

<link rel="stylesheet" href="css/soft.css?v=<?= @filemtime(__DIR__ . "/../css/soft.css") ?: 1 ?>">

<style>
  /* Side menu fix (inline เพื่อไม่ติดแคช modern.css) */
  .menu-backdrop { z-index: 1090 !important; }
  .side-menu {
    z-index: 1100 !important;
    height: 100vh;
    height: 100dvh;
    max-height: 100dvh;
    overflow-y: auto !important;
    overscroll-behavior: contain;
    -webkit-overflow-scrolling: touch;
    touch-action: pan-y;
    padding-bottom: calc(32px + env(safe-area-inset-bottom)) !important;
    background: #ffffff !important;
  }
  /* ซ่อน bottom nav / ปุ่ม AI ตอนเปิดเมนู และล็อกหน้าหลักไม่ให้เลื่อน */
  .menu-toggle:checked ~ .bottom-nav,
  .menu-toggle:checked ~ .ai-chat-fab { display: none !important; }
  html:has(#menu-toggle:checked),
  body:has(#menu-toggle:checked) { overflow: hidden !important; }

  /* Bottom nav แบบลอย (แคปซูล) */
  .bottom-nav {
    left: 50% !important;
    right: auto !important;
    bottom: calc(14px + env(safe-area-inset-bottom, 0px)) !important;
    transform: translateX(-50%) !important;
    width: calc(100% - 28px) !important;
    max-width: 430px !important;
    height: 64px !important;
    padding: 0 10px !important;
    border-radius: 999px !important;
    background: rgba(255, 255, 255, .96) !important;
    border: 1px solid rgba(15, 23, 42, .06);
    box-shadow: 0 12px 32px rgba(15, 23, 42, .18), 0 2px 6px rgba(15, 23, 42, .06) !important;
    overflow: visible !important;
  }
  .bottom-nav a {
    height: 46px !important;
    border-radius: 999px !important;
  }
  /* ไอคอนอย่างเดียว ซ่อนข้อความ (ยังอ่านได้ด้วย screen reader) */
  .bottom-nav a > span:not(.nav-scan-btn) {
    position: absolute; width: 1px; height: 1px; overflow: hidden;
    clip: rect(0 0 0 0); white-space: nowrap;
  }
  .bottom-nav a:not(.nav-scan) { flex: 0 0 46px !important; width: 46px; margin: 0 auto; }
  .bottom-nav a.active:not(.nav-scan) { background: #eff6ff !important; color: #2563eb !important; }
  .app { padding-bottom: calc(110px + env(safe-area-inset-bottom, 0px)) !important; }
  .ai-chat-fab { bottom: calc(96px + env(safe-area-inset-bottom, 0px)) !important; }

  /* ปุ่มกล้องตรงกลาง (ยื่นขึ้นเหนือแถบ) */
  .bottom-nav a.nav-scan,
  .bottom-nav a.nav-scan.active {
    position: relative;
    flex: 0 0 64px !important;
    margin: 0 auto;
    background: transparent !important;
  }
  .nav-scan-btn {
    position: absolute;
    top: 50%;
    margin-top: -48px;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    border: 5px solid #fff;
    transition: transform .15s ease;
  }
  .bottom-nav .nav-scan-btn svg { width: 26px; height: 26px; fill: #fff; }
  .nav-scan:active .nav-scan-btn {
    transform: translateX(-50%) scale(.92);
  }
</style>

<input type="checkbox" id="menu-toggle" class="menu-toggle">
<input type="checkbox" id="notif-toggle" class="notif-toggle">

<header class="mobile-topbar">
  <div class="topbar-left">
    <label for="menu-toggle" class="hamburger-btn">
      <span></span><span></span><span></span>
    </label>
    <a href="<?= $isLoggedIn ? 'dashboard.php' : 'index.php' ?>" class="mobile-brand">
      <img src="assets/images/logo.png" alt="ล่าพิกัด.com" style="height:36px;width:auto;object-fit:contain;">
    </a>
  </div>

  <div class="topbar-right">
    <?php if ($isLoggedIn): ?>
    <div class="notif-wrap">
      <label for="notif-toggle" class="notif-btn" id="notif-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="notif-badge" id="notif-badge" style="display:none">0</span>
      </label>

      <div class="notif-panel" id="notif-panel">
        <div class="notif-header">
          <strong>การแจ้งเตือน</strong>
          <button onclick="markAllRead()" class="notif-read-btn">อ่านทั้งหมด</button>
        </div>
        <div class="notif-list">
          <?php if (empty($notifications)): ?>
            <div class="notif-empty">ยังไม่มีกิจกรรม</div>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <div class="notif-item" data-time="<?= strtotime($n['completed_at']) ?>">
                <div class="notif-dot"></div>
                <div class="notif-body">
                  <span>คุณสแกน "<strong><?= e($n['title']) ?></strong>" สำเร็จ</span>
                  <small>ได้รับ +<?= $n['reward_points'] ?> คะแนน · <?= notifTimeAgo($n['completed_at']) ?></small>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <a href="profile.php" class="top-profile" id="top-profile-btn">
      <?php
      $topAvatarUrl = null;
      if ($isLoggedIn && isset($conn)) {
          $topUid = intval($_SESSION["user_id"]);
          $topR   = $conn->query("SELECT avatar_url FROM users WHERE id=$topUid");
          if ($topR) { $r = $topR->fetch_assoc(); $topAvatarUrl = $r["avatar_url"] ?? null; }
      }
      if ($topAvatarUrl): ?>
        <img src="<?= e($topAvatarUrl) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:50%">
      <?php else: ?>
        <?= e(mb_substr($userName, 0, 1, "UTF-8")) ?>
      <?php endif; ?>
    </a>
  </div>
</header>

<label for="notif-toggle" class="notif-backdrop"></label>

<aside class="side-menu">
  <label for="menu-toggle" class="side-close">ปิด</label>
  <div class="side-profile">
    <div class="side-avatar"><?= e(mb_substr($userName, 0, 1, "UTF-8")) ?></div>
    <strong><?= e($userName) ?></strong>
    <?php if ($isLoggedIn): ?>
      <span><?= e($userRole) ?></span>
      <?php if (!empty($navSchool["school_id"])): ?>
        <span style="display:flex;align-items:center;gap:6px;font-size:12.5px;margin-top:6px">
          <?= schoolLogoHtml(["name" => $navSchool["school_name"], "logo_url" => $navSchool["school_logo"]], 22) ?>
          <?= e($navSchool["school_name"]) ?> · <?= e(schoolRoleLabel($navSchool["school_role"])) ?>
        </span>
      <?php endif; ?>
    <?php else: ?>
      <span>ยังไม่ได้เข้าสู่ระบบ</span>
    <?php endif; ?>
  </div>
  <nav class="side-links">
    <?php if ($isLoggedIn): ?>
      <a href="dashboard.php">หน้าหลัก</a>
      <a href="places.php">สถานที่และภารกิจ</a>
      <a href="ai_quests.php">ภารกิจแนะนำ</a>
      <?php if ($navShowSchool): ?>
        <a href="events.php">กิจกรรมโรงเรียน</a>
      <?php endif; ?>
      <a href="my_points.php">แต้มร้านค้าของฉัน / QR ของฉัน</a>
      <a href="profile.php">โปรไฟล์</a>
      <?php if ($userRole === "admin"): ?>
        <a href="admin.php">แอดมิน</a>
        <a href="admin_users.php">จัดการผู้ใช้</a>
        <a href="admin_schools.php">โรงเรียนทั้งหมด (สมาชิก · รางวัล)</a>
        <a href="event_manage.php">จัดการกิจกรรม</a>
        <?php if (!empty($navSchool["school_id"])): ?>
          <a href="school_members.php?id=<?= intval($navSchool["school_id"]) ?>">สมาชิก <?= e($navSchool["school_name"]) ?></a>
          <a href="council_rewards.php?school_id=<?= intval($navSchool["school_id"]) ?>">รางวัล <?= e($navSchool["school_name"]) ?></a>
        <?php endif; ?>
      <?php endif; ?>
      <?php if ($userRole === "shop"): ?>
        <a href="shop.php">ร้านค้าของฉัน</a>
        <a href="shop_deduct_scan.php">สแกนหักคะแนนลูกค้า</a>
        <a href="shop_qr.php">QR เช็คอินของร้าน</a>
        <a href="shop_rewards.php">รางวัลของร้าน</a>
      <?php endif; ?>
      <?php if ($userRole !== "admin" && $navIsCouncil): ?>
        <a href="council_rewards.php">รางวัลโรงเรียน (<?= $navSchool["school_role"] === "teacher" ? "ครู" : "สภา" ?>)</a>
        <a href="school_members.php?id=<?= intval($navSchool["school_id"]) ?>">สมาชิกโรงเรียน</a>
      <?php endif; ?>
      <a href="logout.php" class="danger-link">ออกจากระบบ</a>
    <?php else: ?>
      <a href="login.php">เข้าสู่ระบบ</a>
      <a href="register.php">สมัครสมาชิก</a>
    <?php endif; ?>
  </nav>
</aside>

<label for="menu-toggle" class="menu-backdrop"></label>

<nav class="bottom-nav">
  <a class="<?= activeTab(["dashboard.php"]) ?>" href="dashboard.php">
    <svg viewBox="0 0 24 24"><path d="M3 10.5L12 3l9 7.5V21a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1V10.5z"/></svg>
    <span>หน้าหลัก</span>
  </a>
  <a class="<?= activeTab(["places.php","place.php","quest.php","scan.php","qr_claim.php","complete_quest.php"]) ?>" href="places.php">
    <svg viewBox="0 0 24 24"><path d="M9 3h12v12H9V3zm2 2v8h8V5h-8zM3 9h4v12H3V9zm2 2v8h0v-8zm4 6h12v4H9v-4zm2 2h8v0h-8z"/></svg>
    <span>ภารกิจ</span>
  </a>
  <a class="nav-scan <?= activeTab(["scan_any.php"]) ?>" href="<?= $isLoggedIn ? 'scan_any.php' : 'login.php' ?>" aria-label="สแกน QR">
    <span class="nav-scan-btn">
      <svg viewBox="0 0 24 24"><path d="M9 3 7.17 5H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-3.17L15 3H9zm3 15a5 5 0 1 1 0-10 5 5 0 0 1 0 10zm0-2a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/></svg>
    </span>
    <span>สแกน</span>
  </a>
  <a class="<?= activeTab(["community.php"]) ?>" href="community.php">
    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
    <span>ชุมชน</span>
  </a>
  <a class="<?= activeTab(["profile.php","login.php","register.php"]) ?>" href="profile.php">
    <svg viewBox="0 0 24 24"><path d="M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5zm0 2c-5 0-9 2.5-9 5.5V22h18v-2.5C21 16.5 17 14 12 14z"/></svg>
    <span>ฉัน</span>
  </a>
</nav>

<?php if ($showAiChatFab): ?>
<style>
  .ai-chat-fab {
    position: fixed;
    right: 16px;
    bottom: 86px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 8px 24px rgba(37,99,235,.4);
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    overflow: hidden;
    z-index: 950;
  }
  .ai-chat-fab img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .ai-chat-badge {
    position: absolute;
    top: -2px; right: -2px;
    width: 15px; height: 15px;
    border-radius: 50%;
    background: #ef4444;
    border: 2px solid #fff;
    animation: aiChatPulse 1.6s infinite;
  }
  .ai-chat-badge.hide { display: none; }
  @keyframes aiChatPulse {
    0%   { box-shadow: 0 0 0 0 rgba(239,68,68,.55); }
    70%  { box-shadow: 0 0 0 8px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
  }
</style>
<a href="<?= e($aiGuideLink) ?>" class="ai-chat-fab" id="ai-chat-fab" aria-label="แชทกับน้องพิกัด AI ไกด์นำเที่ยว">
  <img src="assets/images/nong-pikad.png" alt="น้องพิกัด">
  <span class="ai-chat-badge<?= isset($_COOKIE["ai_chat_seen"]) ? " hide" : "" ?>" id="ai-chat-badge"></span>
</a>
<script>
  document.getElementById('ai-chat-fab').addEventListener('click', function () {
    document.cookie = "ai_chat_seen=1; max-age=31536000; path=/";
  });
</script>
<?php endif; ?>

<script>
(function(){
  const badge = document.getElementById('notif-badge');
  const items = document.querySelectorAll('.notif-item');
  if (!badge || !items.length) return;

  // toggle notification panel
  const btn = document.getElementById('notif-btn');
  const panel = document.getElementById('notif-panel');
  if (btn && panel) {
    btn.addEventListener('click', e => {
      e.stopPropagation();
      panel.classList.toggle('open');
    });
    document.addEventListener('click', () => panel.classList.remove('open'));
    panel.addEventListener('click', e => e.stopPropagation());
  }

  const lastRead = parseInt(localStorage.getItem('notif_last_read') || '0');
  let unread = 0;

  items.forEach(el => {
    const t = parseInt(el.dataset.time || '0');
    if (t > lastRead) {
      unread++;
      el.classList.add('unread');
    }
  });

  if (unread > 0) {
    badge.textContent = unread;
    badge.style.display = 'flex';
  }
})();

function markAllRead() {
  localStorage.setItem('notif_last_read', Math.floor(Date.now() / 1000).toString());
  const badge = document.getElementById('notif-badge');
  if (badge) badge.style.display = 'none';
  document.querySelectorAll('.notif-item.unread').forEach(el => el.classList.remove('unread'));
  document.getElementById('notif-toggle').checked = false;
}
</script>
