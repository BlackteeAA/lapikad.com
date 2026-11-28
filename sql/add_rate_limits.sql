-- Attempt counters for rate limiting (www/_ratelimit.php).
--
-- One row per attempt. Counting rows inside a time window is enough for the
-- small volumes here, and expiring rows are swept by rateLimitSweep().
--
-- bucket : which limit, e.g. "login", "forgot", "ai_recommend"
-- ident  : what is being limited, e.g. "email:a@b.com", "ip:1.2.3.4", "user:42"

CREATE TABLE IF NOT EXISTS rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(32) NOT NULL,
  ident VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL,
  KEY idx_bucket_ident_time (bucket, ident, attempted_at),
  KEY idx_attempted_at (attempted_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
