<?php
require_once "_admin.php";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";

    if ($action === "add_points") {
        $targetUserId = intval($_POST["user_id"] ?? 0);
        $points = intval($_POST["points"] ?? 0);
        $reason = trim($_POST["reason"] ?? "เพิ่มคะแนนโดยแอดมิน");

        if ($targetUserId > 0 && $points !== 0) {
            $conn->begin_transaction();

            $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id=?");
            $stmt->bind_param("ii", $points, $targetUserId);
            $stmt->execute();

            $adminId = intval($_SESSION["user_id"]);
            $stmt = $conn->prepare("INSERT INTO point_logs (user_id, admin_id, points, reason) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iiis", $targetUserId, $adminId, $points, $reason);
            $stmt->execute();

            $conn->commit();
            $msg = "เพิ่มคะแนนสำเร็จ";
        } else {
            $msg = "ข้อมูลคะแนนไม่ถูกต้อง";
        }
    }

    if ($action === "add_place") {
        $name = trim($_POST["place_name"] ?? "");
        $description = trim($_POST["place_description"] ?? "");
        $location = trim($_POST["location_text"] ?? "");

        if ($name !== "") {
            $stmt = $conn->prepare("INSERT INTO places (name, description, location_text) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $name, $description, $location);
            $stmt->execute();
            $msg = "เพิ่มสถานที่สำเร็จ";
        }
    }

    if ($action === "add_quest") {
        $placeId = intval($_POST["place_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $questType = $_POST["quest_type"] ?? "qr";
        $rewardPoints = intval($_POST["reward_points"] ?? 10);

        $targetCode = "LAPIKAD-" . strtoupper(bin2hex(random_bytes(6)));

        if ($placeId > 0 && $title !== "") {
            $stmt = $conn->prepare("INSERT INTO quests (place_id, title, description, quest_type, reward_points, target_code) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssis", $placeId, $title, $description, $questType, $rewardPoints, $targetCode);
            $stmt->execute();
            $msg = "เพิ่มภารกิจและสร้าง QR Code สำเร็จ";
        } else {
            $msg = "กรุณากรอกข้อมูลภารกิจให้ครบ";
        }
    }
}

$users = $conn->query("SELECT id, name, email, role, points FROM users ORDER BY id");

$places = $conn->query("SELECT id, name FROM places ORDER BY id");
$placesForSelect = [];
while ($p = $places->fetch_assoc()) {
    $placesForSelect[] = $p;
}

$quests = $conn->query("
    SELECT q.*, p.name AS place_name
    FROM quests q
    JOIN places p ON p.id=q.place_id
    ORDER BY q.id DESC
");

$logs = $conn->query("
    SELECT pl.*, u.name AS user_name, a.name AS admin_name
    FROM point_logs pl
    JOIN users u ON u.id=pl.user_id
    LEFT JOIN users a ON a.id=pl.admin_id
    ORDER BY pl.id DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>แอดมิน | ล่าพิกัด.com</title>
  <link rel="stylesheet" href="css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
</head>
<body>
  <main class="app admin-wide">
    <?php include "_nav.php"; ?>

    <section class="panel">
      <p class="eyebrow">Admin Panel</p>
      <h1>จัดการระบบ</h1>
      <p class="muted">เพิ่มคะแนน เพิ่มสถานที่ เพิ่มภารกิจ และสร้าง QR Code อัตโนมัติ</p>
    </section>

    <?php if ($msg): ?>
      <div class="alert good"><?= e($msg) ?></div>
    <?php endif; ?>

    <section class="panel">
      <h2>เพิ่มคะแนนผู้ใช้</h2>

      <form method="post" class="form">
        <input type="hidden" name="action" value="add_points">

        <select name="user_id" required>
          <option value="">เลือกผู้ใช้</option>
          <?php while ($user = $users->fetch_assoc()): ?>
            <option value="<?= e($user["id"]) ?>">
              <?= e($user["name"]) ?> · <?= e($user["email"]) ?> · <?= e($user["points"]) ?> คะแนน
            </option>
          <?php endwhile; ?>
        </select>

        <input name="points" type="number" placeholder="คะแนน เช่น 50 หรือ -20" required>
        <input name="reason" placeholder="เหตุผล เช่น ชนะกิจกรรม / ปรับคะแนน">

        <button class="btn" type="submit">บันทึกคะแนน</button>
      </form>
    </section>

    <section class="panel">
      <h2>เพิ่มสถานที่</h2>

      <form method="post" class="form">
        <input type="hidden" name="action" value="add_place">

        <input name="place_name" placeholder="ชื่อสถานที่" required>
        <input name="location_text" placeholder="ตำแหน่งหรืออำเภอ">
        <textarea name="place_description" placeholder="รายละเอียดสถานที่"></textarea>

        <button class="btn" type="submit">เพิ่มสถานที่</button>
      </form>
    </section>

    <section class="panel">
      <h2>เพิ่มภารกิจพร้อมสร้าง QR</h2>
      <p class="muted">ระบบจะสุ่มรหัส QR ให้อัตโนมัติ ปลอมยากกว่าให้กรอกเอง</p>

      <form method="post" class="form">
        <input type="hidden" name="action" value="add_quest">

        <select name="place_id" required>
          <option value="">เลือกสถานที่</option>
          <?php foreach ($placesForSelect as $place): ?>
            <option value="<?= e($place["id"]) ?>">
              <?= e($place["name"]) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <input name="title" placeholder="ชื่อภารกิจ เช่น สแกน QR หน้าวัด" required>
        <textarea name="description" placeholder="รายละเอียดภารกิจ"></textarea>

        <select name="quest_type">
          <option value="qr">QR Code</option>
          <option value="checkin">Check-in</option>
          <option value="quiz">Quiz</option>
        </select>

        <input name="reward_points" type="number" value="10" placeholder="คะแนนรางวัล">

        <button class="btn" type="submit">สร้างภารกิจและ QR Code</button>
      </form>
    </section>

    <section class="panel">
  <h2>QR Code ภารกิจ</h2>
  <p class="muted">กดดู QR Code เพื่อเปิดหน้า QR สำหรับดาวน์โหลดหรือนำไปพิมพ์ติดตามสถานที่</p>

  <?php while ($quest = $quests->fetch_assoc()): ?>
    <?php if ($quest["quest_type"] === "qr"): ?>
      <div class="admin-item">
        <strong><?= e($quest["title"]) ?></strong>

        <p class="muted">
          <?= e($quest["place_name"]) ?> · +<?= e($quest["reward_points"]) ?> คะแนน
        </p>

        <code>LAPIKAD:<?= e($quest["target_code"]) ?></code>

        <a
          class="btn ghost"
          href="qr_view.php?id=<?= e($quest["id"]) ?>"
        >
          ดู QR Code
        </a>
      </div>
    <?php endif; ?>
  <?php endwhile; ?>
</section>

    <section class="panel">
      <h2>ประวัติคะแนนล่าสุด</h2>

      <?php while ($log = $logs->fetch_assoc()): ?>
        <div class="admin-item">
          <strong><?= e($log["user_name"]) ?></strong>
          <p class="muted">
            <?= e($log["points"]) ?> คะแนน · <?= e($log["reason"]) ?>
          </p>
        </div>
      <?php endwhile; ?>
    </section>
  </main>

  <script>
    document.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll("[data-qr]").forEach((canvas) => {
        const text = canvas.dataset.qr;

        QRCode.toCanvas(
          canvas,
          text,
          {
            width: 220,
            margin: 2
          }
        );
      });
    });

    function downloadQR(canvasId, filename) {
      const canvas = document.getElementById(canvasId);
      const link = document.createElement("a");

      link.download = filename + ".png";
      link.href = canvas.toDataURL("image/png");
      link.click();
    }
  </script>
</body>
</html>