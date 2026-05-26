-- ============================================================
-- Migration 007: Shift Attendance
-- Replaces supervisor_attendance with self-service shift system
-- ============================================================

-- 1. Add SHIFT_ATTENDANCE to uploads.parent_type ENUM
ALTER TABLE uploads
    MODIFY COLUMN parent_type ENUM('GREEN_BELT','SITE','TASK','SHIFT_ATTENDANCE') NOT NULL;

-- 2. Create shift_attendance table
CREATE TABLE shift_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_key VARCHAR(50) NOT NULL,
    shift_date DATE NOT NULL,
    belt_id BIGINT UNSIGNED NULL,

    started_at DATETIME NULL,
    start_upload_id BIGINT UNSIGNED NULL,
    start_latitude DECIMAL(10,7) NULL,
    start_longitude DECIMAL(10,7) NULL,
    start_distance_km DECIMAL(6,2) NULL,
    start_location_flag TINYINT(1) NOT NULL DEFAULT 0,

    completed_at DATETIME NULL,
    end_upload_id BIGINT UNSIGNED NULL,
    end_latitude DECIMAL(10,7) NULL,
    end_longitude DECIMAL(10,7) NULL,
    end_distance_km DECIMAL(6,2) NULL,
    end_location_flag TINYINT(1) NOT NULL DEFAULT 0,

    has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
    start_meter_reading DECIMAL(10,1) NULL,
    start_meter_upload_id BIGINT UNSIGNED NULL,
    end_meter_reading DECIMAL(10,1) NULL,
    end_meter_upload_id BIGINT UNSIGNED NULL,

    is_late_start TINYINT(1) NOT NULL DEFAULT 0,
    is_early_end TINYINT(1) NOT NULL DEFAULT 0,

    shift_notes TEXT NULL,

    override_by_user_id BIGINT UNSIGNED NULL,
    override_reason TEXT NULL,
    override_status ENUM('PRESENT','ABSENT','HALF_DAY') NULL,

    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_shift_attendance_user_date (user_id, shift_date),
    CONSTRAINT fk_sa_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_sa_start_upload FOREIGN KEY (start_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
    CONSTRAINT fk_sa_end_upload FOREIGN KEY (end_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
    CONSTRAINT fk_sa_start_meter_upload FOREIGN KEY (start_meter_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
    CONSTRAINT fk_sa_end_meter_upload FOREIGN KEY (end_meter_upload_id) REFERENCES uploads(id) ON DELETE SET NULL,
    CONSTRAINT fk_sa_override_by FOREIGN KEY (override_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    KEY idx_sa_shift_date (shift_date),
    KEY idx_sa_role_date (role_key, shift_date),
    KEY idx_sa_belt_date (belt_id, shift_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create shift_activities table
CREATE TABLE shift_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_attendance_id BIGINT UNSIGNED NOT NULL,
    belt_id BIGINT UNSIGNED NULL,
    activity_key VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sact_shift FOREIGN KEY (shift_attendance_id)
        REFERENCES shift_attendance(id) ON DELETE CASCADE,
    CONSTRAINT fk_sact_belt FOREIGN KEY (belt_id)
        REFERENCES green_belts(id) ON DELETE RESTRICT,
    KEY idx_sact_shift_belt (shift_attendance_id, belt_id),
    KEY idx_sact_belt (belt_id),
    KEY idx_sact_activity (activity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create attendance_activity_types table
CREATE TABLE attendance_activity_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_key VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_activity_key (activity_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Seed default activity types
INSERT INTO attendance_activity_types (activity_key, label, sort_order) VALUES
    ('WATERING', 'Watering', 1),
    ('PLANTING', 'Planting', 2),
    ('PRUNING', 'Pruning / Trimming', 3),
    ('GRASS_CUTTING', 'Grass Cutting', 4),
    ('CLEANING', 'Cleaning', 5),
    ('REPAIR_WORK', 'Repair Work', 6),
    ('PEST_CONTROL', 'Pest Control', 7),
    ('LABOUR_SUPERVISION', 'Labour Supervision', 8),
    ('SITE_INSPECTION', 'Site Inspection', 9),
    ('AUTHORITY_MEETING', 'Authority Meeting', 10),
    ('MATERIAL_EQUIPMENT', 'Material / Equipment', 11);

-- 6. Add system settings
INSERT INTO system_settings (setting_key, setting_value, value_type, description) VALUES
    ('attendance_shift_start_time', '09:00', 'STRING', 'Expected shift start time (HH:MM, 24h)'),
    ('attendance_shift_end_time', '17:00', 'STRING', 'Expected shift end time (HH:MM, 24h)'),
    ('attendance_late_grace_minutes', '15', 'INTEGER', 'Grace minutes after shift start before flagging late'),
    ('attendance_early_grace_minutes', '10', 'INTEGER', 'Grace minutes before shift end before flagging early'),
    ('attendance_location_threshold_km', '3', 'INTEGER', 'GPS distance threshold in km for location soft flag');

-- 7. RBAC: Add attendance.shift module scope to GBS, HS, OPS
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'attendance.shift'
FROM roles r
WHERE r.role_key IN ('GREEN_BELT_SUPERVISOR', 'HEAD_SUPERVISOR', 'OPS_MANAGER')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- 7b. RBAC: Add shift_review + activity_types module scopes for OPS_MANAGER
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'attendance.shift_review'
FROM roles r
WHERE r.role_key = 'OPS_MANAGER'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'attendance.activity_types'
FROM roles r
WHERE r.role_key = 'OPS_MANAGER'
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- 8. Remove old supervisor_attendance module scope
DELETE rms FROM role_module_scopes rms
WHERE rms.module_key = 'green_belt.supervisor_attendance';

-- 9. Drop old table
DROP TABLE IF EXISTS supervisor_attendance;
