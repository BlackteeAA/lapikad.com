-- Adds an enable/disable toggle for places, used by the admin dashboard.
-- Run this once against the live database before using the new admin UI.
ALTER TABLE places ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1;
