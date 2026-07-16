<?php
require_once "_auth.php";

$userId = intval($_SESSION["user_id"]);

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrf_verify()) {
    redirect("dashboard.php");
}

$code = strtoupper(trim($_POST["code"] ?? ""));

$stmt = $conn->prepare("SELECT * FROM shop_redemptions WHERE code=?");
$stmt->bind_param("s", $code);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row || intval($row["user_id"]) !== $userId) {
    redirect("dashboard.php");
}

if ($row["status"] === "pending" && strtotime($row["expires_at"]) > time()) {
    try {
        $conn->begin_transaction();

        $upd = $conn->prepare("UPDATE shop_redemptions SET status='cancelled' WHERE id=? AND status='pending'");
        $upd->bind_param("i", $row["id"]);
        $upd->execute();

        if ($upd->affected_rows > 0) {
            $refundPts = $conn->prepare("
                UPDATE user_shop_points SET points = points + ? WHERE user_id=? AND place_id=?
            ");
            $refundPts->bind_param("iii", $row["points_cost"], $row["user_id"], $row["place_id"]);
            $refundPts->execute();

            $refundStock = $conn->prepare("UPDATE rewards SET stock = stock + 1 WHERE id=?");
            $refundStock->bind_param("i", $row["reward_id"]);
            $refundStock->execute();
        }

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
    }
}

redirect("redeem_wait.php?code=" . urlencode($code));
?>
