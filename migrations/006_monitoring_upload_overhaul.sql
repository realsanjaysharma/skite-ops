-- Migration 006: Monitoring Upload Overhaul schema changes
-- Date: 2026-05-25
-- Spec: docs/superpowers/specs/2026-05-25-monitoring-upload-overhaul-design.md

-- 1. ALTER sites — board dimensions, creative reference, last monitored tracking, spatial index
ALTER TABLE sites
  ADD COLUMN board_width_ft SMALLINT UNSIGNED NULL AFTER board_type,
  ADD COLUMN board_height_ft SMALLINT UNSIGNED NULL AFTER board_width_ft,
  ADD COLUMN creative_upload_id BIGINT UNSIGNED NULL AFTER longitude,
  ADD COLUMN last_monitored_at DATETIME NULL AFTER creative_upload_id,
  ADD COLUMN last_monitored_by_user_id BIGINT UNSIGNED NULL AFTER last_monitored_at,
  ADD CONSTRAINT fk_sites_creative_upload_id
    FOREIGN KEY (creative_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
  ADD CONSTRAINT fk_sites_last_monitored_by
    FOREIGN KEY (last_monitored_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  ADD INDEX idx_sites_lat_lng (latitude, longitude);

-- 2. ALTER site_monitoring_due_dates — completion tracking
ALTER TABLE site_monitoring_due_dates
  ADD COLUMN completed_at DATETIME NULL AFTER due_date;

-- 3. ALTER uploads — site condition observation
ALTER TABLE uploads
  ADD COLUMN site_condition ENUM('GOOD','DAMAGED','FADED','CREATIVE_MISSING','LIGHTS_OFF') NULL AFTER photo_label;

-- 4. CREATE monitoring_shifts table
CREATE TABLE monitoring_shifts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    shift_date DATE NOT NULL,
    started_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    planned_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    completed_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    unplanned_count SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_shift_date (user_id, shift_date),
    CONSTRAINT fk_shifts_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
