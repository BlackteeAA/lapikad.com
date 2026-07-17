<?php
require_once "_auth.php";

$questId = intval($_GET["id"] ?? 0);

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name
    FROM quests q
    JOIN places p ON p.id = q.place_id
    WHERE q.id = ?
");
$stmt->bind_param("i", $questId);
$stmt->execute();
$quest = $stmt->get_result()->fetch_assoc();

if (!$quest) redirect("places.php");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
  <title>สแกน QR | ล่าพิกัด.com</title>
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      background: #000;
      color: #fff;
      font-family: -apple-system, 'Kanit', sans-serif;
      overflow: hidden;
      height: 100vh;
    }

    #reader {
      position: fixed;
      inset: 0;
      width: 100vw !important;
      height: 100vh !important;
      touch-action: none;
    }

    #reader video {
      width: 100vw !important;
      height: 100vh !important;
      object-fit: cover !important;
    }

    /* Hide all Html5Qrcode UI */
    #reader > div, #reader img, #reader a,
    #reader select, #reader button, #reader span {
      display: none !important;
    }

    /* ── Top bar ── */
    .sc-top {
      position: fixed;
      top: 0;
      left: 0; right: 0;
      top: env(safe-area-inset-top, 0);
      z-index: 100;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 16px;
    }

    .sc-back {
      display: flex;
      align-items: center;
      gap: 6px;
      color: #fff;
      text-decoration: none;
      font-size: 16px;
      font-weight: 500;
      background: rgba(0,0,0,.35);
      border-radius: 999px;
      padding: 8px 14px;
    }

    .sc-back svg { width:18px;height:18px;fill:#fff; }

    .sc-torch {
      width: 42px; height: 42px;
      background: rgba(0,0,0,.35);
      border-radius: 50%;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
    }

    .sc-torch svg { width:22px;height:22px;fill:#fff; }
    .sc-torch.on { background: #fbbf24; }
    .sc-torch.on svg { fill: #000; }

    /* ── Overlay with hole ── */
    .sc-overlay {
      position: fixed;
      inset: 0;
      z-index: 50;
      pointer-events: none;
    }

    /* ── Scan box ── */
    .sc-box {
      position: fixed;
      z-index: 60;
      left: 50%; top: 50%;
      transform: translate(-50%, -50%);
      width: 260px;
      height: 260px;
      pointer-events: none;
    }

    /* Dark overlay around box */
    .sc-box::before {
      content: '';
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.52);
      -webkit-mask:
        linear-gradient(#000 0 0) top/100% calc(50% - 130px),
        linear-gradient(#000 0 0) bottom/100% calc(50% - 130px),
        linear-gradient(#000 0 0) left/calc(50% - 130px) 100%,
        linear-gradient(#000 0 0) right/calc(50% - 130px) 100%;
      -webkit-mask-repeat: no-repeat;
      mask:
        linear-gradient(#000 0 0) top/100% calc(50% - 130px),
        linear-gradient(#000 0 0) bottom/100% calc(50% - 130px),
        linear-gradient(#000 0 0) left/calc(50% - 130px) 100%,
        linear-gradient(#000 0 0) right/calc(50% - 130px) 100%;
      mask-repeat: no-repeat;
    }

    /* Corner brackets */
    .corner {
      position: absolute;
      width: 44px;
      height: 44px;
      border-color: #fff;
      border-style: solid;
    }
    .c-tl { top:0;left:0;  border-width:3px 0 0 3px; border-radius:6px 0 0 0; }
    .c-tr { top:0;right:0; border-width:3px 3px 0 0; border-radius:0 6px 0 0; }
    .c-bl { bottom:0;left:0;  border-width:0 0 3px 3px; border-radius:0 0 0 6px; }
    .c-br { bottom:0;right:0; border-width:0 3px 3px 0; border-radius:0 0 6px 0; }

    /* Scan line — full height, fixed center vertical */
    .sc-line {
      position: fixed;
      top: 0; bottom: 0;
      left: 50%;
      width: 2px;
      background: #ef4444;
      box-shadow: 0 0 6px #ef4444, 0 0 12px rgba(239,68,68,.4);
      z-index: 70;
      pointer-events: none;
    }

    /* ── Bottom bar ── */
    .sc-bottom {
      position: fixed;
      bottom: 0;
      left: 0; right: 0;
      bottom: env(safe-area-inset-bottom, 0);
      z-index: 100;
      padding: 20px 24px 30px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .sc-hint {
      flex: 1;
      text-align: center;
      font-size: 14px;
      color: rgba(255,255,255,.85);
      line-height: 1.4;
    }

    .sc-gallery {
      width: 46px; height: 46px;
      background: rgba(255,255,255,.18);
      border-radius: 14px;
      border: none;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
    }

    .sc-gallery svg { width:24px;height:24px;fill:#fff; }

    /* Status toast */
    #sc-status {
      position: fixed;
      bottom: calc(110px + env(safe-area-inset-bottom, 0));
      left: 50%;
      transform: translateX(-50%);
      z-index: 200;
      background: rgba(255,255,255,.95);
      color: #111;
      font-size: 14px;
      font-weight: 600;
      padding: 10px 20px;
      border-radius: 999px;
      white-space: nowrap;
      display: none;
    }

    #sc-status.show { display: block; }
    #sc-status.error { background: #dc2626; color: #fff; }

    #gallery-input { display: none; }
  </style>
</head>
<body>

  <div id="reader"></div>

  <!-- Scan line full width -->
  <div class="sc-line"></div>

  <!-- Scan box corners -->
  <div class="sc-box">
    <div class="corner c-tl"></div>
    <div class="corner c-tr"></div>
    <div class="corner c-bl"></div>
    <div class="corner c-br"></div>
  </div>

  <!-- Top bar -->
  <div class="sc-top">
    <a href="quest.php?id=<?= e($questId) ?>" class="sc-back">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      ย้อนกลับ
    </a>
    <button class="sc-torch" id="torch-btn" onclick="toggleTorch()" style="display:none">
      <svg viewBox="0 0 24 24"><path d="M7 2v11h3v9l7-12h-4l4-8z"/></svg>
    </button>
  </div>

  <!-- Bottom bar -->
  <div class="sc-bottom">
    <div style="width:46px"></div>
    <div class="sc-hint">ให้ตำแหน่ง QR Code อยู่ตรงกลางภาพ</div>
    <button class="sc-gallery" onclick="document.getElementById('gallery-input').click()">
      <svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>
    </button>
  </div>

  <div id="sc-status"></div>

  <form id="scan-form" method="post" action="complete_quest.php" style="display:none">
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
    <input type="hidden" name="quest_id"   value="<?= e($questId) ?>">
    <input type="hidden" name="scanned_code" id="scanned_code">
  </form>

  <input type="file" id="gallery-input" accept="image/*">

  <script>
  const form      = document.getElementById('scan-form');
  const codeInput = document.getElementById('scanned_code');
  const status    = document.getElementById('sc-status');
  let locked = false;
  let activeTrack = null;
  let torchOn = false;
  let minZoom = 1, maxZoom = 1, currentZoom = 1;

  function showStatus(msg, isError = false) {
    status.textContent = msg;
    status.className = 'show' + (isError ? ' error' : '');
  }

  function submitCode(raw) {
    if (locked) return;
    locked = true;
    if (navigator.vibrate) navigator.vibrate([100, 50, 100]);
    showStatus('สแกนสำเร็จ กำลังตรวจสอบ...');
    codeInput.value = raw;
    setTimeout(() => form.submit(), 300);
  }

  function toggleTorch() {
    if (!activeTrack) return;
    torchOn = !torchOn;
    const btn = document.getElementById('torch-btn');
    activeTrack.applyConstraints({ advanced: [{ torch: torchOn }] })
      .then(() => btn.classList.toggle('on', torchOn))
      .catch(() => { torchOn = false; btn.classList.remove('on'); });
  }

  // Pinch-to-zoom on the camera view
  function setupPinchZoom() {
    const reader = document.getElementById('reader');
    let startDist = 0;
    let startZoom = minZoom;

    function dist(touches) {
      const [a, b] = touches;
      return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY);
    }

    reader.addEventListener('touchstart', e => {
      if (e.touches.length === 2) {
        startDist = dist(e.touches);
        startZoom = currentZoom;
      }
    }, { passive: true });

    reader.addEventListener('touchmove', e => {
      if (e.touches.length === 2 && startDist > 0) {
        e.preventDefault();
        const scale = dist(e.touches) / startDist;
        let zoom = startZoom * scale;
        zoom = Math.min(maxZoom, Math.max(minZoom, zoom));
        currentZoom = zoom;
        activeTrack.applyConstraints({ advanced: [{ zoom }] }).catch(() => {});
      }
    }, { passive: false });

    reader.addEventListener('touchend', e => {
      if (e.touches.length < 2) startDist = 0;
    }, { passive: true });
  }

  // Gallery scan
  document.getElementById('gallery-input').addEventListener('change', async e => {
    const file = e.target.files[0];
    if (!file) return;
    showStatus('กำลังสแกนรูป...');
    try {
      const scanner = new Html5Qrcode('reader');
      const result  = await scanner.scanFile(file, false);
      submitCode(result);
    } catch (err) {
      showStatus('ไม่พบ QR Code ในรูปนี้', true);
      setTimeout(() => status.className = '', 2000);
    }
  });

  // Native BarcodeDetector
  let nativeDetector = null;
  if ('BarcodeDetector' in window) {
    BarcodeDetector.getSupportedFormats().then(formats => {
      if (formats.includes('qr_code'))
        nativeDetector = new BarcodeDetector({ formats: ['qr_code'] });
    }).catch(() => {});
  }

  function startNative(video) {
    if (!nativeDetector) return;
    const tick = async () => {
      if (locked) return;
      if (video.readyState === 4) {
        try {
          const codes = await nativeDetector.detect(video);
          if (codes.length) { submitCode(codes[0].rawValue); return; }
        } catch (e) {}
      }
      requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  // Start camera
  const html5QrCode = new Html5Qrcode('reader');

  // iPad (including iPadOS 13+ which reports as "Macintosh") is typically
  // mounted as a stationary kiosk, so the customer presents their QR code
  // to the front camera instead of holding the iPad up to scan like a phone.
  const isIPad = /iPad/.test(navigator.userAgent) ||
    (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  function afterStart() {
    // Get track for torch/zoom
    setTimeout(() => {
      const video = document.querySelector('#reader video');
      if (!video || !video.srcObject) return;
      const tracks = video.srcObject.getVideoTracks();
      if (!tracks.length) return;
      activeTrack = tracks[0];

      // Show torch if supported
      const caps = activeTrack.getCapabilities ? activeTrack.getCapabilities() : {};
      if (caps.torch) document.getElementById('torch-btn').style.display = 'flex';

      // Pin zoom back to the widest view in case the device ignored the
      // initial zoom constraint, and enable pinch-to-zoom for manual control.
      if (caps.zoom) {
        minZoom = caps.zoom.min;
        maxZoom = caps.zoom.max;
        currentZoom = minZoom;
        activeTrack.applyConstraints({ advanced: [{ zoom: minZoom }] }).catch(() => {});
        setupPinchZoom();
      }

      // Start native scanner
      if (nativeDetector) startNative(video);
    }, 1000);
  }

  async function start() {
    // Prefer facingMode over an enumerated deviceId. On iPhones with more
    // than one rear lens, selecting a camera by deviceId opens iOS's virtual
    // "Back Camera" (which blends wide/ultra-wide/telephoto), and that
    // virtual device can default its baseline to a zoomed-in crop no matter
    // what zoom constraint we pass. facingMode binds directly to the
    // physical wide-angle lens and avoids that.
    const facingCfg = {
      fps: 30,
      qrbox: () => ({ width: 260, height: 260 }),
      disableFlip: false,
      videoConstraints: {
        facingMode: { exact: isIPad ? 'user' : 'environment' },
        zoom: 1,
        focusMode: 'continuous'
      }
    };

    try {
      await html5QrCode.start({ facingMode: { exact: isIPad ? 'user' : 'environment' } }, facingCfg, submitCode, () => {});
      afterStart();
      return;
    } catch (e) {}

    // Fallback for browsers without facingMode support (e.g. desktop/webcams).
    try {
      const cams = await Html5Qrcode.getCameras();
      if (!cams || !cams.length) { showStatus('ไม่พบกล้อง', true); return; }

      const cam = isIPad
        ? (cams.find(c => (c.label || '').toLowerCase().includes('front')) || cams[0])
        : (cams.find(c => {
            const l = (c.label || '').toLowerCase();
            return l.includes('back') || l.includes('rear') || l.includes('environment');
          }) || cams[cams.length - 1]);

      const cfg = {
        fps: 30,
        qrbox: () => ({ width: 260, height: 260 }),
        disableFlip: false,
        videoConstraints: {
          deviceId: { exact: cam.id },
          zoom: 1,
          focusMode: 'continuous'
        }
      };

      await html5QrCode.start(cam.id, cfg, submitCode, () => {});
      afterStart();
    } catch (e2) {
      showStatus('เปิดกล้องไม่ได้ — ตรวจสอบสิทธิ์กล้อง', true);
    }
  }

  start();
  </script>
</body>
</html>
