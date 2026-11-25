<?php
require_once "_admin.php";
require_once "_school.php";

$msg = "";
$msgType = "good";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!csrf_verify()) redirect("admin_users.php");
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
        } else { $msg = "ข้อมูลไม่ถูกต้อง"; $msgType = "bad"; }
    }

    if ($action === "toggle_ban") {
        $targetUserId = intval($_POST["user_id"] ?? 0);
        if ($targetUserId > 0 && $targetUserId === intval($_SESSION["user_id"])) {
            $msg = "ไม่สามารถระงับบัญชีตัวเองได้";
            $msgType = "bad";
        } elseif ($targetUserId > 0) {
            $stmt = $conn->prepare("UPDATE users SET is_banned = 1 - is_banned WHERE id=?");
            $stmt->bind_param("i", $targetUserId);
            $stmt->execute();

            // Force logout everywhere: drop any remember-me sessions for this user.
            $stmt = $conn->prepare("DELETE FROM remember_tokens WHERE user_id=?");
            $stmt->bind_param("i", $targetUserId);
            $stmt->execute();

            $msg = "อัพเดทสถานะผู้ใช้สำเร็จ";
        }
    }

    if ($action === "set_admin") {
        $targetUserId = intval($_POST["user_id"] ?? 0);
        $makeAdmin    = ($_POST["make_admin"] ?? "") === "1";
        if ($targetUserId > 0 && $targetUserId === intval($_SESSION["user_id"])) {
            $msg = "ไม่สามารถเปลี่ยนสิทธิ์แอดมินของตัวเองได้";
            $msgType = "bad";
        } elseif ($targetUserId > 0) {
            if ($makeAdmin) {
                $newRole = "admin";
            } else {
                // Back to shop owner if they still own a place, otherwise a normal user.
                $stmt = $conn->prepare("SELECT 1 FROM places WHERE owner_user_id=? LIMIT 1");
                $stmt->bind_param("i", $targetUserId);
                $stmt->execute();
                $newRole = $stmt->get_result()->fetch_row() ? "shop" : "user";
            }
            $stmt = $conn->prepare("UPDATE users SET role=? WHERE id=?");
            $stmt->bind_param("si", $newRole, $targetUserId);
            $stmt->execute();
            $msg = $makeAdmin ? "ตั้งเป็นแอดมินสำเร็จ" : "ปลดสิทธิ์แอดมินสำเร็จ";
        } else { $msg = "ข้อมูลไม่ถูกต้อง"; $msgType = "bad"; }
    }

    if ($action === "set_school") {
        $targetUserId = intval($_POST["user_id"] ?? 0);
        $schoolId     = intval($_POST["school_id"] ?? 0);
        $schoolRole   = $_POST["school_role"] ?? "student";
        if ($targetUserId > 0 && ($schoolId === 0 || getSchool($conn, $schoolId))) {
            setUserSchool($conn, $targetUserId, $schoolId ?: null, $schoolRole);
            $msg = "อัพเดทโรงเรียนและบทบาทสำเร็จ";
        } else { $msg = "ข้อมูลไม่ถูกต้อง"; $msgType = "bad"; }
    }
}

$users = $conn->query("
    SELECT u.id,u.name,u.email,u.role,u.points,u.avatar_url,u.is_banned,u.school_id,u.school_role,
           u.student_name,u.class_room,u.student_no,s.name AS school_name
    FROM users u LEFT JOIN schools s ON s.id = u.school_id
    ORDER BY u.points DESC
")->fetch_all(MYSQLI_ASSOC);
$schools = getAllSchools($conn);

// Group: ผู้ใช้งานทั่วไป (no school) | โรงเรียน → ชั้น → ห้อง
$generalUsers = [];
$schoolGroups = [];
foreach ($schools as $sc) {
    $schoolGroups[intval($sc["id"])] = ["school" => $sc, "grades" => [], "noclass" => [], "count" => 0];
}
foreach ($users as $u) {
    $sid = intval($u["school_id"] ?? 0);
    if (!$sid || !isset($schoolGroups[$sid])) { $generalUsers[] = $u; continue; }
    $schoolGroups[$sid]["count"]++;
    [$grade, $room] = splitClassRoom($u["class_room"] ?? "");
    if ($grade === null) { $schoolGroups[$sid]["noclass"][] = $u; continue; }
    $schoolGroups[$sid]["grades"][$grade][$room ?? ""][] = $u;
}
$byStudentNo = function ($a, $b) {
    $na = intval($a["student_no"] ?? 0) ?: PHP_INT_MAX;
    $nb = intval($b["student_no"] ?? 0) ?: PHP_INT_MAX;
    return $na <=> $nb ?: strcmp($a["student_name"] ?: $a["name"], $b["student_name"] ?: $b["name"]);
};
$staffFirst = function ($a, $b) {
    $rank = ["teacher" => 0, "council" => 1, "student" => 2];
    return ($rank[$a["school_role"] ?? ""] ?? 3) <=> ($rank[$b["school_role"] ?? ""] ?? 3) ?: strcmp($a["name"], $b["name"]);
};
foreach ($schoolGroups as &$g) {
    uksort($g["grades"], "strnatcmp");
    foreach ($g["grades"] as &$rooms) {
        uksort($rooms, "strnatcmp");
        foreach ($rooms as &$list) usort($list, $byStudentNo);
        unset($list);
    }
    unset($rooms);
    usort($g["noclass"], $staffFirst);
}
unset($g);
// Schools with members first, then by name (getAllSchools already sorts by name).
uasort($schoolGroups, fn($a, $b) => ($b["count"] > 0) <=> ($a["count"] > 0));
$schoolUserCount = count($users) - count($generalUsers);
$selfId = intval($_SESSION["user_id"]);

function renderUserEntry($u, $schools, $selfId, $inSchool) {
    $isSelf = intval($u["id"]) === $selfId;
    $search = mb_strtolower(implode(" ", [$u["name"], $u["email"], $u["student_name"] ?? "", $u["class_room"] ?? "", $u["school_name"] ?? ""]), "UTF-8");
    ?>
    <div class="user-entry" data-search="<?= e($search) ?>">
    <div class="row-item <?= !empty($u["is_banned"]) ? 'banned' : '' ?>">
      <?php if ($inSchool && !empty($u["student_no"])): ?>
        <div class="no-badge" title="เลขที่"><?= e($u["student_no"]) ?></div>
      <?php endif; ?>
      <div class="avatar">
        <?php if (!empty($u["avatar_url"])): ?>
          <img src="<?= e($u["avatar_url"]) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <?= e(mb_substr($u["name"], 0, 1, "UTF-8")) ?>
        <?php endif; ?>
      </div>
      <div class="row-info">
        <strong><?= e($inSchool && !empty($u["student_name"]) ? $u["student_name"] : $u["name"]) ?><?= !empty($u["is_banned"]) ? ' · ถูกระงับ' : '' ?></strong>
        <span><?= e($u["email"]) ?></span>
        <?php if ($inSchool && $u["school_role"] && $u["school_role"] !== "student"): ?>
          <span style="display:block;color:#4f46e5"><?= e(schoolRoleLabel($u["school_role"])) ?></span>
        <?php endif; ?>
      </div>
      <div class="pts">
        คะแนนปัจจุบัน
        <b>★ <?= number_format($u["points"]) ?></b>
      </div>
      <button type="button" class="pill-btn" onclick="quickAddPoints(<?= $u["id"] ?>)">เพิ่มคะแนน</button>
      <?php // every user, admins included, can be given a school + role
        $shortRole = ["teacher" => "ครู", "council" => "สภา", "student" => "นักเรียน"][$u["school_role"] ?? ""] ?? "";
        $roleColor = ["teacher" => "#db2777", "council" => "#4f46e5", "student" => "#0ea5e9"][$u["school_role"] ?? ""] ?? null;
        // A council role without a school (left over from the old global สภา flag) can't do anything yet.
        $orphan = !$u["school_id"] && $shortRole !== ""; ?>
        <button type="button" class="pill-btn" title="โรงเรียนและบทบาท" onclick="toggleSchoolForm(<?= $u["id"] ?>)"
                style="<?= $u["school_id"] && $roleColor ? "background:$roleColor;color:#fff;border-color:$roleColor" : ($orphan ? "background:#fff7ed;color:#c2410c;border-color:#fdba74" : "background:#fff;color:#4f46e5;border-color:#c7d2fe") ?>">
          <?= $u["school_id"] ? e($shortRole) : ($orphan ? e($shortRole) . " · เลือกโรงเรียน" : "+ โรงเรียน") ?>
        </button>
      <?php $isAdmin = $u["role"] === "admin"; ?>
      <?php if ($isSelf): ?>
        <span class="admin-pill on" title="บัญชีของคุณ">แอดมิน</span>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="set_admin">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
          <input type="hidden" name="make_admin" value="<?= $isAdmin ? "0" : "1" ?>">
          <button type="submit" class="admin-pill <?= $isAdmin ? 'on' : '' ?>"
                  title="<?= $isAdmin ? 'กดเพื่อปลดสิทธิ์แอดมิน' : 'กดเพื่อตั้งเป็นแอดมินเว็บ' ?>"
                  onclick="return confirm('<?= $isAdmin ? 'ปลดสิทธิ์แอดมินของ' : 'ตั้ง' ?> <?= e(addslashes($u["name"])) ?> <?= $isAdmin ? '?' : 'เป็นแอดมินเว็บ?\nแอดมินจะจัดการได้ทุกส่วนของเว็บ รวมถึงทุกโรงเรียน' ?>')">
            <?= $isAdmin ? "แอดมิน" : "+ แอดมิน" ?>
          </button>
        </form>
      <?php endif; ?>
      <?php if (!$isSelf): ?>
        <form method="post">
          <input type="hidden" name="action" value="toggle_ban">
          <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
          <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
          <button type="submit" class="ban-pill <?= !empty($u["is_banned"]) ? 'unban' : 'ban' ?>"
                  onclick="return confirm('<?= !empty($u["is_banned"]) ? 'ปลดระงับ' : 'ระงับ' ?>บัญชี <?= e(addslashes($u["name"])) ?>?')">
            <?= !empty($u["is_banned"]) ? "ปลดระงับ" : "ระงับ" ?>
          </button>
        </form>
      <?php endif; ?>
    </div>
    <form method="post" id="sform-<?= $u["id"] ?>" class="adm-form school-form" style="display:none;margin:0 0 14px">
      <input type="hidden" name="action" value="set_school">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="user_id" value="<?= $u["id"] ?>">
      <div class="adm-row">
        <select name="school_id">
          <option value="0">— ไม่มีโรงเรียน —</option>
          <?php foreach ($schools as $sc): ?>
            <option value="<?= $sc["id"] ?>" <?= intval($u["school_id"]) === intval($sc["id"]) ? "selected" : "" ?>><?= e($sc["name"]) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="school_role">
          <?php foreach (SCHOOL_ROLES as $r => $label): ?>
            <option value="<?= $r ?>" <?= ($u["school_role"] ?? "student") === $r ? "selected" : "" ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button class="adm-btn primary" type="submit">บันทึกโรงเรียน / บทบาท</button>
      <?php if (!$schools): ?><span style="font-size:12px;color:#94a3b8">ยังไม่มีโรงเรียน <a href="admin_schools.php">เพิ่มโรงเรียน</a></span><?php endif; ?>
    </form>
    </div>
    <?php
}
$logs  = $conn->query("SELECT pl.*,u.name AS uname FROM point_logs pl JOIN users u ON u.id=pl.user_id ORDER BY pl.id DESC LIMIT 15")->fetch_all(MYSQLI_ASSOC);
$autoOpen = $_GET["open"] ?? "";
?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <link rel="apple-touch-icon" href="assets/images/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title>จัดการผู้ใช้ | ล่าพิกัด.com</title>
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

    .pg-card { background:#fff;border-radius:20px;padding:8px 18px;box-shadow:0 2px 10px rgba(15,23,42,.06); }

    .search-box { display:flex;align-items:center;gap:8px;background:#f8fafc;border-radius:12px;padding:10px 14px;margin-bottom:14px; }
    .search-box svg { width:16px;height:16px;color:#94a3b8;flex-shrink:0; }
    .search-box input { border:none;background:none;flex:1;font-family:inherit;font-size:13px;outline:none; }

    .row-item { display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid #f1f5f9; }
    .row-item:last-child { border-bottom:none; }

    .row-item .avatar {
      width:44px;height:44px;border-radius:50%;overflow:hidden;
      background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;
      display:flex;align-items:center;justify-content:center;font-weight:700;flex-shrink:0;
    }
    .row-info { flex:1;min-width:0; }
    .row-info strong { display:block;font-size:14px;font-weight:600;color:#0f172a; }
    .row-info span   { font-size:11.5px;color:#94a3b8; }

    .pts { text-align:right;font-size:10.5px;color:#64748b;white-space:nowrap; }
    .pts b { display:block;font-size:13px;color:#0f172a;margin-top:1px; }

    .row-item.banned { opacity:.55; }
    .ban-pill {
      font-size:10.5px;font-weight:600;padding:6px 12px;border-radius:99px;
      border:1.5px solid;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0;
    }
    .ban-pill.ban   { background:#fff;color:#e11d48;border-color:#fecdd3; }
    .ban-pill.unban { background:#fff1f2;color:#e11d48;border-color:#e11d48; }

    .pill-btn {
      font-size:11.5px;font-weight:600;background:#eff6ff;color:#2563eb;
      border:1.5px solid #bfdbfe;border-radius:99px;padding:7px 12px;cursor:pointer;
      font-family:inherit;white-space:nowrap;flex-shrink:0;
    }

    .admin-pill {
      font-size:11.5px;font-weight:600;padding:7px 12px;border-radius:99px;border:1.5px solid #fcd34d;
      background:#fff;color:#b45309;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0;
    }
    .admin-pill.on { background:#f59e0b;color:#fff;border-color:#f59e0b; }
    span.admin-pill { cursor:default; }

    .no-badge {
      min-width:28px;height:28px;border-radius:8px;background:#f1f5f9;color:#475569;
      display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;flex-shrink:0;
    }

    .cat-tabs { display:flex;gap:6px;background:#e2e8f0;border-radius:999px;padding:4px;margin-bottom:14px; }
    .cat-tab {
      flex:1;border:none;background:none;border-radius:999px;padding:10px 12px;cursor:pointer;
      font-family:inherit;font-size:14px;font-weight:600;color:#475569;
    }
    .cat-tab.active { background:#fff;color:#2563eb;box-shadow:0 1px 4px rgba(15,23,42,.1); }
    .cat-tab .cnt { font-size:11.5px;font-weight:500;color:#94a3b8;margin-left:4px; }
    .cat-panel.search-hidden { display:none!important; }
    .cat-panel + .cat-panel { margin-top:14px; }

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
    .grp-title small { display:block;font-size:11.5px;font-weight:400;color:#94a3b8; }
    .grp .cnt { font-size:12px;color:#64748b;white-space:nowrap; }
    .grp > .grp-body { padding:10px 0 0 14px; }
    .grp > .grp-body.pg-card { margin:8px 0 0 14px;padding:8px 18px; }
    .grp-grade > summary { background:#f8fafc;box-shadow:none;border:1px solid #e2e8f0; }
    .grp-room > summary  { background:transparent;box-shadow:none;border:1px dashed #cbd5e1;padding:9px 14px; }
    .grp-room .grp-title { font-size:13.5px; }
    .grp-empty { text-align:center;padding:16px;color:#94a3b8;font-size:13px; }

    .adm-form { display:grid;gap:10px;margin-top:14px; }
    .adm-row  { display:grid;grid-template-columns:1fr 1fr;gap:10px; }
  </style>
</head>
<body>
  <main class="app" style="max-width:100%;background:#f1f5f9">
    <?php include "includes/topbar.php"; ?>

    <a href="admin.php" class="back-btn">
      <svg viewBox="0 0 24 24"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
      กลับ
    </a>

    <div class="pg-head">
      <h1>เพิ่มคะแนนให้ผู้ใช้</h1>
      <button type="button" class="adm-btn primary" onclick="revealAndScroll('add-points-form')">+ เพิ่มคะแนน</button>
    </div>

    <?php if ($msg): ?>
      <div class="adm-alert <?= $msgType ?>"><?= e($msg) ?></div>
    <?php endif; ?>

    <div class="search-box">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="user-search" placeholder="ค้นหาชื่อ / อีเมล / ชั้น เช่น ม.5/2" oninput="filterUsers(this.value)">
    </div>

    <div id="add-points-form" class="pg-card" style="display:none;margin-bottom:14px">
      <form method="post" class="adm-form">
        <input type="hidden" name="action" value="add_points">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <select name="user_id" required>
          <option value="">เลือกผู้ใช้</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= $u["id"] ?>"><?= e($u["name"]) ?> · <?= e($u["email"]) ?> · <?= $u["points"] ?> คะแนน</option>
          <?php endforeach; ?>
        </select>
        <div class="adm-row">
          <input name="points" type="number" placeholder="คะแนน เช่น 50 / -20" required>
          <input name="reason" placeholder="เหตุผล">
        </div>
        <button class="adm-btn primary" type="submit">บันทึกคะแนน</button>
      </form>
    </div>

    <div class="cat-tabs" role="tablist">
      <button type="button" class="cat-tab" data-tab="general" onclick="showTab('general')">
        ผู้ใช้งานทั่วไป <span class="cnt"><?= number_format(count($generalUsers)) ?></span>
      </button>
      <button type="button" class="cat-tab" data-tab="schools" onclick="showTab('schools')">
        โรงเรียน <span class="cnt"><?= number_format($schoolUserCount) ?></span>
      </button>
    </div>

    <div id="users-list">
      <section class="cat-panel" data-panel="general">
        <div class="pg-card">
          <?php foreach ($generalUsers as $u) renderUserEntry($u, $schools, $selfId, false); ?>
          <?php if (!$generalUsers): ?><div class="grp-empty">ไม่มีผู้ใช้งานทั่วไป</div><?php endif; ?>
        </div>
      </section>

      <section class="cat-panel" data-panel="schools">
        <?php foreach ($schoolGroups as $sid => $g): $sc = $g["school"]; ?>
          <details class="grp grp-school" data-key="s<?= $sid ?>">
            <summary>
              <?= schoolLogoHtml($sc, 32) ?>
              <span class="grp-title"><?= e($sc["name"]) ?>
                <?php $place = schoolPlaceText($sc); if ($place): ?><small><?= e($place) ?></small><?php endif; ?>
              </span>
              <span class="cnt"><?= number_format($g["count"]) ?> คน</span>
            </summary>
            <div class="grp-body">
              <?php if ($g["count"] === 0): ?><div class="grp-empty">ยังไม่มีสมาชิก</div><?php endif; ?>

              <?php foreach ($g["grades"] as $grade => $rooms):
                $gradeCount = array_sum(array_map("count", $rooms)); ?>
                <details class="grp grp-grade" data-key="s<?= $sid ?>-g<?= e(md5($grade)) ?>">
                  <summary>
                    <span class="grp-title">ชั้น <?= e($grade) ?></span>
                    <span class="cnt"><?= count($rooms) ?> ห้อง · <?= number_format($gradeCount) ?> คน</span>
                  </summary>
                  <div class="grp-body">
                    <?php foreach ($rooms as $room => $list): ?>
                      <details class="grp grp-room" data-key="s<?= $sid ?>-r<?= e(md5($grade . "|" . $room)) ?>">
                        <summary>
                          <span class="grp-title"><?= $room !== "" ? "ห้อง " . e($room) : "ไม่ระบุห้อง" ?></span>
                          <span class="cnt"><?= count($list) ?> คน</span>
                        </summary>
                        <div class="grp-body pg-card">
                          <?php foreach ($list as $u) renderUserEntry($u, $schools, $selfId, true); ?>
                        </div>
                      </details>
                    <?php endforeach; ?>
                  </div>
                </details>
              <?php endforeach; ?>

              <?php if ($g["noclass"]): ?>
                <details class="grp grp-grade" data-key="s<?= $sid ?>-none">
                  <summary>
                    <span class="grp-title">ครู / ยังไม่ระบุชั้น</span>
                    <span class="cnt"><?= number_format(count($g["noclass"])) ?> คน</span>
                  </summary>
                  <div class="grp-body pg-card">
                    <?php foreach ($g["noclass"] as $u) renderUserEntry($u, $schools, $selfId, true); ?>
                  </div>
                </details>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
        <?php if (!$schoolGroups): ?>
          <div class="pg-card"><div class="grp-empty">ยังไม่มีโรงเรียน <a href="admin_schools.php">เพิ่มโรงเรียน</a></div></div>
        <?php endif; ?>
      </section>

      <div id="users-empty" class="pg-card" style="display:none;text-align:center;padding:20px;color:#94a3b8;font-size:13px">ไม่พบผู้ใช้ที่ค้นหา</div>
    </div>

    <form method="post" id="qform" style="display:none">
      <input type="hidden" name="action" value="add_points">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <input type="hidden" name="user_id" id="quser">
      <input type="hidden" name="reason" value="เพิ่มคะแนนด่วนโดยแอดมิน">
      <input type="hidden" name="points" id="qpoints">
    </form>

    <?php if (!empty($logs)): ?>
    <div class="pg-head" style="margin-top:22px">
      <h1 style="font-size:16px">ประวัติคะแนนล่าสุด</h1>
    </div>
    <div class="pg-card">
      <?php foreach ($logs as $log): ?>
        <div class="row-item">
          <div class="row-info">
            <strong><?= e($log["uname"]) ?></strong>
            <span><?= e($log["reason"]) ?></span>
          </div>
          <span style="font-size:14px;font-weight:700;color:<?= $log["points"] >= 0 ? '#16a34a' : '#e11d48' ?>">
            <?= $log["points"] >= 0 ? '+' : '' ?><?= $log["points"] ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

  </main>
  <script>
  function revealAndScroll(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'block';
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function toggleSchoolForm(userId) {
    const el = document.getElementById('sform-' + userId);
    el.style.display = el.style.display === 'none' ? 'grid' : 'none';
  }

  function quickAddPoints(userId) {
    const amt = prompt('ต้องการเพิ่ม (หรือลด) กี่คะแนน?', '10');
    if (amt === null) return;
    const n = parseInt(amt, 10);
    if (!n) return;
    document.getElementById('quser').value = userId;
    document.getElementById('qpoints').value = n;
    document.getElementById('qform').submit();
  }

  // ── Category tabs + collapsible โรงเรียน → ชั้น → ห้อง groups ──
  // Tab and open groups are remembered so the page comes back the same after a save.
  const TAB_KEY = 'adminUsersTab', OPEN_KEY = 'adminUsersOpen';
  let currentTab = localStorage.getItem(TAB_KEY) || 'schools';

  function showTab(tab) {
    currentTab = tab;
    localStorage.setItem(TAB_KEY, tab);
    const searching = document.getElementById('user-search').value.trim() !== '';
    document.querySelectorAll('.cat-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.cat-panel').forEach(p => p.style.display = (searching || p.dataset.panel === tab) ? '' : 'none');
  }

  function saveOpenGroups() {
    if (document.getElementById('user-search').value.trim() !== '') return; // search opens groups temporarily
    const open = [...document.querySelectorAll('details.grp[open]')].map(d => d.dataset.key);
    sessionStorage.setItem(OPEN_KEY, JSON.stringify(open));
  }

  function restoreOpenGroups() {
    let open = [];
    try { open = JSON.parse(sessionStorage.getItem(OPEN_KEY) || '[]'); } catch (e) {}
    const set = new Set(open);
    document.querySelectorAll('details.grp').forEach(d => { d.open = set.has(d.dataset.key); });
    // Only one school? open it right away.
    const schools = document.querySelectorAll('details.grp-school');
    if (schools.length === 1 && !open.length) schools[0].open = true;
  }

  function filterUsers(q) {
    q = q.trim().toLowerCase();
    let visible = 0;
    document.querySelectorAll('#users-list .user-entry').forEach(entry => {
      const match = !q || entry.dataset.search.includes(q);
      entry.style.display = match ? '' : 'none';
      if (!match) { const sf = entry.querySelector('.school-form'); if (sf) sf.style.display = 'none'; }
      if (match) visible++;
    });

    // Hide groups with no matches; open the ones that have matches.
    document.querySelectorAll('details.grp').forEach(d => {
      if (!q) { d.style.display = ''; return; }
      const has = [...d.querySelectorAll('.user-entry')].some(e => e.style.display !== 'none');
      d.style.display = has ? '' : 'none';
      d.open = has;
    });
    document.querySelectorAll('.cat-panel').forEach(p => {
      const has = [...p.querySelectorAll('.user-entry')].some(e => e.style.display !== 'none');
      p.classList.toggle('search-hidden', !!q && !has);
    });
    if (!q) restoreOpenGroups();
    showTab(currentTab);
    document.getElementById('users-empty').style.display = visible === 0 ? 'block' : 'none';
  }

  document.querySelectorAll('details.grp').forEach(d => d.addEventListener('toggle', saveOpenGroups));
  restoreOpenGroups();
  showTab(currentTab);

  <?php if ($autoOpen === "add"): ?>
  document.addEventListener('DOMContentLoaded', () => revealAndScroll('add-points-form'));
  <?php endif; ?>
  </script>
</body>
</html>
