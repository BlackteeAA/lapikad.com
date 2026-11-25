<?php
// First-time gate for the student-events module: collects the student's school and
// real name/class/roll-number (separate from the account's login name), so staff
// scanning the personal QR (event_staff_checkin.php) see who they really are.
// Write-once: students can't edit what's already saved. Changes go through the
// school's teacher or a site admin (school_members.php / admin_users.php).
require_once "_events.php";

$userId = intval($_SESSION["user_id"]);

$allowedNext = ["events.php", "event_my_qr.php", "event_scan.php"];
$next = $_GET["next"] ?? $_POST["next"] ?? "events.php";
if (!in_array($next, $allowedNext, true)
    && !preg_match('/^event_link\.php\?k=[0-9A-F]{20}$/', $next)
    && !preg_match('/^event_(room|scan|my_qr)\.php\?id=\d+$/', $next)) {
    $next = "events.php";
}

$stmt = $conn->prepare("SELECT student_name, class_room, student_no FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$info = $stmt->get_result()->fetch_assoc();

$mySchool = getUserSchool($conn, $userId);
$schools  = getAllSchools($conn);
$info["school_id"] = $mySchool["school_id"];

// Everything already filled in: nothing to do here.
if (hasStudentInfo($conn, $userId)) redirect($next);

// Only the missing parts are asked for. A school that's already set (e.g. assigned by a
// teacher), or name/class/roll-number saved earlier (e.g. before being removed from
// a school), stays as it is.
$schoolLocked = $mySchool["school_id"] !== null;
$infoLocked   = trim($info["student_name"] ?? "") !== ""
             && trim($info["class_room"] ?? "") !== ""
             && trim($info["student_no"] ?? "") !== "";

$msg = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("event_student_info.php?next=" . urlencode($next));

    // Locked parts ignore whatever was posted.
    $studentName = $infoLocked ? $info["student_name"] : trim($_POST["student_name"] ?? "");
    $classRoom   = $infoLocked ? $info["class_room"]   : trim($_POST["class_room"] ?? "");
    $studentNo   = $infoLocked ? $info["student_no"]   : trim($_POST["student_no"] ?? "");
    $schoolId    = $schoolLocked ? $mySchool["school_id"] : intval($_POST["school_id"] ?? 0);
    $info = ["student_name" => $studentName, "class_room" => $classRoom, "student_no" => $studentNo, "school_id" => $schoolId];

    if ($schoolId <= 0 || !getSchool($conn, $schoolId)) {
        $msg = "กรุณาเลือกโรงเรียน";
    } elseif ($studentName === "" || $classRoom === "" || $studentNo === "") {
        $msg = "กรุณากรอกข้อมูลให้ครบ";
    } else {
        if (!$infoLocked) {
            $stmt = $conn->prepare("UPDATE users SET student_name=?, class_room=?, student_no=? WHERE id=?");
            $stmt->bind_param("sssi", $studentName, $classRoom, $studentNo, $userId);
            $stmt->execute();
        }
        // Joining a school on your own always starts as a plain student: council/teacher
        // roles are granted per school by that school's teacher or a site admin.
        if (!$schoolLocked) {
            setUserSchool($conn, $userId, $schoolId, "student");
        }
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
    input:focus, select:focus { border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.12);background:#fff; }
    select {
      width:100%;padding:13px 16px;border:1.5px solid #bfdbfe;border-radius:14px;
      background:#eff6ff;font-family:'Kanit',sans-serif;font-size:15px;color:#0f172a;
      margin-bottom:16px;outline:none;
    }
    .note { font-size:12.5px;color:#b45309;background:#fffbeb;border-radius:12px;padding:10px 12px;margin:-8px 0 16px; }
    input.locked { background:#f1f5f9;border-color:#e2e8f0;color:#64748b; }
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
    <p class="sub">เลือกโรงเรียน แล้วกรอกชื่อ-นามสกุล ชั้น และเลขที่จริงของคุณ เพื่อให้สภานักเรียนเช็คชื่อถูกคน</p>
    <p class="note">ตรวจสอบให้ถูกต้องก่อนบันทึก · บันทึกแล้วจะแก้ไขเองไม่ได้ หากผิดให้แจ้งครูหรือแอดมิน</p>

    <?php if ($msg): ?><div class="alert"><?= e($msg) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="next" value="<?= e($next) ?>">

      <label>โรงเรียน</label>
      <?php if ($schoolLocked): ?>
        <input value="<?= e($mySchool["school_name"]) ?>" readonly class="locked">
      <?php elseif (!$schools): ?>
        <div class="alert">ยังไม่มีโรงเรียนในระบบ กรุณาติดต่อแอดมิน</div>
      <?php else: ?>
        <select name="school_id" required>
          <option value="">เลือกโรงเรียน</option>
          <?php foreach ($schools as $sc): ?>
            <option value="<?= $sc["id"] ?>" <?= intval($info["school_id"] ?? 0) === intval($sc["id"]) ? "selected" : "" ?>>
              <?= e($sc["name"]) ?><?= schoolPlaceText($sc) !== "" ? " · " . e(schoolPlaceText($sc)) : "" ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php endif; ?>

      <?php $lockAttr = $infoLocked ? 'readonly class="locked"' : "required"; ?>
      <label>ชื่อ-นามสกุล</label>
      <input name="student_name" value="<?= e($info["student_name"] ?? "") ?>" placeholder="กรอกชื่อ-นามสกุลจริง" <?= $lockAttr ?>>

      <div class="row">
        <div>
          <label>ชั้น</label>
          <input name="class_room" value="<?= e($info["class_room"] ?? "") ?>" placeholder="เช่น ม.3/2" <?= $lockAttr ?>>
        </div>
        <div>
          <label>เลขที่</label>
          <input name="student_no" value="<?= e($info["student_no"] ?? "") ?>" placeholder="เช่น 15" <?= $lockAttr ?>>
        </div>
      </div>

      <button type="submit">บันทึกและเข้าสู่เมนูนักเรียน</button>
    </form>
  </div>
</body>
</html>
