-- ============================================================
-- Migration 008: Green Belt Board Operations
-- Board monitoring, electrician roles, belt user assignments,
-- issue RESOLVED status, shift labour columns.
-- ============================================================

-- 1. Add board_count to green_belts
ALTER TABLE green_belts
  ADD COLUMN board_count SMALLINT UNSIGNED NULL AFTER is_hidden;

-- 2. Create belt_user_assignments table
CREATE TABLE belt_user_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    assignment_type VARCHAR(50) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bua_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bua_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    KEY idx_bua_belt_type (belt_id, assignment_type),
    KEY idx_bua_user_type (user_id, assignment_type),
    KEY idx_bua_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create board_monitoring_reports table
CREATE TABLE board_monitoring_reports (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    belt_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    report_date DATE NOT NULL,
    status ENUM('ALL_OK','ALL_OFF','PARTIAL_OFF') NOT NULL,
    off_count SMALLINT UNSIGNED NULL,
    total_boards SMALLINT UNSIGNED NOT NULL,
    gps_latitude DECIMAL(10,7) NULL,
    gps_longitude DECIMAL(10,7) NULL,
    issue_id BIGINT UNSIGNED NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bmr_belt_id FOREIGN KEY (belt_id) REFERENCES green_belts(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bmr_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_bmr_issue_id FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE SET NULL,
    UNIQUE KEY uq_bmr_belt_date_user (belt_id, report_date, user_id),
    KEY idx_bmr_report_date (report_date),
    KEY idx_bmr_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Add BOARD_MONITORING to uploads.parent_type ENUM
ALTER TABLE uploads MODIFY parent_type
  ENUM('GREEN_BELT','SITE','TASK','SHIFT_ATTENDANCE','BOARD_MONITORING') NOT NULL;

-- 5. Add RESOLVED to issues.status ENUM
ALTER TABLE issues MODIFY status
  ENUM('OPEN','IN_PROGRESS','RESOLVED','CLOSED') NOT NULL DEFAULT 'OPEN';

-- 6. Add resolution tracking columns to issues
ALTER TABLE issues
  ADD COLUMN resolved_by_user_id BIGINT UNSIGNED NULL AFTER closed_at,
  ADD COLUMN resolved_at DATETIME NULL AFTER resolved_by_user_id,
  ADD CONSTRAINT fk_issues_resolved_by FOREIGN KEY (resolved_by_user_id)
    REFERENCES users(id) ON DELETE SET NULL;

-- 7. Add labour columns to shift_attendance
ALTER TABLE shift_attendance
  ADD COLUMN labour_count SMALLINT UNSIGNED NULL AFTER shift_notes,
  ADD COLUMN male_count SMALLINT UNSIGNED NULL AFTER labour_count,
  ADD COLUMN female_count SMALLINT UNSIGNED NULL AFTER male_count,
  ADD COLUMN labour_variance_notes TEXT NULL AFTER female_count;

-- 8. Insert new roles
INSERT INTO roles (role_key, role_name, description, landing_module_key, is_system_role)
VALUES ('BOARD_MONITOR', 'Board Monitor', 'Green belt board lighting patrol', 'green_belt.board_monitoring', 1);

INSERT INTO roles (role_key, role_name, description, landing_module_key, is_system_role)
VALUES ('ELECTRICIAN', 'Electrician', 'Board electrical repair and maintenance', 'green_belt.board_issues', 1);

-- 9. Map both new roles to UPLOAD permission group (id=2)
INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, 2 FROM roles r WHERE r.role_key = 'BOARD_MONITOR';

INSERT INTO role_permission_mappings (role_id, permission_group_id)
SELECT r.id, 2 FROM roles r WHERE r.role_key = 'ELECTRICIAN';

-- 10. Module scope grants: BOARD_MONITOR
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, m.module_key
FROM roles r
CROSS JOIN (
    SELECT 'green_belt.board_monitoring' AS module_key
    UNION ALL SELECT 'green_belt.board_monitoring_history'
    UNION ALL SELECT 'attendance.shift'
    UNION ALL SELECT 'green_belt.my_uploads'
) m
WHERE r.role_key = 'BOARD_MONITOR';

-- 11. Module scope grants: ELECTRICIAN
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, m.module_key
FROM roles r
CROSS JOIN (
    SELECT 'green_belt.board_issues' AS module_key
    UNION ALL SELECT 'task.my_tasks'
    UNION ALL SELECT 'attendance.shift'
    UNION ALL SELECT 'green_belt.my_uploads'
) m
WHERE r.role_key = 'ELECTRICIAN';

-- 12. Module scope grants: OPS_MANAGER (3 new modules)
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, m.module_key
FROM roles r
CROSS JOIN (
    SELECT 'green_belt.board_monitoring' AS module_key
    UNION ALL SELECT 'green_belt.board_monitoring_history'
    UNION ALL SELECT 'green_belt.board_issues'
) m
WHERE r.role_key = 'OPS_MANAGER';

-- 13. Module scope grants: HEAD_SUPERVISOR (board issues read-only)
INSERT INTO role_module_scopes (role_id, module_key)
SELECT r.id, 'green_belt.board_issues'
FROM roles r
WHERE r.role_key = 'HEAD_SUPERVISOR';

-- 14. Test users
INSERT INTO users (full_name, email, password_hash, role_id, is_active)
SELECT 'Test Board Monitor', 'boardmonitor.test@skite.local',
       '$2y$10$YfGhJ4m8K7j5L2bN0cPdRO5YHxWqBaZ1lQ3vS6tU8wX0yA9dE4fGi',
       r.id, 1
FROM roles r WHERE r.role_key = 'BOARD_MONITOR';

INSERT INTO users (full_name, email, password_hash, role_id, is_active)
SELECT 'Test Electrician', 'electrician.test@skite.local',
       '$2y$10$YfGhJ4m8K7j5L2bN0cPdRO5YHxWqBaZ1lQ3vS6tU8wX0yA9dE4fGi',
       r.id, 1
FROM roles r WHERE r.role_key = 'ELECTRICIAN';

-- 15. Set board_count on sample belts for testing
UPDATE green_belts SET board_count = 6 WHERE id = 1;
UPDATE green_belts SET board_count = 4 WHERE id = 2;
UPDATE green_belts SET board_count = 8 WHERE id = 3;

-- 16. Sample belt_user_assignments
INSERT INTO belt_user_assignments (belt_id, user_id, assignment_type, start_date)
SELECT 1, u.id, 'BOARD_MONITOR', CURDATE()
FROM users u WHERE u.email = 'boardmonitor.test@skite.local';

INSERT INTO belt_user_assignments (belt_id, user_id, assignment_type, start_date)
SELECT 2, u.id, 'BOARD_MONITOR', CURDATE()
FROM users u WHERE u.email = 'boardmonitor.test@skite.local';

INSERT INTO belt_user_assignments (belt_id, user_id, assignment_type, start_date)
SELECT 1, u.id, 'ELECTRICIAN', CURDATE()
FROM users u WHERE u.email = 'electrician.test@skite.local';

INSERT INTO belt_user_assignments (belt_id, user_id, assignment_type, start_date)
SELECT 2, u.id, 'ELECTRICIAN', CURDATE()
FROM users u WHERE u.email = 'electrician.test@skite.local';
