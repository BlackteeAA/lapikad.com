<?php
require_once "_auth.php";

// Visiting the chat page also counts as "having seen" the FAB notification.
setcookie("ai_chat_seen", "1", ["expires" => time() + 31536000, "path" => "/"]);

$placeId = (isset($_GET["place_id"]) && $_GET["place_id"] !== "") ? intval($_GET["place_id"]) : null;
$placeName = null;

if ($placeId !== null) {
    $stmt = $conn->prepare("SELECT name FROM places WHERE id=? AND is_active=1");
    $stmt->bind_param("i", $placeId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row) {
        $placeName = $row["name"];
    } else {
        $placeId = null;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>AI ไกด์นำเที่ยว | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }
    main.app.guide-page { padding-bottom: 190px !important; }

    .guide-hero {
      display: flex;
      align-items: center;
      gap: 14px;
      background: #fff;
      border-radius: 22px;
      padding: 18px;
      margin-bottom: 14px;
      box-shadow: 0 2px 10px rgba(15,23,42,.06);
    }
    .guide-avatar {
      width: 52px; height: 52px; flex-shrink: 0;
      border-radius: 50%;
      overflow: hidden;
      display: flex; align-items: center; justify-content: center;
    }
    .guide-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .guide-hero-text h1 { font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 3px; }
    .guide-hero-text p { font-size: 12.5px; color: #64748b; margin: 0; font-weight: 300; line-height: 1.4; }

    .guide-chips {
      display: flex; flex-wrap: wrap; gap: 8px;
      margin-bottom: 14px;
    }
    .guide-chips button, .guide-followup button {
      font-family: 'Kanit', sans-serif;
      font-size: 12.5px;
      padding: 8px 14px;
      border-radius: 999px;
      border: 1.5px solid #e2e8f0;
      background: #fff;
      color: #2563eb;
      cursor: pointer;
    }

    .guide-chat {
      display: flex;
      flex-direction: column;
      gap: 14px;
      margin-bottom: 12px;
    }

    .guide-msg { display: flex; flex-direction: column; max-width: 88%; }
    .guide-msg.user { align-self: flex-end; align-items: flex-end; }
    .guide-msg.bot { align-self: flex-start; align-items: flex-start; }

    .guide-bubble {
      font-size: 13.5px;
      line-height: 1.55;
      white-space: pre-wrap;
      padding: 12px 15px;
      border-radius: 16px;
    }
    .guide-msg.user .guide-bubble {
      background: #2563eb;
      color: #fff;
      border-radius: 16px 16px 4px 16px;
    }
    .guide-msg.bot .guide-bubble {
      background: #fff;
      color: #0f172a;
      border-radius: 16px 16px 16px 4px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
    }
    .guide-msg.typing .guide-bubble { color: #94a3b8; font-style: italic; }

    .guide-meta {
      display: flex; align-items: center; gap: 4px;
      font-size: 11px; color: #94a3b8; margin-top: 4px; padding: 0 4px;
    }
    .guide-meta svg { width: 12px; height: 12px; fill: #60a5fa; }

    .guide-cards {
      display: flex;
      flex-direction: column;
      gap: 8px;
      background: #fff;
      border-radius: 16px 16px 16px 4px;
      padding: 10px;
      box-shadow: 0 2px 8px rgba(15,23,42,.06);
      width: 100%;
    }
    .guide-card {
      display: flex;
      gap: 10px;
      align-items: center;
      text-decoration: none;
      color: inherit;
      padding: 6px;
      border-radius: 12px;
    }
    .guide-card-thumb {
      width: 52px; height: 52px; flex-shrink: 0;
      border-radius: 12px;
      background: linear-gradient(135deg, #dbeafe, #eff6ff);
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .guide-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .guide-card-thumb svg { width: 22px; height: 22px; fill: #2563eb; }
    .guide-card-body { flex: 1; min-width: 0; }
    .guide-card-body strong { display: block; font-size: 13px; font-weight: 700; color: #0f172a; }
    .guide-card-body span.desc {
      display: block; font-size: 11px; color: #64748b;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .guide-card-dist {
      font-size: 11px; font-weight: 700; color: #16a34a; white-space: nowrap; flex-shrink: 0;
    }

    .cat-badge {
      display: inline-block;
      font-size: 10px; font-weight: 600;
      padding: 2px 8px; border-radius: 99px;
      margin-bottom: 2px;
      background: #f1f5f9; color: #475569;
    }
    .cat-วัด\/ศาสนา, .cat-ชุมชน, .cat-พิพิธภัณฑ์ { background:#dcfce7; color:#15803d; }
    .cat-ธรรมชาติ, .cat-แหล่งท่องเที่ยว        { background:#dcfce7; color:#15803d; }
    .cat-ร้านค้า\/ร้านอาหาร                         { background:#fff7ed; color:#c2410c; }
    .cat-อื่นๆ                                    { background:#f1f5f9; color:#475569; }

    .guide-followup {
      display: flex; flex-wrap: wrap; gap: 8px;
      margin-bottom: 8px;
    }

    .guide-input-bar {
      position: fixed;
      left: 0; right: 0;
      bottom: 68px;
      background: #fff;
      border-top: 1px solid #f1f5f9;
      padding: 10px 14px 8px;
      z-index: 940;
    }
    .guide-input-inner {
      max-width: 430px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .guide-input-inner input {
      flex: 1;
      border: 1.5px solid #e2e8f0;
      border-radius: 999px;
      padding: 12px 18px;
      font-family: 'Kanit', sans-serif;
      font-size: 14px;
      outline: none;
    }
    .guide-send-btn {
      width: 42px; height: 42px; border-radius: 50%;
      background: #2563eb; border: none; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .guide-send-btn svg { width: 19px; height: 19px; fill: #fff; }
    .guide-send-btn:disabled { opacity: .5; cursor: default; }

    .guide-disclaimer {
      max-width: 430px;
      margin: 6px auto 0;
      text-align: center;
      font-size: 10.5px;
      color: #94a3b8;
    }
  </style>
</head>
<body>
  <main class="app guide-page" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <div class="guide-hero">
      <div class="guide-avatar">
        <img src="assets/images/nong-pikad.png" alt="น้องพิกัด">
      </div>
      <div class="guide-hero-text">
        <h1>AI ไกด์นำเที่ยว</h1>
        <p><?= $placeName ? "ถามอะไรก็ได้เกี่ยวกับ " . e($placeName) : "ถามได้ทุกเรื่องเกี่ยวกับสถานที่ท่องเที่ยวในล่าพิกัด.com" ?></p>
      </div>
    </div>

    <div class="guide-chips" id="guide-chips-initial">
      <button type="button" data-q="ใกล้ๆ นี้มีที่ไหนน่าไป">ใกล้ๆ นี้มีที่ไหนน่าไป</button>
      <?php if ($placeId !== null): ?>
        <button type="button" data-q="<?= e($placeName) ?>มีประวัติอะไร">วัดนี้มีประวัติอะไร</button>
      <?php else: ?>
        <button type="button" data-q="วัดนี้มีประวัติอะไร">วัดนี้มีประวัติอะไร</button>
      <?php endif; ?>
      <button type="button" data-q="ร้านอาหารแนะนำแถวนี้">ร้านอาหารแนะนำแถวนี้</button>
    </div>

    <div class="guide-chat" id="guide-chat">
      <div class="guide-msg bot">
        <div class="guide-bubble">สวัสดีครับ! ผมน้องพิกัด ไกด์นำเที่ยว AI ของล่าพิกัด.com ถามอะไรก็ได้เกี่ยวกับสถานที่ท่องเที่ยวเลยครับ</div>
      </div>
    </div>

    <div class="guide-followup" id="guide-followup" style="display:none">
      <button type="button" data-q="แนะนำร้านอาหารใกล้ๆ">แนะนำร้านอาหารใกล้ๆ</button>
      <button type="button" data-q="จุดชมวิวสวยๆ">จุดชมวิวสวยๆ</button>
      <button type="button" data-q="วัดดังในจังหวัด">วัดดังในจังหวัด</button>
    </div>
  </main>

  <div class="guide-input-bar">
    <div class="guide-input-inner">
      <input type="text" id="guide-input" placeholder="พิมพ์คำถามอะไรก็ได้..." maxlength="500" autocomplete="off">
      <button class="guide-send-btn" id="guide-send" aria-label="ส่งข้อความ">
        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
      </button>
    </div>
    <p class="guide-disclaimer">AI อาจตอบไม่ถูกต้อง 100% โปรดตรวจสอบข้อมูลอีกครั้ง</p>
  </div>

  <script>
  (function () {
    const PLACE_ID = <?= $placeId !== null ? intval($placeId) : "null" ?>;
    const CSRF_TOKEN = <?= json_encode(csrf_token()) ?>;

    const chat = document.getElementById('guide-chat');
    const input = document.getElementById('guide-input');
    const sendBtn = document.getElementById('guide-send');
    const initialChips = document.getElementById('guide-chips-initial');
    const followupChips = document.getElementById('guide-followup');

    let userLat, userLng;
    let busy = false;

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(pos => {
        userLat = pos.coords.latitude;
        userLng = pos.coords.longitude;
      }, () => {}, { timeout: 8000, maximumAge: 60000 });
    }

    function timeNow() {
      return new Date().toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
    }

    function catClass(cat) {
      return cat ? 'cat-' + cat : '';
    }

    function esc(s) {
      const d = document.createElement('div');
      d.textContent = s;
      return d.innerHTML;
    }

    function placeCardHtml(p) {
      const thumb = p.image_url
        ? `<img src="${esc(p.image_url)}" alt="${esc(p.name)}">`
        : `<svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>`;
      return `
        <a href="place.php?id=${p.id}" class="guide-card">
          <div class="guide-card-thumb">${thumb}</div>
          <div class="guide-card-body">
            ${p.category ? `<span class="cat-badge ${catClass(p.category)}">${esc(p.category)}</span>` : ''}
            <strong>${esc(p.name)}</strong>
            ${p.description ? `<span class="desc">${esc(p.description)}</span>` : ''}
          </div>
          <span class="guide-card-dist">${p.distance_km} กม.</span>
        </a>`;
    }

    function addBotMessage(reply, places) {
      const wrap = document.createElement('div');
      wrap.className = 'guide-msg bot';

      const bubble = document.createElement('div');
      bubble.className = 'guide-bubble';
      bubble.textContent = reply || 'ขออภัยครับ ไม่มีคำตอบในตอนนี้';
      wrap.appendChild(bubble);

      if (places && places.length) {
        const cards = document.createElement('div');
        cards.className = 'guide-cards';
        cards.innerHTML = places.map(placeCardHtml).join('');
        wrap.appendChild(cards);

        const followupBubble = document.createElement('div');
        followupBubble.className = 'guide-bubble';
        followupBubble.style.marginTop = '8px';
        followupBubble.textContent = 'อยากให้แนะนำแบบไหนอีกไหมครับ? เช่น วัด คาเฟ่ ธรรมชาติ หรือร้านอาหาร';
        wrap.appendChild(followupBubble);

        followupChips.style.display = 'flex';
      }

      const meta = document.createElement('div');
      meta.className = 'guide-meta';
      meta.textContent = timeNow();
      wrap.appendChild(meta);

      chat.appendChild(wrap);
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }

    function addUserMessage(text) {
      const wrap = document.createElement('div');
      wrap.className = 'guide-msg user';
      wrap.innerHTML = `<div class="guide-bubble"></div><div class="guide-meta">${timeNow()} ✓</div>`;
      wrap.querySelector('.guide-bubble').textContent = text;
      chat.appendChild(wrap);
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
    }

    function addTyping() {
      const wrap = document.createElement('div');
      wrap.className = 'guide-msg bot typing';
      wrap.innerHTML = '<div class="guide-bubble">น้องพิกัดกำลังพิมพ์...</div>';
      chat.appendChild(wrap);
      window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
      return wrap;
    }

    function setBusy(state) {
      busy = state;
      sendBtn.disabled = state;
      input.disabled = state;
    }

    async function sendMessage(text) {
      text = (text || '').trim();
      if (!text || busy) return;

      initialChips.style.display = 'none';
      followupChips.style.display = 'none';
      addUserMessage(text);
      input.value = '';
      setBusy(true);

      const typing = addTyping();

      try {
        const params = { csrf_token: CSRF_TOKEN, message: text };
        if (PLACE_ID !== null) params.place_id = PLACE_ID;
        if (userLat !== undefined && userLng !== undefined) {
          params.lat = userLat;
          params.lng = userLng;
        }

        const res = await fetch('chat.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams(params),
        });
        const data = await res.json();
        typing.remove();
        addBotMessage(data.reply, data.places);
      } catch (err) {
        typing.remove();
        addBotMessage('เชื่อมต่อไม่สำเร็จ กรุณาลองใหม่อีกครั้ง', []);
      } finally {
        setBusy(false);
        input.focus();
      }
    }

    sendBtn.addEventListener('click', () => sendMessage(input.value));
    input.addEventListener('keydown', e => {
      if (e.key === 'Enter') sendMessage(input.value);
    });
    document.querySelectorAll('#guide-chips-initial button, #guide-followup button').forEach(btn => {
      btn.addEventListener('click', () => sendMessage(btn.dataset.q));
    });
  })();
  </script>
</body>
</html>
