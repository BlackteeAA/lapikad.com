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

if (!$quest) {
    redirect("places.php");
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>สแกน QR | ล่าพิกัด.com</title>

  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">

  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

  <style>
    .camera-body {
      margin: 0;
      background: #000;
      color: #fff;
      overflow: hidden;
    }

    .camera-page {
      position: fixed;
      inset: 0;
      background: #000;
      overflow: hidden;
    }

    .camera-top {
      position: fixed;
      top: env(safe-area-inset-top);
      left: 0;
      right: 0;
      z-index: 10000;
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px;
      background: linear-gradient(to bottom, rgba(0,0,0,.75), rgba(0,0,0,0));
    }

    .camera-top a {
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255,255,255,.18);
      color: #fff;
      text-decoration: none;
      font-size: 15px;
      font-weight: 600;
    }

    .camera-top strong {
      display: block;
      font-size: 22px;
      line-height: 1.1;
    }

    .camera-top span {
      display: block;
      margin-top: 6px;
      color: rgba(255,255,255,.75);
      font-size: 15px;
    }

    .camera-reader {
      width: 100vw;
      height: 100vh;
      background: #000;
    }

    .camera-reader video {
      width: 100vw !important;
      height: 100vh !important;
      object-fit: cover !important;
    }

    .camera-reader > div {
      border: 0 !important;
      width: 100vw !important;
      height: 100vh !important;
    }

    .camera-reader img,
    .camera-reader a,
    .camera-reader select,
    .camera-reader button,
    .camera-reader span {
      display: none !important;
    }

    .qr-frame {
      position: fixed;
      left: 50%;
      top: 50%;
      z-index: 9999;
      width: 260px;
      height: 260px;
      transform: translate(-50%, -50%);
      border-radius: 24px;
      box-shadow:
        0 0 0 9999px rgba(0,0,0,.42),
        0 0 28px rgba(37,99,235,.6);
      pointer-events: none;
    }

    .qr-frame::after {
      content: "";
      position: absolute;
      left: 22px;
      right: 22px;
      top: 50%;
      height: 3px;
      background: #22c55e;
      box-shadow: 0 0 14px #22c55e;
      animation: scanLine 1.7s infinite ease-in-out;
    }

    .corner {
      position: absolute;
      width: 48px;
      height: 48px;
      border-color: #ffffff;
      border-style: solid;
    }

    .top-left {
      left: 0;
      top: 0;
      border-width: 6px 0 0 6px;
      border-radius: 24px 0 0 0;
    }

    .top-right {
      right: 0;
      top: 0;
      border-width: 6px 6px 0 0;
      border-radius: 0 24px 0 0;
    }

    .bottom-left {
      left: 0;
      bottom: 0;
      border-width: 0 0 6px 6px;
      border-radius: 0 0 0 24px;
    }

    .bottom-right {
      right: 0;
      bottom: 0;
      border-width: 0 6px 6px 0;
      border-radius: 0 0 24px 0;
    }

    @keyframes scanLine {
      0% {
        transform: translateY(-96px);
      }

      50% {
        transform: translateY(96px);
      }

      100% {
        transform: translateY(-96px);
      }
    }

    .camera-bottom {
      position: fixed;
      left: 16px;
      right: 16px;
      bottom: calc(24px + env(safe-area-inset-bottom));
      z-index: 10000;
      text-align: center;
    }

    #scan-status {
      display: inline-block;
      padding: 12px 18px;
      border-radius: 999px;
      background: rgba(255,255,255,.94);
      color: #111;
      font-size: 15px;
      font-weight: 600;
    }

    #scan-status.scan-error {
      background: #dc2626;
      color: #fff;
    }
  </style>
</head>

<body class="camera-body">
  <main class="camera-page">
    <div class="camera-top">
      <a href="quest.php?id=<?= e($questId) ?>">กลับ</a>

      <div>
        <strong>สแกน QR Code</strong>
        <span><?= e($quest["title"]) ?></span>
      </div>
    </div>

    <div id="reader" class="camera-reader"></div>

    <div class="qr-frame">
      <div class="corner top-left"></div>
      <div class="corner top-right"></div>
      <div class="corner bottom-left"></div>
      <div class="corner bottom-right"></div>
    </div>

    <div class="camera-bottom">
      <div id="scan-status">กำลังเปิดกล้องหลัง</div>
    </div>

    <form id="scan-form" method="post" action="complete_quest.php">
      <input type="hidden" name="quest_id" value="<?= e($questId) ?>">
      <input type="hidden" id="scanned_code" name="scanned_code">
    </form>
  </main>

  <script>
    const statusBox = document.getElementById("scan-status");
    const codeInput = document.getElementById("scanned_code");
    const form = document.getElementById("scan-form");

    let locked = false;

    function submitCode(decodedText) {
      if (locked) return;

      locked = true;

      if (navigator.vibrate) {
        navigator.vibrate(180);
      }

      statusBox.textContent = "สแกนแล้ว กำลังตรวจสอบ";
      codeInput.value = decodedText;
      form.submit();
    }

    function showError(message) {
      statusBox.textContent = message;
      statusBox.classList.add("scan-error");
    }

    const html5QrCode = new Html5Qrcode("reader");

    const config = {
      fps: 15,
      qrbox: function(width, height) {
        const size = Math.floor(Math.min(width, height) * 0.62);

        return {
          width: size,
          height: size
        };
      },
      aspectRatio: 1.7777778,
      disableFlip: false
    };

    async function startCamera() {
      try {
        const cameras = await Html5Qrcode.getCameras();

        if (!cameras || cameras.length === 0) {
          showError("ไม่พบกล้องในอุปกรณ์นี้");
          return;
        }

        let selectedCamera = cameras.find(camera => {
          const label = (camera.label || "").toLowerCase();
          return label.includes("wide") || label.includes("ultra");
        });

        if (!selectedCamera) {
          selectedCamera = cameras.find(camera => {
            const label = (camera.label || "").toLowerCase();
            return (
              label.includes("back") ||
              label.includes("rear") ||
              label.includes("environment")
            );
          });
        }

        if (!selectedCamera) {
          selectedCamera = cameras[cameras.length - 1];
        }

        await html5QrCode.start(
          selectedCamera.id,
          config,
          submitCode,
          () => {}
        );

        statusBox.textContent = "วาง QR Code ให้อยู่ในกรอบ";
      } catch (err) {
        try {
          await html5QrCode.start(
            { facingMode: "environment" },
            config,
            submitCode,
            () => {}
          );

          statusBox.textContent = "วาง QR Code ให้อยู่ในกรอบ";
        } catch (error) {
          showError("เปิดกล้องไม่ได้ กรุณาใช้ลิงก์ HTTPS หรือ ngrok");
        }
      }
    }

    startCamera();
  </script>
</body>
</html>