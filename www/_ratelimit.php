<?php
// Attempt throttling. Requires sql/add_rate_limits.sql.
//
// Counters live in the database, not the session: a brute-force script simply
// doesn't send the session cookie back, so a session counter stops nobody.
//
// Every function fails OPEN — if the table is missing (migration not run yet)
// the site keeps working instead of locking everyone out, and the reason goes
// to the error log. Check the log after deploying if a limit never seems to bite.

require_once __DIR__ . "/_bootstrap.php";

// bucket => [max attempts, window in seconds]
const RATE_LIMITS = [
    // Failed logins only; a successful one clears the counter.
    "login"         => [10, 900],    // 10 per 15 min
    // Reset mails per email address, and per IP across all addresses.
    "forgot_email"  => [3, 3600],    // 3 per hour
    "forgot_ip"     => [10, 3600],   // 10 per hour
    // Claude API calls, on top of the 5s cooldown in quest_recommend.php.
    "ai_recommend"  => [20, 3600],   // 20 per hour
];

// True when $ident has already used up $bucket's allowance.
function rateLimitExceeded($conn, $bucket, $ident) {
    [$max, $window] = RATE_LIMITS[$bucket];

    try {
        $stmt = $conn->prepare("
            SELECT COUNT(*) c FROM rate_limits
            WHERE bucket=? AND ident=? AND attempted_at > (NOW() - INTERVAL " . intval($window) . " SECOND)
        ");
        $stmt->bind_param("ss", $bucket, $ident);
        $stmt->execute();
        return intval($stmt->get_result()->fetch_assoc()["c"] ?? 0) >= $max;
    } catch (mysqli_sql_exception $e) {
        error_log("rateLimitExceeded($bucket): " . $e->getMessage());
        return false;
    }
}

// Records one attempt against $bucket for $ident.
function rateLimitHit($conn, $bucket, $ident) {
    try {
        $stmt = $conn->prepare("INSERT INTO rate_limits (bucket, ident, attempted_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ss", $bucket, $ident);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log("rateLimitHit($bucket): " . $e->getMessage());
    }
}

// Forgets $ident's attempts in $bucket — called after a success, so one good
// login doesn't leave the user half-way to a lockout.
function rateLimitClear($conn, $bucket, $ident) {
    try {
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE bucket=? AND ident=?");
        $stmt->bind_param("ss", $bucket, $ident);
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log("rateLimitClear($bucket): " . $e->getMessage());
    }
}

// Deletes rows older than every window in RATE_LIMITS. There is no cron here,
// so it runs on roughly 1 in 50 throttled requests; the table stays small.
function rateLimitSweep($conn) {
    if (random_int(1, 50) !== 1) return;

    $maxWindow = max(array_column(RATE_LIMITS, 1));
    try {
        $stmt = $conn->prepare("DELETE FROM rate_limits WHERE attempted_at < (NOW() - INTERVAL " . intval($maxWindow) . " SECOND)");
        $stmt->execute();
    } catch (mysqli_sql_exception $e) {
        error_log("rateLimitSweep: " . $e->getMessage());
    }
}

// How many seconds until $ident may try $bucket again (for the "try again in …"
// message). Returns 0 when nothing is on record.
function rateLimitRetryAfter($conn, $bucket, $ident) {
    [$max, $window] = RATE_LIMITS[$bucket];

    try {
        $stmt = $conn->prepare("
            SELECT MIN(attempted_at) oldest FROM rate_limits
            WHERE bucket=? AND ident=? AND attempted_at > (NOW() - INTERVAL " . intval($window) . " SECOND)
        ");
        $stmt->bind_param("ss", $bucket, $ident);
        $stmt->execute();
        $oldest = $stmt->get_result()->fetch_assoc()["oldest"] ?? null;
        if (!$oldest) return 0;
        return max(0, strtotime($oldest) + $window - time());
    } catch (mysqli_sql_exception $e) {
        error_log("rateLimitRetryAfter($bucket): " . $e->getMessage());
        return 0;
    }
}

// "5 นาที" / "40 วินาที" for the Thai message.
function rateLimitWaitText($seconds) {
    $minutes = (int)ceil($seconds / 60);
    return $minutes >= 1 ? $minutes . " นาที" : max(1, (int)$seconds) . " วินาที";
}
?>
