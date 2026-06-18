<?php
require_once "_auth.php";

$questId = intval($_GET["id"] ?? 0);

$stmt = $conn->prepare("
    SELECT q.*, p.name AS place_name
    FROM quests q
    JOIN places p ON p.id=q.place_id
    WHERE q.id=?
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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>สแกน QR | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
      fps: 12,
      qrbox: function(viewfinderWidth, viewfinderHeight) {
        const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
        const boxSize = Math.floor(minEdge * 0.72);
        return { width: boxSize, height: boxSize };
      },
      aspectRatio: 1.7777778,
      disableFlip: false
    };

    Html5Qrcode.getCameras()
      .then(function(cameras) {
        if (!cameras || cameras.length === 0) {
          showError("ไม่พบกล้องในอุปกรณ์นี้");
          return;
        }

        let backCamera = cameras.find(function(camera) {
          const label = (camera.label || "").toLowerCase();
          return label.includes("back") || label.includes("rear") || label.includes("environment");
        });

        let cameraId = backCamera ? backCamera.id : cameras[cameras.length - 1].id;

        html5QrCode.start(
          cameraId,
          config,
          function(decodedText) {
            submitCode(decodedText);
          },
          function(errorMessage) {}
        ).then(function() {
          statusBox.textContent = "เล็งกล้องไปที่ QR Code";
        }).catch(function(err) {
          html5QrCode.start(
            { facingMode: { exact: "environment" } },
            config,
            function(decodedText) {
              submitCode(decodedText);
            },
            function(errorMessage) {}
          ).then(function() {
            statusBox.textContent = "เล็งกล้องไปที่ QR Code";
          }).catch(function(error) {
            showError("เปิดกล้องหลังไม่ได้ กรุณาอนุญาตการใช้กล้อง");
          });
        });
      })
      .catch(function(error) {
        html5QrCode.start(
          { facingMode: "environment" },
          config,
          function(decodedText) {
            submitCode(decodedText);
          },
          function(errorMessage) {}
        ).then(function() {
          statusBox.textContent = "เล็งกล้องไปที่ QR Code";
        }).catch(function(err) {
          showError("เปิดกล้องไม่ได้ กรุณาใช้ลิงก์ HTTPS หรือ ngrok");
        });
      });
  </script>
</body>
</html>
