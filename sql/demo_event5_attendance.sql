-- DEMO DATA: fills the "รับน้อง" event (events.id = 5, school_id = 1) with check-ins
-- from the school's existing student accounts, so the attendance stats/report have
-- something to show. Each room gets its own attendance rate (about 70–95%), and
-- check-in times are spread from 10 minutes before to 30 minutes after the start.
--
-- These rows look exactly like real check-ins in the report / PDF.
-- Undo: run the DELETE at the bottom (demo rows are method='manual' with no staff).

INSERT IGNORE INTO event_attendances (event_id, user_id, method, checked_in_by, checked_in_at)
SELECT 5, u.id, 'manual', NULL,
       COALESCE(e.starts_at, NOW()) - INTERVAL 10 MINUTE + INTERVAL FLOOR(RAND() * 40 * 60) SECOND
FROM users u
JOIN events e ON e.id = 5
WHERE u.school_id = 1
  AND u.school_role IN ('student', 'council')
  AND u.is_banned = 0
  AND RAND() < 0.70 + 0.25 * (CRC32(COALESCE(u.class_room, '')) % 100) / 100;

-- Check the result:
-- SELECT COUNT(*) FROM event_attendances WHERE event_id = 5;

-- ── UNDO (remove the demo check-ins) ──
-- DELETE FROM event_attendances WHERE event_id = 5 AND method = 'manual' AND checked_in_by IS NULL;
