<?php
// One school's members and roles.
//   site admin  — add anyone, set any role (including teacher), remove anyone
//   teacher     — add users who have no school yet, switch student <-> council, remove students/council,
//                 fix students'/council's name/class/roll-number (students can't edit it themselves)
//   council     — view only
require_once "_auth.php";
require_once "_school.php";

$userId   = intval($_SESSION["user_id"]);
$schoolId = intval($_GET["id"] ?? $_POST["school_id"] ?? 0);
$school   = $schoolId > 0 ? getSchool($conn, $schoolId) : null;
if (!$school) redirect(isSiteAdmin() ? "admin_schools.php" : "dashboard.php");

$canManage = canManageSchoolMembers($conn, $userId, $schoolId);
if (!$canManage && !canManageSchoolContent($conn, $userId, $schoolId)) redirect("dashboard.php");
$isAdmin = isSiteAdmin();

// Roles this viewer may hand out.
$grantable = $isAdmin ? ["student", "council", "teacher"] : ["student", "council"];

function loadMember($conn, $memberId, $schoolId) {
    $stmt = $conn->prepare("SELECT id, name, role, school_role FROM users WHERE id=? AND school_id=?");
    $stmt->bind_param("ii", $memberId, $schoolId);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

$msg = "";
$msgType = "good";

if (uploadPostTooLarge()) {
    $msg = uploadTooLargeMessage(); $msgType = "bad";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST" && $canManage) {
    if (!csrf_verify()) redirect("school_members.php?id=" . $schoolId);
    $action = $_POST["action"] ?? "";

    if ($action === "add_member") {
        $email = trim($_POST["email"] ?? "");
        $role  = $_POST["school_role"] ?? "student";
        $stmt = $conn->prepare("SELECT id, name, role, school_id FROM users WHERE email=?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $target = $stmt->get_result()->fetch_assoc();

        if (!$target) {
            $msg = "ไม่พบผู้ใช้อีเมลนี้"; $msgType = "bad";
        } elseif (!in_array($role, $grantable, true)) {
            $msg = "ไม่มีสิทธิ์กำหนดบทบาทนี้"; $msgType = "bad";
        } elseif ($target["school_id"] !== null && intval($target["school_id"]) !== $schoolId && !$isAdmin) {
            $msg = $target["name"] . " อยู่โรงเรียนอื่นแล้ว ให้นักเรียนเปลี่ยนโรงเรียนเองก่อน"; $msgType = "bad";
        } elseif (intval($target["school_id"] ?? 0) === $schoolId) {
            $msg = $target["name"] . " เป็นสมาชิกโรงเรียนนี้อยู่แล้ว"; $msgType = "bad";
        } else {
            setUserSchool($conn, intval($target["id"]), $schoolId, $role);
            $msg = "เพิ่ม " . $target["name"] . " เป็น" . schoolRoleLabel($role) . "แล้ว";
        }
    }

    if ($action === "set_logo") {
        if (!empty($_POST["remove_logo"])) {
            removeSchoolLogo($conn, $schoolId);
            $msg = "ลบโลโก้แล้ว";
        } else {
            $err = saveSchoolLogo($conn, $schoolId, $_FILES["logo"] ?? null);
            if ($err) { $msg = $err; $msgType = "bad"; }
            elseif (($_FILES["logo"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { $msg = "กรุณาเลือกไฟล์โลโก้"; $msgType = "bad"; }
            else $msg = "อัปเดตโลโก้โรงเรียนแล้ว";
        }
        $school = getSchool($conn, $schoolId);
    }

    // Students can't edit their own name/class/roll-number once saved; fixes go here.
    if ($action === "edit_info") {
        $memberId = intval($_POST["user_id"] ?? 0);
        $member   = loadMember($conn, $memberId, $schoolId);
        $studentName = trim($_POST["student_name"] ?? "");
        $classRoom   = trim($_POST["class_room"] ?? "");
        $studentNo   = trim($_POST["student_no"] ?? "");

        if (!$member) {
            $msg = "ไม่พบสมาชิก"; $msgType = "bad";
        } elseif ($member["school_role"] === "teacher" && !$isAdmin && $memberId !== $userId) {
            $msg = "เฉพาะแอดมินที่แก้ข้อมูลครูคนอื่นได้"; $msgType = "bad";
        } elseif ($studentName === "") {
            $msg = "กรุณากรอกชื่อ-นามสกุล"; $msgType = "bad";
        } else {
            $stmt = $conn->prepare("UPDATE users SET student_name=?, class_room=?, student_no=? WHERE id=?");
            $stmt->bind_param("sssi", $studentName, $classRoom, $studentNo, $memberId);
            $stmt->execute();
            $msg = "บันทึกข้อมูลของ " . $studentName . " แล้ว";
        }
    }

    if ($action === "set_role" || $action === "remove_member") {
        $memberId = intval($_POST["user_id"] ?? 0);
        $member   = loadMember($conn, $memberId, $schoolId);
        $newRole  = $_POST["school_role"] ?? "";

        if (!$member) {
            $msg = "ไม่พบสมาชิก"; $msgType = "bad";
        } elseif ($memberId === $userId && !$isAdmin) {
            $msg = "ไม่สามารถเปลี่ยนสิทธิ์ของตัวเองได้"; $msgType = "bad";
        } elseif ($member["school_role"] === "teacher" && !$isAdmin) {
            $msg = "เฉพาะแอดมินที่เปลี่ยนสิทธิ์ครูได้"; $msgType = "bad";
        } elseif ($action === "set_role" && !in_array($newRole, $grantable, true)) {
            $msg = "ไม่มีสิทธิ์กำหนดบทบาทนี้"; $msgType = "bad";
        } elseif ($action === "set_role") {
            setUserSchool($conn, $memberId, $schoolId, $newRole);
            $msg = $member["name"] . " เป็น" . schoolRoleLabel($newRole) . "แล้ว";
        } else {
            setUserSchool($conn, $memberId, null, null);
            // Drop them from helper lists of this school's events too.
            $stmt = $conn->prepare("DELETE es FROM event_staff es JOIN events e ON e.id = es.event_id WHERE es.user_id=? AND e.school_id=?");
            $stmt->bind_param("ii", $memberId, $schoolId);
            $stmt->execute();
            $msg = "นำ " . $member["name"] . " ออกจากโรงเรียนแล้ว";
        }
    }
}

$stmt = $conn->prepare("
    SELECT id, name, email, avatar_url, student_name, class_room, student_no, school_role
    FROM users
    WHERE school_id=?
    ORDER BY FIELD(school_role, 'teacher', 'council', 'student'), class_room, CAST(student_no AS UNSIGNED), name
");
$stmt->bind_param("i", $schoolId);
$stmt->execute();
$members = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$groups = ["teacher" => [], "council" => [], "student" => []];
foreach ($members as $m) $groups[$m["school_role"] ?? "student"][] = $m;
$groupTitle = ["teacher" => "ครู / ผู้ดูแลโรงเรียน", "council" => "สภานักเรียน", "student" => "นักเรียน"];
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>สมาชิก <?= e($school["name"]) ?> | ล่าพิกัด.com</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/modern.css">
  <style>
    body { font-family:'Kanit',sans-serif; background:#f1f5f9!important; background-image:none!important; }
    .back-btn { display:inline-flex;align-items:center;gap:6px;color:#2563eb;font-size:14px;font-weight:500;text-decoration:none;margin-bottom:14px; }
    .back-btn svg { width:18px;height:18px;fill:currentColor; }
    .pg-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;gap:10px; }
    .pg-head h1 { font-size:20px;font-weight:700;color:#0f172a;margin:0; }
    .pg-head .sub { display:block;font-size:12.5px;color:#64748b; }
    .adm-btn {
      display:inline-flex;align-items:center;justify-content:center;gap:6px;
      padding:11px 18px;border:none;border-radius:999px;
      font-family:inherit;font-size:13.5px;font-weight:600;cursor:pointer;transition:opacity .2s;flex-shrink:0;
    }
    .adm-btn.primary { background:#2563eb;color:#fff; }
    .adm-btn:hover   { opacity:.85; }
    .adm-alert { padding:12px 16px;border-radius:14px;font-size:14px;font-weight:500;margin-bottom:16px; }
    .adm-alert.good { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .adm-alert.bad  { background:#fff1f2;color:#e11d48;border:1px solid #fecdd3; }
    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06);margin-bottom:14px; }
    .sec { font-size:14px;font-weight:700;color:#0f172a;margin:18px 0 8px;display:flex;gap:6px;align-items:center; }
    .sec small { font-size:12px;color:#64748b;font-weight:500; }
    .search-box { display:flex;align-items:center;gap:8px;background:#fff;border-radius:12px;padding:10px 14px;margin-bottom:6px;box-shadow:0 2px 10px rgba(15,23,42,.06); }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }
    .row-item { display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }
    .avatar {
      width:40px;height:40px;border-radius:50%;overflow:hidden;
      background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;
      display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;
    }
    .avatar img { width:100%;height:100%;object-fit:cover; }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { display:block;font-size:11.5px;color:#94a3b8;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .role-select { font-family:inherit;font-size:12px;padding:6px 8px;border-radius:10px;border:1.5px solid #c7d2fe;background:#fff;color:#4338ca;max-width:130px; }
    .icon-btn {
      width:28px;height:28px;border-radius:8px;flex-shrink:0;
      display:flex;align-items:center;justify-content:center;
      border:none;cursor:pointer;background:#fff1f2;color:#e11d48;
    }
    .icon-btn svg { width:14px;height:14px; }
    .icon-btn.edit { background:#eff6ff;color:#2563eb; }
    .empty { text-align:center;padding:16px;color:#94a3b8;font-size:13px; }

    .member-entry { border-bottom:1px solid #f1f5f9; }
    .member-entry:last-child { border-bottom:none; }
    .member-entry .row-item { border-bottom:none; }

    /* ชั้น → ห้อง groups */
    .grp { margin-bottom:10px; }
    .grp > summary {
      list-style:none;cursor:pointer;display:flex;align-items:center;gap:10px;
      background:#fff;border-radius:16px;padding:12px 16px;box-shadow:0 2px 10px rgba(15,23,42,.06);
    }
    .grp > summary::-webkit-details-marker { display:none; }
    .grp > summary::after {
      content:"";width:8px;height:8px;border-right:2px solid #94a3b8;border-bottom:2px solid #94a3b8;
      transform:rotate(-45deg);transition:transform .2s;flex-shrink:0;margin-left:4px;
    }
    .grp[open] > summary::after { transform:rotate(45deg); }
    .grp-title { flex:1;min-width:0;font-size:14.5px;font-weight:600;color:#0f172a; }
    .grp .cnt { font-size:12px;color:#64748b;white-space:nowrap; }
    .grp > .grp-body { padding:10px 0 0 14px; }
    .grp > .grp-body.pg-card { margin:8px 0 0 14px;padding:8px 18px; }
    .grp-room > summary { background:transparent;box-shadow:none;border:1px dashed #cbd5e1;padding:9px 14px; }
    .grp-room .grp-title { font-size:13.5px; }
    .adm-form { display:grid;gap:10px;margin:14px 0; }
    .adm-row  { display:grid;grid-template-columns:2fr 1fr;gap:10px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="<?= $isAdmin ? "admin_schools.php" : "events.php" ?>" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <?= schoolLogoHtml($school, 52) ?>
      <div style="flex:1;min-width:0">
        <h1><?= e($school["name"]) ?></h1>
        <span class="sub">สมาชิก <?= count($members) ?> คน<?= schoolPlaceText($school) !== "" ? " · " . e(schoolPlaceText($school)) : "" ?></span>
      </div>
      <?php if ($canManage): ?>
        <button type="button" class="adm-btn primary" onclick="toggleAdd()">+ เพิ่มสมาชิก</button>
      <?php endif; ?>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <?php if ($canManage): ?>
    <div class="pg-card" style="padding:12px 18px">
      <form method="post" enctype="multipart/form-data" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <input type="hidden" name="action" value="set_logo">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="school_id" value="<?= $schoolId ?>">
        <span style="font-size:13px;font-weight:600;color:#0f172a">โลโก้โรงเรียน</span>
        <input type="file" name="logo" accept="image/*" style="flex:1;min-width:160px;font-size:12.5px;background:#f8fafc;border:1.5px dashed #bfdbfe;padding:8px;border-radius:12px">
        <button class="adm-btn primary" type="submit" style="padding:9px 14px"><?= !empty($school["logo_url"]) ? "เปลี่ยนโลโก้" : "อัปโหลด" ?></button>
        <?php if (!empty($school["logo_url"])): ?>
          <button class="adm-btn" type="submit" name="remove_logo" value="1" style="padding:9px 14px;background:#fff1f2;color:#e11d48" onclick="return confirm('ลบโลโก้โรงเรียน?')">ลบ</button>
        <?php endif; ?>
      </form>
    </div>

    <div id="add-form" class="pg-card" style="display:<?= $msgType === "bad" && ($_POST["action"] ?? "") === "add_member" ? "block" : "none" ?>">
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="add_member">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="school_id" value="<?= $schoolId ?>">
        <div class="adm-row">
          <input type="email" name="email" placeholder="อีเมลของผู้ใช้" required>
          <select name="school_role">
            <?php foreach ($grantable as $r): ?>
              <option value="<?= $r ?>"><?= e(schoolRoleLabel($r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="adm-btn primary" type="submit">เพิ่มเข้าโรงเรียน</button>
        <p style="font-size:12px;color:#94a3b8;margin:0">นักเรียนเลือกโรงเรียนเองได้ตอนเข้าเมนูกิจกรรมโรงเรียนครั้งแรก<?= $isAdmin ? "" : " · เพิ่มได้เฉพาะผู้ที่ยังไม่มีโรงเรียน" ?></p>
      </form>
    </div>
    <?php endif; ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" placeholder="ค้นหาชื่อ / ชั้น / อีเมล" oninput="filterMembers(this.value)">
    </div>

    <?php $renderMember = function ($m) use ($canManage, $isAdmin, $userId, $grantable, $schoolId) {
          $display = trim($m["student_name"] ?? "") !== "" ? $m["student_name"] : $m["name"];
          $classText = trim(($m["class_room"] ?? "") !== "" ? $m["class_room"] . (($m["student_no"] ?? "") !== "" ? " เลขที่ " . $m["student_no"] : "") : "");
          // Teachers can't touch themselves or other teachers; admins can touch anyone.
          $editable = $canManage && ($isAdmin || ($m["school_role"] !== "teacher" && intval($m["id"]) !== $userId));
          $infoEditable = $canManage && ($isAdmin || $m["school_role"] !== "teacher" || intval($m["id"]) === $userId);
    ?>
      <div class="member-entry">
          <div class="row-item" data-search="<?= e(mb_strtolower($display . " " . $m["name"] . " " . $m["email"] . " " . $classText, "UTF-8")) ?>">
            <div class="avatar">
              <?php if (!empty($m["avatar_url"])): ?>
                <img src="<?= e($m["avatar_url"]) ?>" alt="">
              <?php else: ?>
                <?= e(mb_substr($display, 0, 1, "UTF-8")) ?>
              <?php endif; ?>
            </div>
            <div class="row-info">
              <strong><?= e($display) ?></strong>
              <span><?= $classText !== "" ? e($classText) . " · " : "" ?><?= e($m["email"]) ?></span>
            </div>
            <?php if ($infoEditable): ?>
              <button type="button" class="icon-btn edit" title="แก้ไขชื่อ / ชั้น / เลขที่" onclick="toggleEdit(<?= $m["id"] ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
              </button>
            <?php endif; ?>
            <?php if ($editable): ?>
              <form method="post">
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="school_id" value="<?= $schoolId ?>">
                <input type="hidden" name="user_id" value="<?= $m["id"] ?>">
                <select name="school_role" class="role-select" onchange="this.form.submit()">
                  <?php foreach ($grantable as $r): ?>
                    <option value="<?= $r ?>" <?= $m["school_role"] === $r ? "selected" : "" ?>><?= e(schoolRoleLabel($r)) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
              <form method="post">
                <input type="hidden" name="action" value="remove_member">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="school_id" value="<?= $schoolId ?>">
                <input type="hidden" name="user_id" value="<?= $m["id"] ?>">
                <button type="submit" class="icon-btn" title="นำออกจากโรงเรียน" onclick="return confirm('นำ <?= e(addslashes($display)) ?> ออกจากโรงเรียน?')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </button>
              </form>
            <?php endif; ?>
          </div>
          <?php if ($infoEditable): ?>
          <form method="post" id="edit-<?= $m["id"] ?>" class="adm-form edit-form" style="display:none;margin:0 0 12px">
            <input type="hidden" name="action" value="edit_info">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="school_id" value="<?= $schoolId ?>">
            <input type="hidden" name="user_id" value="<?= $m["id"] ?>">
            <input name="student_name" value="<?= e($m["student_name"] ?? "") ?>" placeholder="ชื่อ-นามสกุลจริง" maxlength="100" required>
            <div class="adm-row" style="grid-template-columns:1fr 1fr">
              <input name="class_room" value="<?= e($m["class_room"] ?? "") ?>" placeholder="ชั้น เช่น ม.3/2" maxlength="20">
              <input name="student_no" value="<?= e($m["student_no"] ?? "") ?>" placeholder="เลขที่" maxlength="10">
            </div>
            <button class="adm-btn primary" type="submit">บันทึกข้อมูล</button>
          </form>
          <?php endif; ?>
      </div>
    <?php }; ?>

    <?php foreach (["teacher", "council"] as $role): $list = $groups[$role]; ?>
      <div class="sec"><?= $groupTitle[$role] ?> <small><?= count($list) ?> คน</small></div>
      <div class="pg-card">
        <?php if (!$list): ?><div class="empty">ยังไม่มี<?= $groupTitle[$role] ?></div><?php endif; ?>
        <?php foreach ($list as $m) $renderMember($m); ?>
      </div>
    <?php endforeach; ?>

    <?php $studentGrades = groupByClassRoom($groups["student"], $studentNoClass); ?>
    <div class="sec"><?= $groupTitle["student"] ?> <small><?= count($groups["student"]) ?> คน</small></div>
    <?php if (!$groups["student"]): ?>
      <div class="pg-card"><div class="empty">ยังไม่มีนักเรียน</div></div>
    <?php endif; ?>
    <?php foreach ($studentGrades as $grade => $rooms): $gradeCount = array_sum(array_map("count", $rooms)); ?>
      <details class="grp grp-grade" data-key="g<?= e(md5($grade)) ?>">
        <summary>
          <span class="grp-title">ชั้น <?= e($grade) ?></span>
          <span class="cnt"><?= count($rooms) ?> ห้อง · <?= number_format($gradeCount) ?> คน</span>
        </summary>
        <div class="grp-body">
          <?php foreach ($rooms as $room => $list): ?>
            <details class="grp grp-room" data-key="r<?= e(md5($grade . "|" . $room)) ?>">
              <summary>
                <span class="grp-title"><?= $room !== "" ? "ห้อง " . e($room) : "ไม่ระบุห้อง" ?></span>
                <span class="cnt"><?= count($list) ?> คน</span>
              </summary>
              <div class="grp-body pg-card">
                <?php foreach ($list as $m) $renderMember($m); ?>
              </div>
            </details>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
    <?php if ($studentNoClass): ?>
      <details class="grp grp-grade" data-key="none">
        <summary>
          <span class="grp-title">ยังไม่ระบุชั้น</span>
          <span class="cnt"><?= count($studentNoClass) ?> คน</span>
        </summary>
        <div class="grp-body pg-card">
          <?php foreach ($studentNoClass as $m) $renderMember($m); ?>
        </div>
      </details>
    <?php endif; ?>
  </main>
  <script>
  function toggleAdd() {
    const el = document.getElementById('add-form');
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
  }
  function toggleEdit(id) {
    const el = document.getElementById('edit-' + id);
    el.style.display = el.style.display === 'none' ? 'grid' : 'none';
  }
  // Open ชั้น/ห้อง groups are remembered per school so the page comes back the same after a save.
  const OPEN_KEY = 'schoolMembersOpen-<?= $schoolId ?>';

  function saveOpenGroups() {
    if (document.querySelector('.search-box input').value.trim() !== '') return;
    const open = [...document.querySelectorAll('details.grp[open]')].map(d => d.dataset.key);
    sessionStorage.setItem(OPEN_KEY, JSON.stringify(open));
  }

  function restoreOpenGroups() {
    let open = [];
    try { open = JSON.parse(sessionStorage.getItem(OPEN_KEY) || '[]'); } catch (e) {}
    const set = new Set(open);
    document.querySelectorAll('details.grp').forEach(d => { d.open = set.has(d.dataset.key); });
  }

  function filterMembers(q) {
    q = q.trim().toLowerCase();
    document.querySelectorAll('.member-entry').forEach(entry => {
      const row = entry.querySelector('.row-item[data-search]');
      const match = !q || row.dataset.search.includes(q);
      entry.style.display = match ? '' : 'none';
      if (!match) { const f = entry.querySelector('.edit-form'); if (f) f.style.display = 'none'; }
    });
    // While searching: hide groups with no matches, open the ones that have some.
    document.querySelectorAll('details.grp').forEach(d => {
      if (!q) { d.style.display = ''; return; }
      const has = [...d.querySelectorAll('.member-entry')].some(e => e.style.display !== 'none');
      d.style.display = has ? '' : 'none';
      d.open = has;
    });
    if (!q) restoreOpenGroups();
  }

  document.querySelectorAll('details.grp').forEach(d => d.addEventListener('toggle', saveOpenGroups));
  restoreOpenGroups();
  </script>
</body>
</html>
