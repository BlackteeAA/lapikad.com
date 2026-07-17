<?php
$currentPage = basename($_SERVER["PHP_SELF"]);

function activeTab($pages) {
    global $currentPage;
    return in_array($currentPage, $pages) ? "active" : "";
}

$isLoggedIn = isset($_SESSION["user_id"]);
$userName = $_SESSION["name"] ?? "ผู้ใช้";
$userRole = $_SESSION["role"] ?? "";

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
    <?php else: ?>
      <span>ยังไม่ได้เข้าสู่ระบบ</span>
    <?php endif; ?>
  </div>
  <nav class="side-links">
    <?php if ($isLoggedIn): ?>
      <a href="dashboard.php">หน้าหลัก</a>
      <a href="places.php">สถานที่และภารกิจ</a>
      <a href="ai_quests.php">ภารกิจแนะนำ</a>
      <a href="rewards.php">รางวัล</a>
      <a href="profile.php">โปรไฟล์</a>
      <?php if ($userRole === "admin"): ?>
        <a href="admin.php">แอดมิน</a>
      <?php endif; ?>
      <?php if ($userRole === "shop"): ?>
        <a href="shop.php">ร้านค้าของฉัน</a>
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
  <a class="<?= activeTab(["community.php"]) ?>" href="community.php">
    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
    <span>ชุมชน</span>
  </a>
  <a class="<?= activeTab(["rewards.php"]) ?>" href="rewards.php">
    <svg viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
    <span>รางวัล</span>
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
