<?php
// Gate for student-council pages ("สภานักเรียน"): manages one school's rewards
// (rewards.place_id IS NULL AND rewards.school_id = that school).
// Council/teacher members work on their own school; site admins pick a school
// with ?school_id= (or the school_id POST field).
// Sets $councilSchoolId (int, or null when an admin hasn't picked one yet) and $councilSchool.
require_once __DIR__ . "/_auth.php";
require_once __DIR__ . "/_school.php";

$councilUserId = intval($_SESSION["user_id"]);

if (isSiteAdmin()) {
    $councilSchoolId = intval($_POST["school_id"] ?? $_GET["school_id"] ?? 0) ?: null;
} elseif (isSchoolCouncil($conn, $councilUserId)) {
    $councilSchoolId = getUserSchool($conn, $councilUserId)["school_id"];
} else {
    redirect("dashboard.php");
}

$councilSchool = $councilSchoolId ? getSchool($conn, $councilSchoolId) : null;
if (!$councilSchool) $councilSchoolId = null;
?>
