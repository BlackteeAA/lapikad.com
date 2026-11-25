<?php
// Council-made entry link / QR (created in event_admin.php → "ลิงก์ / QR เข้างาน").
// Opening it enters the student into the event ("เข้างาน") and sends them to the
// event page (event_room.php), where they check in. Students don't see
// the school menu; this link is how they take part in school activities.
// Only problems are rendered here; success redirects.
require_once "_db.php";

$token   = strtoupper(trim($_GET["k"] ?? ""));
$token   = preg_match('/^[0-9A-F]{20}$/', $token) ? $token : "";
$selfUrl = "event_link.php?k=" . $token;

// Not logged in: remember the link so login.php brings the student straight back.
if (!isset($_SESSION["user_id"])) tryRememberLogin($conn);
if (!isset($_SESSION["user_id"])) {
    if ($token !== "") $_SESSION["after_login"] = $selfUrl;
    redirect("login.php");
}

require_once "_events.php";

$userId   = intval($_SESSION["user_id"]);
$mySchool = getUserSchool($conn, $userId);

$state   = "bad";     // good | info | bad
$title   = "ลิงก์ไม่ถูกต้อง";
$message = "ลิงก์หรือ QR นี้ไม่มีในระบบ หรือถูกลบไปแล้ว ขอลิงก์ใหม่จากสภานักเรียน";

$link = null;
if ($token !== "") {
    try {
        $link = getEventLink($conn, $token);
    } catch (mysqli_sql_exception $e) {
        $link = null; // sql/add_event_links.sql not run yet
    }
}
$event = $link ? getEvent($conn, intval($link["event_id"])) : null;

if ($event) {
    if (eventLinkExpired($link)) {
        $title   = "ลิงก์หมดอายุแล้ว";
        $message = "ลิงก์นี้หมดอายุเมื่อ " . thaiTimestampText($link["expires_at"]) . " ขอลิงก์ใหม่จากสภานักเรียน";
    } elseif (!isSiteAdmin() && $mySchool["school_id"] === null) {
        $title   = "ยังไม่ได้อยู่ในโรงเรียน";
        $message = "บัญชีนี้ยังไม่ได้อยู่ในโรงเรียนใด ให้ครูหรือสภานักเรียนเพิ่มคุณเข้าโรงเรียนก่อน แล้วเปิดลิงก์นี้อีกครั้ง";
    } elseif (!canJoinEvent($conn, $event, $userId)) {
        $title   = "ต่างโรงเรียน";
        $message = "กิจกรรม \"" . $event["title"] . "\" เป็นของโรงเรียนอื่น";
    } elseif (!hasStudentInfo($conn, $userId)) {
        // First time: fill in real name / class / roll number, then come back here.
        redirect("event_student_info.php?next=" . urlencode($selfUrl));
    } else {
        // This link only enters the event ("เข้างาน"); checking in is done
        // afterwards from inside the event page.
        $result = joinEvent($conn, $event, $userId, intval($link["id"]));
        if ($result === "ok") {
            redirect("event_room.php?id=" . intval($event["id"]) . "&joined=1");
        } elseif ($result === "already") {
            redirect("event_room.php?id=" . intval($event["id"]));
        } elseif ($result === "other_school") {
            $title   = "ต่างโรงเรียน";
            $message = "กิจกรรมนี้เป็นของโรงเรียนอื่น";
        } else {
            $title   = "เกิดข้อผิดพลาด";
            $message = "บันทึกไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
        }
    }
}

$stmt = $conn->prepare("SELECT name, student_name, class_room, student_no, avatar_url FROM users WHERE id=?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$myName = trim($me["student_name"] ?? "") !== "" ? $me["student_name"] : $me["name"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= e($title) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; }
    .el-wrap { max-width:460px; margin:0 auto; padding-top:8px; }
    .el-card { background:#fff; border-radius:24px; padding:26px 20px 22px; text-align:center; margin-bottom:14px; }
    .el-mark { width:76px; height:76px; margin:0 auto 14px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:38px; font-weight:700; }
    .el-mark.good { background:#dcfce7; color:#16a34a; }
    .el-mark.info { background:#dbeafe; color:#2563eb; }
    .el-mark.bad  { background:#fee2e2; color:#dc2626; }
    .el-card h1 { font-size:22px; margin:0 0 6px; color:#0f172a; }
    .el-card p  { margin:0; color:#64748b; font-size:14.5px; }
    .el-event { margin:16px 0 0; padding:14px; border-radius:16px; background:#f8fafc; text-align:left; }
    .el-event strong { display:block; font-size:15.5px; color:#0f172a; }
    .el-event span { font-size:13px; color:#64748b; }
    .el-points { display:inline-block; margin-top:14px; padding:8px 18px; border-radius:999px; background:#fef9c3; color:#a16207; font-weight:600; font-size:16px; }
    .el-me { display:flex; align-items:center; gap:12px; background:#fff; border-radius:20px; padding:14px 16px; margin-bottom:14px; }
    .el-avatar { width:46px; height:46px; border-radius:50%; overflow:hidden; flex-shrink:0; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#2563eb,#22c55e); color:#fff; font-weight:600; font-size:19px; }
    .el-avatar img { width:100%; height:100%; object-fit:cover; }
    .el-me-info { flex:1; min-width:0; }
    .el-me-info strong { display:block; color:#0f172a; font-size:15px; }
    .el-me-info span { font-size:12.5px; color:#64748b; }
    .el-me-pts { text-align:right; }
    .el-me-pts b { display:block; font-size:20px; color:#2563eb; line-height:1.1; }
    .el-me-pts span { font-size:11.5px; color:#94a3b8; }
    .el-btn { display:block; text-align:center; padding:14px; border-radius:16px; background:#2563eb; color:#fff; text-decoration:none; font-weight:600; margin-bottom:10px; }
    .el-btn.ghost { background:#fff; color:#2563eb; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%">
    <?php include "includes/topbar.php"; ?>

    <div class="el-wrap">
      <section class="el-card">
        <div class="el-mark <?= $state ?>"><?= $state === "bad" ? "!" : "✓" ?></div>
        <h1><?= e($title) ?></h1>
        <p><?= e($message) ?></p>

        <?php if ($event && $state !== "bad"): ?>
          <div class="el-event">
            <strong><?= e($event["title"]) ?></strong>
            <span>
              <?= $event["starts_at"] ? e(eventDateText($event["starts_at"])) : "" ?>
              <?= $event["location_text"] ? ($event["starts_at"] ? " · " : "") . e($event["location_text"]) : "" ?>
            </span>
          </div>
        <?php endif; ?>

      </section>

      <?php if ($me): ?>
      <section class="el-me">
        <div class="el-avatar">
          <?php if (!empty($me["avatar_url"])): ?>
            <img src="<?= e($me["avatar_url"]) ?>" alt="">
          <?php else: ?>
            <?= e(mb_substr($myName, 0, 1, "UTF-8")) ?>
          <?php endif; ?>
        </div>
        <div class="el-me-info">
          <strong><?= e($myName) ?></strong>
          <span>
            <?= !empty($me["class_room"]) ? "ชั้น " . e($me["class_room"]) : "" ?>
            <?= !empty($me["student_no"]) ? " · เลขที่ " . e($me["student_no"]) : "" ?>
            <?= !empty($mySchool["school_name"]) ? "<br>" . e($mySchool["school_name"]) : "" ?>
          </span>
        </div>
      </section>
      <?php endif; ?>

      <a class="el-btn" href="dashboard.php">กลับหน้าหลัก</a>
      <?php if ($state === "bad"): ?>
        <a class="el-btn ghost" href="scan_any.php">สแกน QR อื่น</a>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
