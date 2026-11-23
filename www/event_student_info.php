<?php
// First-time gate for the student-events module: collects the student's real
// name/class/roll-number (separate from the account's login name), so staff
// scanning the personal QR (event_staff_checkin.php) see who they really are.
require_once "_events.php";

$userId = intval($_SESSION["user_id"]);

$allowedNext = ["events.php", "event_my_qr.php", "event_scan.php"];
$next = $_GET["next"] ?? $_POST["next"] ?? "events.php";
if (!in_array($next, $allowedNext, true)) $next = "events.php";

$stmt = $conn->prepare("SELECT student_name, class_room, student_no FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("event_student_info.php?next=" . urlencode($next));

    $studentName = trim($_POST["student_name"] ?? "");
    $classRoom   = trim($_POST["class_room"] ?? "");
    $studentNo   = trim($_POST["student_no"] ?? "");
    $info = ["student_name" => $studentName, "class_room" => $classRoom, "student_no" => $studentNo];

    if ($studentName === "" || $classRoom === "" || $studentNo === "") {
        $msg = "กรุณากรอกข้อมูลให้ครบ";
    } else {
        $stmt = $conn->prepare("UPDATE users SET student_name=?, class_room=?, student_no=? WHERE id=?");
        $stmt->bind_param("sssi", $studentName, $classRoom, $studentNo, $userId);
        $stmt->execute();
        redirect($next);
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>ข้อมูลนักเรียน | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box;margin:0;padding:0; }
    body {
      font-family:'Kanit',sans-serif;min-height:100vh;padding:20px;
      background:linear-gradient(135deg,#0ea5e9,#6366f1);
      display:flex;align-items:center;justify-content:center;
    }
    .card {
      width:100%;max-width:380px;background:#fff;border-radius:28px;padding:28px 24px;
      box-shadow:0 20px 60px rgba(0,0,0,.25);
    }
    h1 { font-size:20px;color:#0f172a;font-weight:700;margin-bottom:4px; }
    .sub { font-size:13px;color:#64748b;margin-bottom:20px; }
    label { display:block;font-size:14px;font-weight:500;color:#374151;margin-bottom:6px; }
    input {
      width:100%;padding:13px 16px;border:1.5px solid #bfdbfe;border-radius:14px;
      background:#eff6ff;font-family:'Kanit',sans-serif;font-size:15px;color:#0f172a;
      margin-bottom:16px;outline:none;transition:border-color .2s, box-shadow .2s;
    }
    input:focus { border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);background:#fff; }
    .row { display:grid;grid-template-columns:1fr 1fr;gap:12px; }
    .alert {
      background:#fff1f2;color:#dc2626;border:1px solid #fecdd3;padding:12px 14px;
      border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px;
    }
    button {
      display:block;width:100%;padding:15px;background:#2563eb;color:#fff;border:none;
      border-radius:999px;font-family:'Kanit',sans-serif;font-size:16px;font-weight:600;
      cursor:pointer;transition:opacity .2s;
    }
    button:hover { opacity:.88; }
  </style>
</head>
<body>
  <div class="card">
    <h1>ก่อนเข้าเมนูนักเรียน</h1>
    <p class="sub">กรอกชื่อ-นามสกุล ชั้น และเลขที่จริงของคุณ เพื่อให้สภานักเรียนเช็คชื่อถูกคน</p>

    <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <label>ชื่อ-นามสกุล</label>
      <input name="student_name" value="<?= e($info["student_name"] ?? "") ?>" placeholder="กรอกชื่อ-นามสกุลจริง" required>

      <div class="row">
        <div>
          <label>ชั้น</label>
          <input name="class_room" value="<?= e($info["class_room"] ?? "") ?>" placeholder="เช่น ม.3/2" required>
        </div>
        <div>
          <label>เลขที่</label>
          <input name="student_no" value="<?= e($info["student_no"] ?? "") ?>" placeholder="เช่น 15" required>
        </div>
      </div>

      <button type="submit">บันทึกและเข้าสู่เมนูนักเรียน</button>
    </form>
  </div>
</body>
</html>
