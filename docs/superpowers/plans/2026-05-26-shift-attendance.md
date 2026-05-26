# Shift Attendance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the oversight-based supervisor attendance system with self-service photo-proof shift attendance (selfie + GPS + per-belt activities + vehicle meter readings) for GREEN_BELT_SUPERVISOR and HEAD_SUPERVISOR, with OPS_MANAGER review/override.

**Architecture:** New `ShiftAttendanceController → ShiftAttendanceService → ShiftAttendanceRepository` stack. Photos stored in the existing `uploads` table with new `SHIFT_ATTENDANCE` parent_type. Activity types managed via OPS-editable `attendance_activity_types` table. Junction table `shift_activities` links shifts to per-belt activities.

**Tech Stack:** PHP 8+ / MySQL / XAMPP. Vanilla JS SPA (hash routing). No framework. `UploadStorageService` for file handling, `UploadRepository` for upload row creation.

**Key constraints:**
- `UploadService::createUploadsForSurface()` is NOT used — attendance uploads don't fit its parent_type/surface model. Instead, use `UploadStorageService` directly for file handling + `UploadRepository` for row creation.
- Transactions only in service layer. Repositories must not manage transactions.
- Soft-delete (`is_deleted = 0`) on uploads. No soft-delete on `shift_attendance` (override model instead).
- All date/time in IST (`Asia/Kolkata`), already set globally.

---

## File Structure

### New files
| File | Responsibility |
|---|---|
| `migrations/007_shift_attendance.sql` | New tables, ENUM changes, system settings, seed data, RBAC scope updates, drop old table |
| `app/repositories/ShiftAttendanceRepository.php` | SQL for `shift_attendance` table |
| `app/repositories/AttendanceActivityRepository.php` | SQL for `shift_activities` + `attendance_activity_types` tables |
| `app/services/ShiftAttendanceService.php` | Business logic: start/complete shift, GPS validation, flags, override, summaries |
| `app/controllers/ShiftAttendanceController.php` | Request handling for all attendance routes |

### Modified files
| File | What changes |
|---|---|
| `app/services/UploadStorageService.php` | Add `'SHIFT_ATTENDANCE' => 'sa'` to prefix map |
| `config/route_registry.php` | Remove old `attendance/*` routes, add new `attendance/*` routes |
| `config/rbac.php` | Replace `green_belt.supervisor_attendance` with `attendance.shift` in module_catalog and landing_routes |
| `public/js/core/navigation.js` | Replace old nav entry with new `attendance.shift` entries |
| `public/js/views/modules.js` | Remove old `green_belt.supervisor_attendance` view, add 3 new views (`attendance.shift` my-shift, `attendance.shift_review` OPS review, `attendance.activity_types` management), update `green_belt.watering_oversight` and `governance.alert_panel` attendance references |
| `public/css/style.css` | Shift attendance card styles, calendar grid styles |
| `public/index.html` | Cache bust `?v=` on CSS and JS |
| `docs/06_schema/schema_v1_full.sql` | Add new tables, modify uploads ENUM, drop old table |

### Deleted files
| File | Reason |
|---|---|
| `app/controllers/AttendanceController.php` | Replaced by ShiftAttendanceController |
| `app/services/AttendanceService.php` | Replaced by ShiftAttendanceService |
| `app/repositories/AttendanceRepository.php` | Replaced by ShiftAttendanceRepository |

---

## Task 1: Migration — New tables, ENUM changes, seed data, RBAC

**Files:**
- Create: `migrations/007_shift_attendance.sql`
- Modify: `docs/06_schema/schema_v1_full.sql`

- [ ] **Step 1: Create migration file**

Create `migrations/007_shift_attendance.sql`:

```sql
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

-- 8. Remove old supervisor_attendance module scope
DELETE rms FROM role_module_scopes rms
WHERE rms.module_key = 'green_belt.supervisor_attendance';

-- 9. Drop old table
DROP TABLE IF EXISTS supervisor_attendance;
```

- [ ] **Step 2: Run migration**

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root skite_ops < migrations\007_shift_attendance.sql
```

Expected: no errors. Verify tables exist:

```powershell
& "C:\xampp\mysql\bin\mysql.exe" -u root skite_ops -e "SHOW TABLES LIKE 'shift_%'; SHOW TABLES LIKE 'attendance_%'; SELECT COUNT(*) AS activity_count FROM attendance_activity_types; SELECT setting_key FROM system_settings WHERE setting_key LIKE 'attendance_%';"
```

Expected: `shift_attendance`, `shift_activities`, `attendance_activity_types` tables exist. 11 activity types. 5 settings rows.

- [ ] **Step 3: Update schema doc**

Update `docs/06_schema/schema_v1_full.sql`:
1. Add the three new CREATE TABLE statements after the existing `supervisor_attendance` definition
2. Remove the `supervisor_attendance` CREATE TABLE block
3. Modify the `uploads` table `parent_type` ENUM to include `'SHIFT_ATTENDANCE'`

- [ ] **Step 4: Commit**

```
git add migrations/007_shift_attendance.sql docs/06_schema/schema_v1_full.sql
git commit -m "feat: migration 007 — shift attendance tables, RBAC, drop old attendance"
```

---

## Task 2: Repositories — ShiftAttendanceRepository + AttendanceActivityRepository

**Files:**
- Create: `app/repositories/ShiftAttendanceRepository.php`
- Create: `app/repositories/AttendanceActivityRepository.php`

- [ ] **Step 1: Create ShiftAttendanceRepository**

Create `app/repositories/ShiftAttendanceRepository.php`:

```php
<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * ShiftAttendanceRepository
 *
 * Data access for shift_attendance table.
 * One row per user per day. Tracks self-service shift check-in/out
 * with selfie uploads, GPS, vehicle meter readings, and flags.
 */
class ShiftAttendanceRepository extends BaseRepository
{
    /**
     * Find a shift by ID with user name.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT sa.*,
                    u.full_name AS user_name,
                    gb.belt_code, gb.common_name AS belt_name,
                    gb.latitude AS belt_latitude, gb.longitude AS belt_longitude,
                    ov.full_name AS override_by_name
             FROM shift_attendance sa
             INNER JOIN users u ON u.id = sa.user_id
             LEFT JOIN green_belts gb ON gb.id = sa.belt_id
             LEFT JOIN users ov ON ov.id = sa.override_by_user_id
             WHERE sa.id = ?",
            [$id]
        );
    }

    /**
     * Find today's shift for a user.
     */
    public function findByUserAndDate(int $userId, string $date): ?array
    {
        return $this->fetchOne(
            "SELECT sa.*,
                    gb.belt_code, gb.common_name AS belt_name,
                    gb.latitude AS belt_latitude, gb.longitude AS belt_longitude
             FROM shift_attendance sa
             LEFT JOIN green_belts gb ON gb.id = sa.belt_id
             WHERE sa.user_id = ? AND sa.shift_date = ?",
            [$userId, $date]
        );
    }

    /**
     * Create a new shift record (start shift). Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO shift_attendance
                (user_id, role_key, shift_date, belt_id, started_at,
                 start_upload_id, start_latitude, start_longitude,
                 start_distance_km, start_location_flag,
                 has_vehicle, start_meter_reading, start_meter_upload_id,
                 is_late_start)
             VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['user_id'],
                $data['role_key'],
                $data['shift_date'],
                $data['belt_id'],
                $data['start_upload_id'],
                $data['start_latitude'],
                $data['start_longitude'],
                $data['start_distance_km'],
                $data['start_location_flag'] ? 1 : 0,
                $data['has_vehicle'] ? 1 : 0,
                $data['start_meter_reading'],
                $data['start_meter_upload_id'],
                $data['is_late_start'] ? 1 : 0,
            ]
        );
        return (int) $this->lastInsertId();
    }

    /**
     * Complete shift — set end data.
     */
    public function completeShift(int $shiftId, array $data): bool
    {
        return $this->execute(
            "UPDATE shift_attendance
             SET completed_at = NOW(),
                 end_upload_id = ?,
                 end_latitude = ?,
                 end_longitude = ?,
                 end_distance_km = ?,
                 end_location_flag = ?,
                 end_meter_reading = ?,
                 end_meter_upload_id = ?,
                 is_early_end = ?,
                 shift_notes = ?
             WHERE id = ? AND completed_at IS NULL",
            [
                $data['end_upload_id'],
                $data['end_latitude'],
                $data['end_longitude'],
                $data['end_distance_km'],
                $data['end_location_flag'] ? 1 : 0,
                $data['end_meter_reading'],
                $data['end_meter_upload_id'],
                $data['is_early_end'] ? 1 : 0,
                $data['shift_notes'],
                $shiftId,
            ]
        );
    }

    /**
     * Set OPS override on a shift.
     */
    public function setOverride(int $shiftId, string $status, int $overrideByUserId, string $reason): bool
    {
        return $this->execute(
            "UPDATE shift_attendance
             SET override_status = ?, override_by_user_id = ?, override_reason = ?
             WHERE id = ?",
            [$status, $overrideByUserId, $reason, $shiftId]
        );
    }

    /**
     * List shifts for a month. Returns one row per user per day.
     * Used by OPS review (calendar + list).
     */
    public function getMonthlyShifts(string $month, ?string $roleFilter = null): array
    {
        $where = "sa.shift_date >= ? AND sa.shift_date < DATE_ADD(?, INTERVAL 1 MONTH)";
        $params = ["$month-01", "$month-01"];

        if ($roleFilter) {
            $where .= " AND sa.role_key = ?";
            $params[] = $roleFilter;
        }

        return $this->fetchAll(
            "SELECT sa.*,
                    u.full_name AS user_name,
                    gb.belt_code, gb.common_name AS belt_name
             FROM shift_attendance sa
             INNER JOIN users u ON u.id = sa.user_id
             LEFT JOIN green_belts gb ON gb.id = sa.belt_id
             WHERE {$where}
             ORDER BY u.full_name ASC, sa.shift_date ASC",
            $params
        );
    }

    /**
     * Get all users who should have shift records for attendance tracking.
     * Returns users with roles that use shift attendance.
     */
    public function getShiftEligibleUsers(?string $roleFilter = null): array
    {
        $where = "u.is_active = 1 AND r.role_key IN ('GREEN_BELT_SUPERVISOR','HEAD_SUPERVISOR')";
        $params = [];

        if ($roleFilter) {
            $where = "u.is_active = 1 AND r.role_key = ?";
            $params[] = $roleFilter;
        }

        return $this->fetchAll(
            "SELECT u.id AS user_id, u.full_name, r.role_key
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE {$where}
             ORDER BY u.full_name ASC",
            $params
        );
    }

    /**
     * Get monthly summary per user: total present, absent, flagged days.
     */
    public function getMonthlySummaryByUser(string $month, ?string $roleFilter = null): array
    {
        $where = "sa.shift_date >= ? AND sa.shift_date < DATE_ADD(?, INTERVAL 1 MONTH)";
        $params = ["$month-01", "$month-01"];

        if ($roleFilter) {
            $where .= " AND sa.role_key = ?";
            $params[] = $roleFilter;
        }

        return $this->fetchAll(
            "SELECT sa.user_id,
                    u.full_name AS user_name,
                    sa.role_key,
                    COUNT(*) AS total_shifts,
                    SUM(CASE WHEN sa.completed_at IS NOT NULL THEN 1 ELSE 0 END) AS completed_shifts,
                    SUM(CASE WHEN sa.completed_at IS NULL THEN 1 ELSE 0 END) AS incomplete_shifts,
                    SUM(sa.is_late_start) AS late_starts,
                    SUM(sa.is_early_end) AS early_ends,
                    SUM(sa.start_location_flag) AS location_flags,
                    SUM(CASE WHEN sa.override_status IS NOT NULL THEN 1 ELSE 0 END) AS overridden,
                    SUM(CASE WHEN sa.has_vehicle = 1 AND sa.end_meter_reading IS NOT NULL
                         THEN sa.end_meter_reading - sa.start_meter_reading ELSE 0 END) AS total_km
             FROM shift_attendance sa
             INNER JOIN users u ON u.id = sa.user_id
             WHERE {$where}
             GROUP BY sa.user_id, u.full_name, sa.role_key
             ORDER BY u.full_name ASC",
            $params
        );
    }

    /**
     * Get monthly summary per belt: which supervisors worked there.
     */
    public function getMonthlySummaryByBelt(string $month): array
    {
        return $this->fetchAll(
            "SELECT sact.belt_id,
                    gb.belt_code, gb.common_name AS belt_name,
                    COUNT(DISTINCT sa.user_id) AS supervisor_count,
                    COUNT(DISTINCT sa.shift_date) AS days_worked,
                    GROUP_CONCAT(DISTINCT sact.activity_key ORDER BY sact.activity_key) AS activities
             FROM shift_activities sact
             INNER JOIN shift_attendance sa ON sa.id = sact.shift_attendance_id
             INNER JOIN green_belts gb ON gb.id = sact.belt_id
             WHERE sa.shift_date >= ? AND sa.shift_date < DATE_ADD(?, INTERVAL 1 MONTH)
               AND sact.belt_id IS NOT NULL
             GROUP BY sact.belt_id, gb.belt_code, gb.common_name
             ORDER BY gb.belt_code ASC",
            ["$month-01", "$month-01"]
        );
    }
}
```

- [ ] **Step 2: Create AttendanceActivityRepository**

Create `app/repositories/AttendanceActivityRepository.php`:

```php
<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * AttendanceActivityRepository
 *
 * Data access for shift_activities (junction) and
 * attendance_activity_types (master) tables.
 */
class AttendanceActivityRepository extends BaseRepository
{
    // ─── Activity Types (Master) ───────────────────────────────────

    /**
     * List all activity types. If activeOnly=true, filter by is_active=1.
     */
    public function getActivityTypes(bool $activeOnly = true): array
    {
        $where = $activeOnly ? 'WHERE is_active = 1' : '';
        return $this->fetchAll(
            "SELECT * FROM attendance_activity_types {$where} ORDER BY sort_order ASC, label ASC",
            []
        );
    }

    /**
     * Find activity type by key.
     */
    public function findActivityTypeByKey(string $key): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM attendance_activity_types WHERE activity_key = ?",
            [$key]
        );
    }

    /**
     * Create or update an activity type. Returns the ID.
     */
    public function saveActivityType(array $data): int
    {
        if (!empty($data['id'])) {
            $this->execute(
                "UPDATE attendance_activity_types
                 SET label = ?, sort_order = ?, is_active = ?
                 WHERE id = ?",
                [$data['label'], $data['sort_order'], $data['is_active'] ? 1 : 0, $data['id']]
            );
            return (int) $data['id'];
        }

        $this->execute(
            "INSERT INTO attendance_activity_types (activity_key, label, sort_order, is_active)
             VALUES (?, ?, ?, ?)",
            [$data['activity_key'], $data['label'], $data['sort_order'], $data['is_active'] ? 1 : 0]
        );
        return (int) $this->lastInsertId();
    }

    // ─── Shift Activities (Junction) ───────────────────────────────

    /**
     * Insert activities for a completed shift.
     * $activities is an array of ['belt_id' => int|null, 'activity_key' => string]
     */
    public function insertShiftActivities(int $shiftAttendanceId, array $activities): void
    {
        $sql = "INSERT INTO shift_activities (shift_attendance_id, belt_id, activity_key) VALUES (?, ?, ?)";
        $seen = [];

        foreach ($activities as $act) {
            $beltId = $act['belt_id'] ?? null;
            $key = $act['activity_key'];
            $dedupKey = ($beltId ?? 'null') . ':' . $key;

            if (isset($seen[$dedupKey])) {
                continue; // skip duplicates
            }
            $seen[$dedupKey] = true;

            $this->execute($sql, [$shiftAttendanceId, $beltId, $key]);
        }
    }

    /**
     * Get activities for a specific shift, grouped by belt.
     */
    public function getActivitiesByShift(int $shiftAttendanceId): array
    {
        return $this->fetchAll(
            "SELECT sact.*, gb.belt_code, gb.common_name AS belt_name,
                    aat.label AS activity_label
             FROM shift_activities sact
             LEFT JOIN green_belts gb ON gb.id = sact.belt_id
             LEFT JOIN attendance_activity_types aat ON aat.activity_key = sact.activity_key
             WHERE sact.shift_attendance_id = ?
             ORDER BY gb.belt_code ASC, aat.sort_order ASC",
            [$shiftAttendanceId]
        );
    }

    /**
     * Get activity summary for a belt in a month.
     */
    public function getBeltActivitySummary(int $beltId, string $month): array
    {
        return $this->fetchAll(
            "SELECT sact.activity_key,
                    aat.label AS activity_label,
                    COUNT(*) AS day_count
             FROM shift_activities sact
             INNER JOIN shift_attendance sa ON sa.id = sact.shift_attendance_id
             LEFT JOIN attendance_activity_types aat ON aat.activity_key = sact.activity_key
             WHERE sact.belt_id = ?
               AND sa.shift_date >= ?
               AND sa.shift_date < DATE_ADD(?, INTERVAL 1 MONTH)
             GROUP BY sact.activity_key, aat.label
             ORDER BY day_count DESC",
            [$beltId, "$month-01", "$month-01"]
        );
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\xampp\php\php.exe" -l app\repositories\ShiftAttendanceRepository.php
& "C:\xampp\php\php.exe" -l app\repositories\AttendanceActivityRepository.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```
git add app/repositories/ShiftAttendanceRepository.php app/repositories/AttendanceActivityRepository.php
git commit -m "feat: ShiftAttendanceRepository + AttendanceActivityRepository"
```

---

## Task 3: Service — ShiftAttendanceService

**Files:**
- Create: `app/services/ShiftAttendanceService.php`
- Modify: `app/services/UploadStorageService.php` (add prefix map entry)

- [ ] **Step 1: Add SHIFT_ATTENDANCE prefix to UploadStorageService**

In `app/services/UploadStorageService.php`, find the `$prefixMap` array inside `storeValidatedFile()` (around line 147):

```php
        $prefixMap = [
            'GREEN_BELT' => 'gb',
            'SITE' => 'site',
            'TASK' => 'task',
        ];
```

Change to:

```php
        $prefixMap = [
            'GREEN_BELT' => 'gb',
            'SITE' => 'site',
            'TASK' => 'task',
            'SHIFT_ATTENDANCE' => 'sa',
        ];
```

- [ ] **Step 2: Create ShiftAttendanceService**

Create `app/services/ShiftAttendanceService.php`:

```php
<?php

require_once __DIR__ . '/../repositories/ShiftAttendanceRepository.php';
require_once __DIR__ . '/../repositories/AttendanceActivityRepository.php';
require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/UploadStorageService.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/SettingsService.php';

class ShiftAttendanceService
{
    private ShiftAttendanceRepository $shiftRepo;
    private AttendanceActivityRepository $activityRepo;
    private BeltAssignmentRepository $beltAssignmentRepo;
    private UploadRepository $uploadRepo;
    private UploadStorageService $storageService;
    private AuditService $auditService;
    private SettingsService $settingsService;

    public function __construct()
    {
        $this->shiftRepo = new ShiftAttendanceRepository();
        $this->activityRepo = new AttendanceActivityRepository();
        $this->beltAssignmentRepo = new BeltAssignmentRepository();
        $this->uploadRepo = new UploadRepository();
        $this->storageService = new UploadStorageService();
        $this->auditService = new AuditService();
        $this->settingsService = new SettingsService();
    }

    // ─── My Shift (GET) ───────────────────────────────────────────

    /**
     * Get today's shift status, assigned belts (for GBS), and activity types.
     */
    public function getMyShift(int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        $belts = [];
        if ($roleKey === 'GREEN_BELT_SUPERVISOR') {
            $allAssignments = $this->beltAssignmentRepo->findByUserId('supervisor', $userId);
            $belts = array_values(array_filter($allAssignments, function ($a) {
                return $a['end_date'] === null;
            }));
        }

        $activityTypes = $this->activityRepo->getActivityTypes(true);

        $activities = [];
        if ($shift) {
            $activities = $this->activityRepo->getActivitiesByShift((int) $shift['id']);
        }

        return [
            'shift' => $shift,
            'belts' => $belts,
            'activity_types' => $activityTypes,
            'activities' => $activities,
            'settings' => [
                'shift_start_time' => $this->getSetting('attendance_shift_start_time', '09:00'),
                'shift_end_time' => $this->getSetting('attendance_shift_end_time', '17:00'),
                'late_grace_minutes' => (int) $this->getSetting('attendance_late_grace_minutes', '15'),
                'early_grace_minutes' => (int) $this->getSetting('attendance_early_grace_minutes', '10'),
            ],
        ];
    }

    // ─── Start Shift ──────────────────────────────────────────────

    /**
     * Start a shift for today. Handles selfie upload, GPS, belt validation, meter.
     */
    public function startShift(array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');

        // Check for existing shift
        $existing = $this->shiftRepo->findByUserAndDate($userId, $today);
        if ($existing) {
            return $existing; // already started — return existing
        }

        $beltId = null;
        $startDistanceKm = null;
        $startLocationFlag = false;

        // Belt validation for GREEN_BELT_SUPERVISOR
        if ($roleKey === 'GREEN_BELT_SUPERVISOR') {
            if (empty($data['belt_id'])) {
                throw new InvalidArgumentException('Belt selection is required.');
            }
            $beltId = (int) $data['belt_id'];

            // Verify belt assignment
            $assignments = $this->beltAssignmentRepo->findByUserId('supervisor', $userId);
            $activeAssignments = array_filter($assignments, function ($a) {
                return $a['end_date'] === null;
            });
            $activeBeltIds = array_column($activeAssignments, 'belt_id');

            if (!in_array($beltId, array_map('intval', $activeBeltIds), true)) {
                throw new DomainException('You are not assigned to this belt.');
            }
        }

        // Store selfie upload
        $selfieUploadId = $this->storeAttendancePhoto($rawFiles, 'files', $userId);

        // GPS distance calculation
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if ($beltId !== null && $lat !== null && $lng !== null) {
            // Get belt coordinates
            $beltData = $this->shiftRepo->findByUserAndDate($userId, $today); // won't exist yet
            // Query belt directly for GPS
            $assignments = $this->beltAssignmentRepo->findByUserId('supervisor', $userId);
            $beltRow = null;
            foreach ($assignments as $a) {
                if ((int) $a['belt_id'] === $beltId) {
                    $beltRow = $a;
                    break;
                }
            }

            // We need belt lat/lng — fetch from green_belts
            $beltGps = $this->getBeltGps($beltId);
            if ($beltGps && $beltGps['latitude'] && $beltGps['longitude']) {
                $startDistanceKm = $this->haversineKm(
                    $lat, $lng,
                    (float) $beltGps['latitude'], (float) $beltGps['longitude']
                );
                $threshold = (float) $this->getSetting('attendance_location_threshold_km', '3');
                $startLocationFlag = ($startDistanceKm > $threshold);
            }
        }

        // Late start flag
        $isLateStart = $this->isLateStart();

        // Vehicle / meter
        $hasVehicle = !empty($data['has_vehicle']);
        $startMeterReading = null;
        $startMeterUploadId = null;

        if ($hasVehicle) {
            if (!isset($data['start_meter_reading']) || $data['start_meter_reading'] === '') {
                throw new InvalidArgumentException('Start meter reading is required when vehicle is used.');
            }
            $startMeterReading = (float) $data['start_meter_reading'];
            $startMeterUploadId = $this->storeAttendancePhoto($rawFiles, 'meter_photo', $userId);
        }

        // Create shift row
        $shiftId = $this->shiftRepo->create([
            'user_id' => $userId,
            'role_key' => $roleKey,
            'shift_date' => $today,
            'belt_id' => $beltId,
            'start_upload_id' => $selfieUploadId,
            'start_latitude' => $lat,
            'start_longitude' => $lng,
            'start_distance_km' => $startDistanceKm,
            'start_location_flag' => $startLocationFlag,
            'has_vehicle' => $hasVehicle,
            'start_meter_reading' => $startMeterReading,
            'start_meter_upload_id' => $startMeterUploadId,
            'is_late_start' => $isLateStart,
        ]);

        // Update parent_id on the upload rows to point to this shift
        if ($selfieUploadId) {
            $this->uploadRepo->execute(
                "UPDATE uploads SET parent_id = ? WHERE id = ?",
                [$shiftId, $selfieUploadId]
            );
        }
        if ($startMeterUploadId) {
            $this->uploadRepo->execute(
                "UPDATE uploads SET parent_id = ? WHERE id = ?",
                [$shiftId, $startMeterUploadId]
            );
        }

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    // ─── Complete Shift ───────────────────────────────────────────

    /**
     * Complete today's shift. Handles selfie, activities, notes, meter.
     */
    public function completeShift(array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        if (!$shift) {
            throw new DomainException('No active shift found for today. Start your shift first.');
        }

        if ($shift['completed_at'] !== null) {
            throw new DomainException('Shift is already completed.');
        }

        $shiftId = (int) $shift['id'];

        // Parse activities
        $activities = json_decode($data['activities'] ?? '[]', true);
        if (!is_array($activities) || count($activities) === 0) {
            throw new InvalidArgumentException('At least one activity is required.');
        }

        // Validate activity keys exist
        $validKeys = array_column($this->activityRepo->getActivityTypes(true), 'activity_key');
        foreach ($activities as $act) {
            if (!in_array($act['activity_key'] ?? '', $validKeys, true)) {
                throw new InvalidArgumentException("Invalid activity: " . ($act['activity_key'] ?? ''));
            }
            // Validate belt_id for GBS
            if ($roleKey === 'GREEN_BELT_SUPERVISOR' && empty($act['belt_id'])) {
                throw new InvalidArgumentException('Belt is required for each activity.');
            }
        }

        // Store end selfie
        $endUploadId = $this->storeAttendancePhoto($rawFiles, 'files', $userId);

        // GPS
        $endLat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $endLng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $endDistanceKm = null;
        $endLocationFlag = false;

        $beltId = $shift['belt_id'] ? (int) $shift['belt_id'] : null;
        if ($beltId !== null && $endLat !== null && $endLng !== null) {
            $beltGps = $this->getBeltGps($beltId);
            if ($beltGps && $beltGps['latitude'] && $beltGps['longitude']) {
                $endDistanceKm = $this->haversineKm(
                    $endLat, $endLng,
                    (float) $beltGps['latitude'], (float) $beltGps['longitude']
                );
                $threshold = (float) $this->getSetting('attendance_location_threshold_km', '3');
                $endLocationFlag = ($endDistanceKm > $threshold);
            }
        }

        // Vehicle / meter
        $endMeterReading = null;
        $endMeterUploadId = null;
        if ((int) $shift['has_vehicle'] === 1) {
            if (!isset($data['end_meter_reading']) || $data['end_meter_reading'] === '') {
                throw new InvalidArgumentException('End meter reading is required.');
            }
            $endMeterReading = (float) $data['end_meter_reading'];
            if ($endMeterReading < (float) $shift['start_meter_reading']) {
                throw new InvalidArgumentException('End meter reading must be >= start reading.');
            }
            $endMeterUploadId = $this->storeAttendancePhoto($rawFiles, 'meter_photo_end', $userId);
        }

        // Early end flag
        $isEarlyEnd = $this->isEarlyEnd();
        $shiftNotes = trim($data['shift_notes'] ?? '');
        if (strlen($shiftNotes) > 500) {
            $shiftNotes = substr($shiftNotes, 0, 500);
        }

        // Transaction: update shift + insert activities
        $this->shiftRepo->beginTransaction();
        try {
            $this->shiftRepo->completeShift($shiftId, [
                'end_upload_id' => $endUploadId,
                'end_latitude' => $endLat,
                'end_longitude' => $endLng,
                'end_distance_km' => $endDistanceKm,
                'end_location_flag' => $endLocationFlag,
                'end_meter_reading' => $endMeterReading,
                'end_meter_upload_id' => $endMeterUploadId,
                'is_early_end' => $isEarlyEnd,
                'shift_notes' => $shiftNotes ?: null,
            ]);

            $this->activityRepo->insertShiftActivities($shiftId, $activities);

            $this->shiftRepo->commit();
        } catch (\Throwable $e) {
            $this->shiftRepo->rollback();
            throw $e;
        }

        // Update parent_id on upload rows
        if ($endUploadId) {
            $this->uploadRepo->execute(
                "UPDATE uploads SET parent_id = ? WHERE id = ?",
                [$shiftId, $endUploadId]
            );
        }
        if ($endMeterUploadId) {
            $this->uploadRepo->execute(
                "UPDATE uploads SET parent_id = ? WHERE id = ?",
                [$shiftId, $endMeterUploadId]
            );
        }

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    // ─── OPS Review ───────────────────────────────────────────────

    /**
     * List shifts for a month (calendar + list data).
     */
    public function getReviewList(array $params): array
    {
        $month = $params['month'] ?? date('Y-m');
        $roleFilter = !empty($params['role_key']) ? $params['role_key'] : null;

        $shifts = $this->shiftRepo->getMonthlyShifts($month, $roleFilter);
        $eligibleUsers = $this->shiftRepo->getShiftEligibleUsers($roleFilter);

        return [
            'shifts' => $shifts,
            'eligible_users' => $eligibleUsers,
            'month' => $month,
        ];
    }

    /**
     * Get full shift detail including photos and activities.
     */
    public function getReviewDetail(int $shiftId): array
    {
        $shift = $this->shiftRepo->findById($shiftId);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $activities = $this->activityRepo->getActivitiesByShift($shiftId);

        return [
            'shift' => $shift,
            'activities' => $activities,
        ];
    }

    /**
     * OPS override on a shift.
     */
    public function overrideShift(int $shiftId, array $data, int $actorId): array
    {
        $status = $data['override_status'] ?? '';
        if (!in_array($status, ['PRESENT', 'ABSENT', 'HALF_DAY'], true)) {
            throw new InvalidArgumentException('Invalid override status.');
        }

        $reason = trim($data['override_reason'] ?? '');
        if ($reason === '') {
            throw new InvalidArgumentException('Override reason is required.');
        }

        $shift = $this->shiftRepo->findById($shiftId);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $oldValues = [
            'override_status' => $shift['override_status'],
            'override_reason' => $shift['override_reason'],
        ];

        $this->shiftRepo->beginTransaction();
        try {
            $this->shiftRepo->setOverride($shiftId, $status, $actorId, $reason);

            $this->auditService->logAction(
                $actorId,
                'SHIFT_ATTENDANCE_OVERRIDE',
                'shift_attendance',
                $shiftId,
                $oldValues,
                ['override_status' => $status, 'override_reason' => $reason],
                $reason
            );

            $this->shiftRepo->commit();
        } catch (\Throwable $e) {
            $this->shiftRepo->rollback();
            throw $e;
        }

        return $this->shiftRepo->findById($shiftId);
    }

    // ─── Monthly Summary ──────────────────────────────────────────

    /**
     * Aggregated monthly data per supervisor or per belt.
     */
    public function getMonthlySummary(array $params): array
    {
        $month = $params['month'] ?? date('Y-m');
        $groupBy = $params['group_by'] ?? 'user'; // 'user' or 'belt'
        $roleFilter = !empty($params['role_key']) ? $params['role_key'] : null;

        if ($groupBy === 'belt') {
            return [
                'group_by' => 'belt',
                'month' => $month,
                'items' => $this->shiftRepo->getMonthlySummaryByBelt($month),
            ];
        }

        return [
            'group_by' => 'user',
            'month' => $month,
            'items' => $this->shiftRepo->getMonthlySummaryByUser($month, $roleFilter),
        ];
    }

    // ─── Activity Types Management ────────────────────────────────

    public function getActivityTypes(): array
    {
        return $this->activityRepo->getActivityTypes(false);
    }

    public function saveActivityType(array $data, int $actorId): array
    {
        if (empty($data['label'])) {
            throw new InvalidArgumentException('Activity label is required.');
        }

        if (empty($data['id']) && empty($data['activity_key'])) {
            // Auto-generate key from label
            $data['activity_key'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($data['label']))));
            $data['activity_key'] = trim($data['activity_key'], '_');
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $id = $this->activityRepo->saveActivityType($data);

        return $this->activityRepo->findActivityTypeByKey($data['activity_key'] ?? '') ?: ['id' => $id];
    }

    // ─── Private Helpers ──────────────────────────────────────────

    /**
     * Store a single attendance photo. Returns the upload ID.
     * Uses UploadStorageService for file handling + direct UploadRepository insert.
     */
    private function storeAttendancePhoto(array $rawFiles, string $fieldName, int $userId): ?int
    {
        if (empty($rawFiles[$fieldName]) || empty($rawFiles[$fieldName]['tmp_name'])) {
            return null;
        }

        $fileData = $rawFiles[$fieldName];
        // Normalize single file to array format expected by storageService
        $normalized = $this->storageService->normalizeFiles($fileData);
        $validated = $this->storageService->validateFiles($normalized);

        if (empty($validated)) {
            return null;
        }

        $stored = $this->storageService->storeValidatedFile(
            $validated[0],
            'SHIFT_ATTENDANCE',
            0 // temporary parent_id — updated after shift row is created
        );

        $uploadId = $this->uploadRepo->create([
            'parent_type' => 'SHIFT_ATTENDANCE',
            'parent_id' => 0, // updated after shift row is created
            'upload_type' => 'WORK',
            'work_type' => null,
            'is_discovery_mode' => 0,
            'file_path' => $stored['file_path'],
            'original_file_name' => $stored['original_file_name'],
            'mime_type' => $stored['mime_type'],
            'file_size_bytes' => $stored['file_size_bytes'],
            'photo_label' => 'GENERAL',
            'site_condition' => null,
            'comment_text' => null,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'authority_visibility' => 'NOT_ELIGIBLE',
            'created_by_user_id' => $userId,
        ]);

        return $uploadId;
    }

    /**
     * Get green belt GPS coordinates.
     */
    private function getBeltGps(int $beltId): ?array
    {
        return $this->shiftRepo->fetchOne(
            "SELECT latitude, longitude FROM green_belts WHERE id = ?",
            [$beltId]
        );
    }

    /**
     * Haversine distance in km between two GPS points.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Check if current time is past the late-start grace window.
     */
    private function isLateStart(): bool
    {
        $startTime = $this->getSetting('attendance_shift_start_time', '09:00');
        $graceMinutes = (int) $this->getSetting('attendance_late_grace_minutes', '15');

        $deadline = strtotime("today {$startTime}") + ($graceMinutes * 60);
        return time() > $deadline;
    }

    /**
     * Check if current time is before the early-end grace window.
     */
    private function isEarlyEnd(): bool
    {
        $endTime = $this->getSetting('attendance_shift_end_time', '17:00');
        $graceMinutes = (int) $this->getSetting('attendance_early_grace_minutes', '10');

        $cutoff = strtotime("today {$endTime}") - ($graceMinutes * 60);
        return time() < $cutoff;
    }

    /**
     * Read a system setting with a fallback default.
     */
    private function getSetting(string $key, string $default): string
    {
        try {
            $settings = $this->settingsService->getAllSettings();
            foreach ($settings as $s) {
                if (($s['setting_key'] ?? '') === $key) {
                    return (string) $s['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // fall through to default
        }
        return $default;
    }
}
```

- [ ] **Step 3: Verify PHP syntax**

```powershell
& "C:\xampp\php\php.exe" -l app\services\ShiftAttendanceService.php
& "C:\xampp\php\php.exe" -l app\services\UploadStorageService.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```
git add app/services/ShiftAttendanceService.php app/services/UploadStorageService.php
git commit -m "feat: ShiftAttendanceService with GPS, flags, activities, photo upload"
```

---

## Task 4: Controller — ShiftAttendanceController

**Files:**
- Create: `app/controllers/ShiftAttendanceController.php`

- [ ] **Step 1: Create ShiftAttendanceController**

Create `app/controllers/ShiftAttendanceController.php`:

```php
<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/ShiftAttendanceService.php';

class ShiftAttendanceController extends BaseController
{
    private ShiftAttendanceService $service;

    public function __construct()
    {
        $this->service = new ShiftAttendanceService();
    }

    /**
     * GET attendance/my-shift
     * Returns today's shift status, assigned belts, activity types.
     */
    public function myShift(): void
    {
        if (!$this->requireMethod('GET')) return;
        $actor = $this->getActor();

        try {
            $result = $this->service->getMyShift($actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/start-shift
     * Start shift with selfie, belt, GPS, optional meter.
     */
    public function startShift(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->startShift($input, $_FILES, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST attendance/complete-shift
     * Complete shift with selfie, activities, notes, optional meter.
     */
    public function completeShift(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->completeShift($input, $_FILES, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET attendance/review-list
     * OPS: list shifts for a month.
     */
    public function reviewList(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $params = [
                'month' => $_GET['month'] ?? date('Y-m'),
                'role_key' => $_GET['role_key'] ?? '',
            ];
            $result = $this->service->getReviewList($params);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET attendance/review-detail
     * Full shift detail with photos and activities.
     */
    public function reviewDetail(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $shiftId = (int) ($_GET['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                throw new InvalidArgumentException('shift_id is required.');
            }
            $result = $this->service->getReviewDetail($shiftId);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/override
     * OPS override on a shift.
     */
    public function override(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $shiftId = (int) ($input['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                throw new InvalidArgumentException('shift_id is required.');
            }
            $result = $this->service->overrideShift($shiftId, $input, $actor['user_id']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET attendance/activity-types
     * List all activity types (active + inactive).
     */
    public function activityTypes(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $result = $this->service->getActivityTypes();
            Response::success(['items' => $result]);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/activity-type-save
     * Create or update an activity type.
     */
    public function activityTypeSave(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->saveActivityType($input, $actor['user_id']);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET attendance/monthly-summary
     * Aggregated monthly data.
     */
    public function monthlySummary(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $params = [
                'month' => $_GET['month'] ?? date('Y-m'),
                'group_by' => $_GET['group_by'] ?? 'user',
                'role_key' => $_GET['role_key'] ?? '',
            ];
            $result = $this->service->getMonthlySummary($params);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
```

- [ ] **Step 2: Verify PHP syntax**

```powershell
& "C:\xampp\php\php.exe" -l app\controllers\ShiftAttendanceController.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 3: Commit**

```
git add app/controllers/ShiftAttendanceController.php
git commit -m "feat: ShiftAttendanceController — all attendance routes"
```

---

## Task 5: Routes + RBAC config + delete old files

**Files:**
- Modify: `config/route_registry.php`
- Modify: `config/rbac.php`
- Delete: `app/controllers/AttendanceController.php`
- Delete: `app/services/AttendanceService.php`
- Delete: `app/repositories/AttendanceRepository.php`

- [ ] **Step 1: Update route_registry.php**

Remove the old attendance routes (lines with `attendance/list` and `attendance/mark`) and add new ones. Find and replace the `SUPERVISOR ATTENDANCE` block:

Old:
```php
    // ==========================================
    // SUPERVISOR ATTENDANCE
    // ==========================================

    'attendance/list' => [
        'controller' => 'AttendanceController',
        'method'     => 'listAttendanceRecords',
        'module_key' => 'green_belt.supervisor_attendance',
        'capability' => 'read',
    ],
    'attendance/mark' => [
        'controller' => 'AttendanceController',
        'method'     => 'markAttendance',
        'module_key' => 'green_belt.supervisor_attendance',
        // HEAD_SUPERVISOR (MANAGE group) and OPS_MANAGER both satisfy 'upload' capability.
        // Service layer enforces who can mark whose attendance and override rules.
        'capability' => 'upload',
    ],
```

New:
```php
    // ==========================================
    // SHIFT ATTENDANCE
    // ==========================================

    'attendance/my-shift' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'myShift',
        'module_key' => 'attendance.shift',
        'capability' => 'read',
    ],
    'attendance/start-shift' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'startShift',
        'module_key' => 'attendance.shift',
        'capability' => 'upload',
    ],
    'attendance/complete-shift' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'completeShift',
        'module_key' => 'attendance.shift',
        'capability' => 'upload',
    ],
    'attendance/review-list' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'reviewList',
        'module_key' => 'attendance.shift',
        'capability' => 'read',
    ],
    'attendance/review-detail' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'reviewDetail',
        'module_key' => 'attendance.shift',
        'capability' => 'read',
    ],
    'attendance/override' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'override',
        'module_key' => 'attendance.shift',
        'capability' => 'manage',
    ],
    'attendance/activity-types' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'activityTypes',
        'module_key' => 'attendance.shift',
        'capability' => 'manage',
    ],
    'attendance/activity-type-save' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'activityTypeSave',
        'module_key' => 'attendance.shift',
        'capability' => 'manage',
    ],
    'attendance/monthly-summary' => [
        'controller' => 'ShiftAttendanceController',
        'method'     => 'monthlySummary',
        'module_key' => 'attendance.shift',
        'capability' => 'read',
    ],
```

- [ ] **Step 2: Update rbac.php**

In the `module_catalog` array, replace `'green_belt.supervisor_attendance'` with `'attendance.shift'`.

In the `landing_routes` array, replace:
```php
'green_belt.supervisor_attendance' => 'attendance/list',
```
with:
```php
'attendance.shift' => 'attendance/my-shift',
```

- [ ] **Step 3: Delete old files**

```bash
git rm app/controllers/AttendanceController.php
git rm app/services/AttendanceService.php
git rm app/repositories/AttendanceRepository.php
```

- [ ] **Step 4: Run route map test**

```powershell
& "C:\xampp\php\php.exe" tests\test_frontend_route_map.php
```

If this test checks for `green_belt.supervisor_attendance`, it will need updating. If it fails, update the relevant test arrays to reference `attendance.shift` instead.

- [ ] **Step 5: Run syntax checks**

```powershell
& "C:\xampp\php\php.exe" tests\syntax_scan.php
```

Expected: All green.

- [ ] **Step 6: Commit**

```
git add config/route_registry.php config/rbac.php
git commit -m "feat: wire attendance routes, update RBAC, remove old attendance stack"
```

---

## Task 6: Frontend — navigation.js update

**Files:**
- Modify: `public/js/core/navigation.js`

- [ ] **Step 1: Replace old attendance nav entry**

In `navigation.js`, find:
```js
'green_belt.supervisor_attendance': { label: 'Attendance', icon: 'ph-user-check', route: 'attendance/list', section: 'Green Belt' },
```

Replace with:
```js
'attendance.shift': { label: 'My Shift', icon: 'ph-user-check', route: 'attendance/my-shift', section: 'Attendance' },
'attendance.shift_review': { label: 'Shift Review', icon: 'ph-calendar-check', route: 'attendance/review-list', section: 'Attendance', hidden: true },
'attendance.activity_types': { label: 'Activity Types', icon: 'ph-list-checks', route: 'attendance/activity-types', section: 'Attendance', hidden: true },
```

Note: `shift_review` and `activity_types` are hidden from sidebar — accessed via buttons on the main Attendance page. OPS sees these through navigation links rendered conditionally in JS.

- [ ] **Step 2: Verify JS syntax**

```powershell
& "C:\xampp\node_portable\node.exe" --check public\js\core\navigation.js
```

Expected: No output (clean).

- [ ] **Step 3: Commit**

```
git add public/js/core/navigation.js
git commit -m "feat: update navigation for shift attendance module"
```

---

## Task 7: Frontend — My Shift view (GREEN_BELT_SUPERVISOR + HEAD_SUPERVISOR)

**Files:**
- Modify: `public/js/views/modules.js` (remove old view ~lines 658-704, add new `attendance.shift` view)

This is the main self-service page. The largest single frontend task.

- [ ] **Step 1: Remove old `green_belt.supervisor_attendance` view**

Delete the entire `Views.register('green_belt.supervisor_attendance', { ... });` block (lines 658–704 in modules.js).

- [ ] **Step 2: Add module-scoped state variable**

Above the new `Views.register('attendance.shift', ...)` call, add:

```js
let _shiftAttendanceState = { shift: null, belts: [], activityTypes: [], activities: [], settings: {} };
```

- [ ] **Step 3: Add `attendance.shift` view**

Insert the new view registration in place of the removed block:

```js
Views.register('attendance.shift', {
  async render() {
    const data = await Api.get('attendance/my-shift');
    _shiftAttendanceState = {
      shift: data.shift,
      belts: data.belts || [],
      activityTypes: data.activity_types || [],
      activities: data.activities || [],
      settings: data.settings || {},
    };
    const shift = _shiftAttendanceState.shift;
    const belts = _shiftAttendanceState.belts;
    const settings = _shiftAttendanceState.settings;
    const roleKey = Auth.getRole();
    const isGBS = roleKey === 'GREEN_BELT_SUPERVISOR';

    // ── No shift today ──
    if (!shift) {
      let beltSelect = '';
      if (isGBS) {
        const beltOpts = belts.map(b =>
          `<option value="${b.belt_id}">${UI.escape(b.belt_code)} — ${UI.escape(b.common_name)}</option>`
        ).join('');
        beltSelect = `
          <label class="field">
            <span>Select Belt</span>
            <select id="sa-belt-select" class="form-control" required>
              ${belts.length === 1 ? beltOpts : '<option value="">Choose belt…</option>' + beltOpts}
            </select>
          </label>`;
      }

      return UI.page('My Shift', 'Start your day')
        + `<div class="card" style="max-width:480px;margin:0 auto;padding:1.5rem;">
            ${beltSelect}
            <label class="field" style="margin-top:1rem;">
              <span style="display:flex;align-items:center;gap:0.5rem;">
                <input type="checkbox" id="sa-vehicle-toggle" /> I used a vehicle today
              </span>
            </label>
            <div id="sa-meter-start-section" hidden>
              <label class="field">
                <span>Start Meter Reading (km)</span>
                <input type="number" id="sa-start-meter" class="form-control" step="0.1" min="0" placeholder="e.g. 12345.6" />
              </label>
              <label class="field">
                <span>Meter Photo</span>
                <input type="file" id="sa-meter-photo" accept="image/*" capture="environment" class="form-control" />
              </label>
              <div id="sa-meter-preview" style="margin:0.5rem 0;"></div>
            </div>
            <label class="field" style="margin-top:1rem;">
              <span>Take Selfie</span>
              <input type="file" id="sa-selfie-start" accept="image/*" capture="user" class="form-control" />
            </label>
            <div id="sa-selfie-preview" style="margin:0.5rem 0;"></div>
            <div id="sa-start-warning" style="color:var(--warn);font-size:0.85rem;margin:0.5rem 0;" hidden></div>
            <button class="btn btn-primary btn-block" id="sa-start-btn" style="margin-top:1rem;">
              <i class="ph ph-play"></i> Start Shift
            </button>
            <div id="sa-progress" hidden>
              <div style="height:4px;background:var(--border);border-radius:2px;margin-top:1rem;">
                <div id="sa-progress-bar" style="height:100%;background:var(--accent);border-radius:2px;width:0%;transition:width .3s;"></div>
              </div>
            </div>
          </div>`;
    }

    // ── Shift active (started, not completed) ──
    if (shift && !shift.completed_at) {
      const startTime = new Date(shift.started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      const lateClass = parseInt(shift.is_late_start) ? 'status-pill status-warn' : 'status-pill status-good';
      const lateLabel = parseInt(shift.is_late_start) ? 'Late Start' : 'On Time';
      const beltInfo = shift.belt_code ? `${UI.escape(shift.belt_code)} — ${UI.escape(shift.belt_name)}` : 'No belt (oversight)';
      const locFlag = parseInt(shift.start_location_flag) ? ' <span class="status-pill status-warn">GPS: Far from belt</span>' : '';
      const meterInfo = parseInt(shift.has_vehicle)
        ? `<p style="color:var(--ink-500);font-size:0.85rem;"><i class="ph ph-motorcycle"></i> Start reading: ${shift.start_meter_reading} km</p>` : '';

      const selfieUrl = shift.start_upload_id ? Api.url('upload/serve', { id: shift.start_upload_id }) : '';
      const selfieThumb = selfieUrl ? `<img src="${selfieUrl}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;">` : '';

      // Build activity selector for complete flow
      const actTypes = _shiftAttendanceState.activityTypes;
      let activitySection = '';

      if (isGBS) {
        // Per-belt activity selection
        activitySection = belts.map(b => `
          <div class="sa-belt-activities" data-belt-id="${b.belt_id}" style="margin-bottom:1rem;border:1px solid var(--border);border-radius:8px;padding:1rem;">
            <strong>${UI.escape(b.belt_code)} — ${UI.escape(b.common_name)}</strong>
            <div class="sa-activity-chips" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.5rem;">
              ${actTypes.map(at => `<button type="button" class="chip sa-act-chip" data-belt="${b.belt_id}" data-key="${at.activity_key}">${UI.escape(at.label)}</button>`).join('')}
            </div>
          </div>
        `).join('');
      } else {
        // Flat activity chips for HEAD_SUPERVISOR
        activitySection = `
          <div class="sa-belt-activities" data-belt-id="" style="margin-bottom:1rem;">
            <div class="sa-activity-chips" style="display:flex;flex-wrap:wrap;gap:0.5rem;">
              ${actTypes.map(at => `<button type="button" class="chip sa-act-chip" data-belt="" data-key="${at.activity_key}">${UI.escape(at.label)}</button>`).join('')}
            </div>
          </div>
        `;
      }

      const meterEndSection = parseInt(shift.has_vehicle) ? `
        <label class="field" style="margin-top:1rem;">
          <span>End Meter Reading (km)</span>
          <input type="number" id="sa-end-meter" class="form-control" step="0.1" min="${shift.start_meter_reading}" placeholder="e.g. 12400.0" />
        </label>
        <label class="field">
          <span>Meter Photo</span>
          <input type="file" id="sa-meter-photo-end" accept="image/*" capture="environment" class="form-control" />
        </label>
        <div id="sa-meter-end-preview" style="margin:0.5rem 0;"></div>
      ` : '';

      return UI.page('My Shift', 'Active')
        + `<div style="background:var(--good-bg, #ecfdf5);border:1px solid var(--good, #10b981);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;">
            ${selfieThumb}
            <div>
              <strong style="font-size:1.1rem;">Active since ${startTime}</strong>
              <span class="${lateClass}" style="margin-left:0.5rem;">${lateLabel}</span>${locFlag}
              <p style="color:var(--ink-500);margin:0.25rem 0 0;">${beltInfo}</p>
              ${meterInfo}
            </div>
          </div>
          <div id="sa-complete-panel" class="card" style="max-width:560px;margin:0 auto;padding:1.5rem;">
            <h3 style="margin:0 0 1rem;">End of Day Activities</h3>
            ${activitySection}
            <label class="field" style="margin-top:0.5rem;">
              <span>Notes (optional)</span>
              <textarea id="sa-shift-notes" class="form-control" maxlength="500" rows="2" placeholder="Anything else to note?"></textarea>
            </label>
            ${meterEndSection}
            <label class="field" style="margin-top:1rem;">
              <span>Take End Selfie</span>
              <input type="file" id="sa-selfie-end" accept="image/*" capture="user" class="form-control" />
            </label>
            <div id="sa-selfie-end-preview" style="margin:0.5rem 0;"></div>
            <div id="sa-end-warning" style="color:var(--warn);font-size:0.85rem;margin:0.5rem 0;" hidden></div>
            <button class="btn btn-primary btn-block" id="sa-complete-btn" style="margin-top:1rem;">
              <i class="ph ph-check-circle"></i> Complete Shift
            </button>
            <div id="sa-complete-progress" hidden>
              <div style="height:4px;background:var(--border);border-radius:2px;margin-top:1rem;">
                <div id="sa-complete-progress-bar" style="height:100%;background:var(--accent);border-radius:2px;width:0%;transition:width .3s;"></div>
              </div>
            </div>
          </div>`;
    }

    // ── Shift completed ──
    if (shift && shift.completed_at) {
      const startTime = new Date(shift.started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      const endTime = new Date(shift.completed_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' });
      const beltInfo = shift.belt_code ? `${UI.escape(shift.belt_code)} — ${UI.escape(shift.belt_name)}` : 'Oversight (no belt)';

      const flags = [];
      if (parseInt(shift.is_late_start)) flags.push('<span class="status-pill status-warn">Late Start</span>');
      if (parseInt(shift.is_early_end)) flags.push('<span class="status-pill status-warn">Early End</span>');
      if (parseInt(shift.start_location_flag)) flags.push('<span class="status-pill status-warn">GPS Flag (Start)</span>');
      if (parseInt(shift.end_location_flag)) flags.push('<span class="status-pill status-warn">GPS Flag (End)</span>');
      const flagHtml = flags.length ? `<div style="margin:0.5rem 0;">${flags.join(' ')}</div>` : '';

      const startSelfieUrl = shift.start_upload_id ? Api.url('upload/serve', { id: shift.start_upload_id }) : '';
      const endSelfieUrl = shift.end_upload_id ? Api.url('upload/serve', { id: shift.end_upload_id }) : '';

      const meterHtml = parseInt(shift.has_vehicle) ? `
        <p><strong>Vehicle:</strong> ${shift.start_meter_reading} → ${shift.end_meter_reading} km
           (${(parseFloat(shift.end_meter_reading) - parseFloat(shift.start_meter_reading)).toFixed(1)} km traveled)</p>
      ` : '';

      // Activities
      const acts = _shiftAttendanceState.activities;
      let actHtml = '';
      if (acts.length) {
        const grouped = {};
        acts.forEach(a => {
          const bKey = a.belt_code ? `${a.belt_code} — ${a.belt_name}` : 'General';
          if (!grouped[bKey]) grouped[bKey] = [];
          grouped[bKey].push(a.activity_label || a.activity_key);
        });
        actHtml = Object.entries(grouped).map(([belt, labels]) =>
          `<p><strong>${UI.escape(belt)}:</strong> ${labels.map(l => UI.escape(l)).join(', ')}</p>`
        ).join('');
      }

      return UI.page('My Shift', 'Completed')
        + `<div class="card" style="max-width:560px;margin:0 auto;padding:1.5rem;">
            <div style="display:flex;justify-content:center;gap:1rem;margin-bottom:1rem;">
              ${startSelfieUrl ? `<div style="text-align:center;"><img src="${startSelfieUrl}" style="width:120px;height:120px;object-fit:cover;border-radius:12px;"><p style="font-size:0.8rem;color:var(--ink-500);">Start</p></div>` : ''}
              ${endSelfieUrl ? `<div style="text-align:center;"><img src="${endSelfieUrl}" style="width:120px;height:120px;object-fit:cover;border-radius:12px;"><p style="font-size:0.8rem;color:var(--ink-500);">End</p></div>` : ''}
            </div>
            <p><strong>Shift:</strong> ${startTime} — ${endTime}</p>
            <p><strong>Belt:</strong> ${beltInfo}</p>
            ${flagHtml}
            ${meterHtml}
            <hr style="margin:1rem 0;">
            <h4 style="margin:0 0 0.5rem;">Activities</h4>
            ${actHtml || '<p style="color:var(--ink-500);">None recorded</p>'}
            ${shift.shift_notes ? `<p style="margin-top:0.5rem;"><strong>Notes:</strong> ${UI.escape(shift.shift_notes)}</p>` : ''}
          </div>`;
    }

    return UI.page('My Shift', '') + '<p>Unexpected state.</p>';
  },

  async afterRender() {
    const shift = _shiftAttendanceState.shift;
    const settings = _shiftAttendanceState.settings;

    // ── Start shift handlers ──
    if (!shift) {
      // Vehicle toggle
      const vehicleToggle = document.getElementById('sa-vehicle-toggle');
      const meterSection = document.getElementById('sa-meter-start-section');
      if (vehicleToggle && meterSection) {
        vehicleToggle.addEventListener('change', () => {
          meterSection.hidden = !vehicleToggle.checked;
        });
      }

      // Selfie preview
      const selfieInput = document.getElementById('sa-selfie-start');
      const selfiePreview = document.getElementById('sa-selfie-preview');
      if (selfieInput && selfiePreview) {
        selfieInput.addEventListener('change', () => {
          selfiePreview.innerHTML = '';
          if (selfieInput.files && selfieInput.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(selfieInput.files[0]);
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;';
            selfiePreview.appendChild(img);
          }
        });
      }

      // Meter photo preview
      const meterInput = document.getElementById('sa-meter-photo');
      const meterPreview = document.getElementById('sa-meter-preview');
      if (meterInput && meterPreview) {
        meterInput.addEventListener('change', () => {
          meterPreview.innerHTML = '';
          if (meterInput.files && meterInput.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(meterInput.files[0]);
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;';
            meterPreview.appendChild(img);
          }
        });
      }

      // Late warning
      if (settings.shift_start_time) {
        const [h, m] = settings.shift_start_time.split(':').map(Number);
        const startDeadline = new Date(); startDeadline.setHours(h, m, 0, 0);
        const now = new Date();
        if (now > startDeadline) {
          const warnEl = document.getElementById('sa-start-warning');
          if (warnEl) {
            warnEl.hidden = false;
            const graceEnd = new Date(startDeadline.getTime() + settings.late_grace_minutes * 60000);
            warnEl.textContent = now > graceEnd
              ? '⚠️ You are late — this will be flagged in your attendance record.'
              : '⏰ You are checking in a bit late.';
          }
        }
      }

      // Start button
      const startBtn = document.getElementById('sa-start-btn');
      if (startBtn) {
        startBtn.addEventListener('click', async () => {
          const selfieFile = document.getElementById('sa-selfie-start')?.files[0];
          if (!selfieFile) { UI.toast('Please take a selfie first.', 'bad'); return; }

          const beltSelect = document.getElementById('sa-belt-select');
          if (beltSelect && !beltSelect.value) { UI.toast('Please select a belt.', 'bad'); return; }

          const vehicleOn = document.getElementById('sa-vehicle-toggle')?.checked;
          if (vehicleOn) {
            const meterVal = document.getElementById('sa-start-meter')?.value;
            const meterFile = document.getElementById('sa-meter-photo')?.files[0];
            if (!meterVal) { UI.toast('Enter start meter reading.', 'bad'); return; }
            if (!meterFile) { UI.toast('Take a photo of the meter.', 'bad'); return; }
          }

          startBtn.disabled = true;
          const progressEl = document.getElementById('sa-progress');
          const progressBar = document.getElementById('sa-progress-bar');
          if (progressEl) progressEl.hidden = false;

          try {
            // Get GPS
            let lat = null, lng = null;
            try {
              const pos = await new Promise((res, rej) =>
                navigator.geolocation.getCurrentPosition(res, rej, { timeout: 5000, enableHighAccuracy: true })
              );
              lat = pos.coords.latitude;
              lng = pos.coords.longitude;
            } catch (_) { /* GPS optional */ }

            const fd = new FormData();
            fd.append('files', selfieFile);
            if (beltSelect) fd.append('belt_id', beltSelect.value);
            if (lat !== null) fd.append('latitude', lat);
            if (lng !== null) fd.append('longitude', lng);
            fd.append('has_vehicle', vehicleOn ? '1' : '0');

            if (vehicleOn) {
              fd.append('start_meter_reading', document.getElementById('sa-start-meter').value);
              const meterFile = document.getElementById('sa-meter-photo')?.files[0];
              if (meterFile) fd.append('meter_photo', meterFile);
            }

            await uploadWithProgress(fd, (pct) => {
              if (progressBar) progressBar.style.width = pct + '%';
            }, 'attendance/start-shift');

            UI.toast('Shift started!', 'good');
            App.refresh();
          } catch (err) {
            UI.toast(err.message, 'bad');
            startBtn.disabled = false;
            if (progressEl) progressEl.hidden = true;
          }
        });
      }
    }

    // ── Complete shift handlers ──
    if (shift && !shift.completed_at) {
      // Activity chip toggle
      document.querySelectorAll('.sa-act-chip').forEach(chip => {
        chip.addEventListener('click', () => chip.classList.toggle('chip-active'));
      });

      // End selfie preview
      const endSelfieInput = document.getElementById('sa-selfie-end');
      const endSelfiePreview = document.getElementById('sa-selfie-end-preview');
      if (endSelfieInput && endSelfiePreview) {
        endSelfieInput.addEventListener('change', () => {
          endSelfiePreview.innerHTML = '';
          if (endSelfieInput.files && endSelfieInput.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(endSelfieInput.files[0]);
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;';
            endSelfiePreview.appendChild(img);
          }
        });
      }

      // End meter preview
      const endMeterInput = document.getElementById('sa-meter-photo-end');
      const endMeterPreview = document.getElementById('sa-meter-end-preview');
      if (endMeterInput && endMeterPreview) {
        endMeterInput.addEventListener('change', () => {
          endMeterPreview.innerHTML = '';
          if (endMeterInput.files && endMeterInput.files[0]) {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(endMeterInput.files[0]);
            img.style.cssText = 'width:80px;height:80px;object-fit:cover;border-radius:8px;';
            endMeterPreview.appendChild(img);
          }
        });
      }

      // Early end warning
      if (settings.shift_end_time) {
        const [h, m] = settings.shift_end_time.split(':').map(Number);
        const endTime = new Date(); endTime.setHours(h, m, 0, 0);
        const graceCutoff = new Date(endTime.getTime() - settings.early_grace_minutes * 60000);
        const now = new Date();
        if (now < graceCutoff) {
          const warnEl = document.getElementById('sa-end-warning');
          if (warnEl) {
            warnEl.hidden = false;
            warnEl.textContent = '⚠️ You are ending early — this will be flagged in your attendance record.';
          }
        }
      }

      // Complete button
      const completeBtn = document.getElementById('sa-complete-btn');
      if (completeBtn) {
        completeBtn.addEventListener('click', async () => {
          const endSelfie = document.getElementById('sa-selfie-end')?.files[0];
          if (!endSelfie) { UI.toast('Please take your end selfie.', 'bad'); return; }

          // Collect selected activities
          const selectedChips = document.querySelectorAll('.sa-act-chip.chip-active');
          if (selectedChips.length === 0) { UI.toast('Select at least one activity.', 'bad'); return; }

          const activities = [];
          selectedChips.forEach(chip => {
            activities.push({
              belt_id: chip.dataset.belt || null,
              activity_key: chip.dataset.key,
            });
          });

          // Meter validation
          if (parseInt(shift.has_vehicle)) {
            const endMeterVal = document.getElementById('sa-end-meter')?.value;
            const endMeterFile = document.getElementById('sa-meter-photo-end')?.files[0];
            if (!endMeterVal) { UI.toast('Enter end meter reading.', 'bad'); return; }
            if (parseFloat(endMeterVal) < parseFloat(shift.start_meter_reading)) {
              UI.toast('End reading must be >= start reading.', 'bad'); return;
            }
            if (!endMeterFile) { UI.toast('Take a photo of the end meter.', 'bad'); return; }
          }

          completeBtn.disabled = true;
          const progressEl = document.getElementById('sa-complete-progress');
          const progressBar = document.getElementById('sa-complete-progress-bar');
          if (progressEl) progressEl.hidden = false;

          try {
            let lat = null, lng = null;
            try {
              const pos = await new Promise((res, rej) =>
                navigator.geolocation.getCurrentPosition(res, rej, { timeout: 5000, enableHighAccuracy: true })
              );
              lat = pos.coords.latitude;
              lng = pos.coords.longitude;
            } catch (_) { /* GPS optional */ }

            const fd = new FormData();
            fd.append('files', endSelfie);
            fd.append('activities', JSON.stringify(activities));
            fd.append('shift_notes', document.getElementById('sa-shift-notes')?.value || '');
            if (lat !== null) fd.append('latitude', lat);
            if (lng !== null) fd.append('longitude', lng);

            if (parseInt(shift.has_vehicle)) {
              fd.append('end_meter_reading', document.getElementById('sa-end-meter').value);
              const meterEndFile = document.getElementById('sa-meter-photo-end')?.files[0];
              if (meterEndFile) fd.append('meter_photo_end', meterEndFile);
            }

            await uploadWithProgress(fd, (pct) => {
              if (progressBar) progressBar.style.width = pct + '%';
            }, 'attendance/complete-shift');

            UI.toast('Shift completed!', 'good');
            App.refresh();
          } catch (err) {
            UI.toast(err.message, 'bad');
            completeBtn.disabled = false;
            if (progressEl) progressEl.hidden = true;
          }
        });
      }
    }
  }
});
```

- [ ] **Step 4: Verify JS syntax**

```powershell
& "C:\xampp\node_portable\node.exe" --check public\js\views\modules.js
```

Expected: No output (clean).

- [ ] **Step 5: Commit**

```
git add public/js/views/modules.js
git commit -m "feat: My Shift view — start/complete with selfie, GPS, activities, meter"
```

---

## Task 8: Frontend — OPS Shift Review view (calendar + list)

**Files:**
- Modify: `public/js/views/modules.js` (add `attendance.shift_review` view)

- [ ] **Step 1: Add `attendance.shift_review` view**

Add after the `attendance.shift` view block:

```js
Views.register('attendance.shift_review', {
  async render({ params = {} }) {
    const month = params.month || new Date().toISOString().slice(0, 7);
    const roleFilter = params.role_key || '';
    const viewMode = params.view || 'calendar';
    const data = await Api.get('attendance/review-list', { month, role_key: roleFilter });
    const shifts = data.shifts || [];
    const users = data.eligible_users || [];

    const roleOptions = '<option value="">All Roles</option><option value="GREEN_BELT_SUPERVISOR">Supervisor</option><option value="HEAD_SUPERVISOR">Head Supervisor</option>';

    const controls = `
      <div style="display:flex;flex-wrap:wrap;gap:0.75rem;align-items:center;margin-bottom:1rem;">
        <input type="month" id="sa-review-month" class="form-control" value="${month}" style="width:auto;">
        <select id="sa-review-role" class="form-control" style="width:auto;">${roleOptions}</select>
        <div style="display:flex;gap:0.25rem;">
          <button class="chip ${viewMode === 'calendar' ? 'chip-active' : ''}" data-sa-view="calendar">Calendar</button>
          <button class="chip ${viewMode === 'list' ? 'chip-active' : ''}" data-sa-view="list">List</button>
        </div>
        <button class="btn btn-ghost" data-sa-summary>Monthly Summary</button>
        <button class="btn btn-ghost" data-sa-activity-mgmt>Activity Types</button>
      </div>`;

    let body = '';

    if (viewMode === 'calendar') {
      // Build calendar grid
      const [year, mon] = month.split('-').map(Number);
      const daysInMonth = new Date(year, mon, 0).getDate();

      // Build shift lookup: userId -> { date -> shift }
      const shiftMap = {};
      shifts.forEach(s => {
        if (!shiftMap[s.user_id]) shiftMap[s.user_id] = {};
        shiftMap[s.user_id][s.shift_date] = s;
      });

      const dayHeaders = Array.from({ length: daysInMonth }, (_, i) => `<th style="min-width:32px;text-align:center;font-size:0.75rem;">${i + 1}</th>`).join('');

      const rows = users.map(u => {
        const cells = Array.from({ length: daysInMonth }, (_, i) => {
          const dayStr = `${month}-${String(i + 1).padStart(2, '0')}`;
          const s = shiftMap[u.user_id]?.[dayStr];
          let icon = '❌'; let cls = '';
          if (s) {
            if (s.override_status) {
              icon = '🔵'; cls = 'title="Override: ' + UI.escape(s.override_status) + '"';
            } else if (s.completed_at) {
              const flagged = parseInt(s.is_late_start) || parseInt(s.is_early_end) || parseInt(s.start_location_flag);
              icon = flagged ? '⚠️' : '✅';
            } else {
              icon = '🔶';
            }
          }
          const shiftId = s ? s.id : '';
          return `<td style="text-align:center;cursor:${s ? 'pointer' : 'default'};font-size:0.9rem;" ${cls} data-sa-cell="${shiftId}">${icon}</td>`;
        }).join('');
        return `<tr><td style="white-space:nowrap;font-weight:500;position:sticky;left:0;background:var(--bg);z-index:1;">${UI.escape(u.full_name)}</td>${cells}</tr>`;
      }).join('');

      body = `<div style="overflow-x:auto;">
        <table class="table" style="font-size:0.85rem;">
          <thead><tr><th style="position:sticky;left:0;background:var(--bg);z-index:2;">Supervisor</th>${dayHeaders}</tr></thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;
    } else {
      // List view
      const listRows = shifts.map(s => {
        const startTime = s.started_at ? new Date(s.started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '-';
        const endTime = s.completed_at ? new Date(s.completed_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '-';
        const flags = [];
        if (parseInt(s.is_late_start)) flags.push('Late');
        if (parseInt(s.is_early_end)) flags.push('Early');
        if (parseInt(s.start_location_flag)) flags.push('GPS');
        const flagStr = flags.length ? `<span class="status-pill status-warn">${flags.join(', ')}</span>` : '';
        const status = s.override_status
          ? `<span class="status-pill status-info">${s.override_status}</span>`
          : (s.completed_at ? '<span class="status-pill status-good">Present</span>' : '<span class="status-pill status-warn">Started</span>');

        return `<tr data-sa-row="${s.id}" style="cursor:pointer;">
          <td>${UI.escape(s.user_name)}</td><td>${s.shift_date}</td>
          <td>${startTime}</td><td>${endTime}</td>
          <td>${s.belt_code ? UI.escape(s.belt_code) : '-'}</td>
          <td>${status} ${flagStr}</td>
        </tr>`;
      }).join('');

      body = `<table class="table">
        <thead><tr><th>Supervisor</th><th>Date</th><th>Start</th><th>End</th><th>Belt</th><th>Status</th></tr></thead>
        <tbody>${listRows || '<tr><td colspan="6" style="text-align:center;color:var(--ink-500);">No shifts found</td></tr>'}</tbody>
      </table>`;
    }

    return UI.page('Shift Review', `${month}`)
      + controls
      + UI.panel('Attendance', body);
  },

  async afterRender({ params = {} }) {
    const month = params.month || new Date().toISOString().slice(0, 7);
    const currentView = params.view || 'calendar';

    // Month change
    document.getElementById('sa-review-month')?.addEventListener('change', (e) => {
      App.navigate('attendance.shift_review', { ...params, month: e.target.value });
    });

    // Role filter
    document.getElementById('sa-review-role')?.addEventListener('change', (e) => {
      App.navigate('attendance.shift_review', { ...params, role_key: e.target.value });
    });

    // View toggle
    document.querySelectorAll('[data-sa-view]').forEach(chip => {
      chip.addEventListener('click', () => {
        App.navigate('attendance.shift_review', { ...params, view: chip.dataset.saView });
      });
    });

    // Cell / row click → detail modal
    const openDetail = async (shiftId) => {
      if (!shiftId) return;
      try {
        const detail = await Api.get('attendance/review-detail', { shift_id: shiftId });
        const s = detail.shift;
        const acts = detail.activities || [];

        const startUrl = s.start_upload_id ? Api.url('upload/serve', { id: s.start_upload_id }) : '';
        const endUrl = s.end_upload_id ? Api.url('upload/serve', { id: s.end_upload_id }) : '';
        const startMeterUrl = s.start_meter_upload_id ? Api.url('upload/serve', { id: s.start_meter_upload_id }) : '';
        const endMeterUrl = s.end_meter_upload_id ? Api.url('upload/serve', { id: s.end_meter_upload_id }) : '';

        const startTime = s.started_at ? new Date(s.started_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '-';
        const endTime = s.completed_at ? new Date(s.completed_at).toLocaleTimeString('en-IN', { hour: '2-digit', minute: '2-digit' }) : '-';

        const flags = [];
        if (parseInt(s.is_late_start)) flags.push('Late Start');
        if (parseInt(s.is_early_end)) flags.push('Early End');
        if (parseInt(s.start_location_flag)) flags.push('GPS Far (Start)');
        if (parseInt(s.end_location_flag)) flags.push('GPS Far (End)');

        const actHtml = acts.length ? acts.map(a =>
          `<span class="chip chip-active" style="font-size:0.8rem;">${UI.escape(a.activity_label || a.activity_key)}${a.belt_code ? ' (' + UI.escape(a.belt_code) + ')' : ''}</span>`
        ).join(' ') : '<em>None</em>';

        const meterHtml = parseInt(s.has_vehicle)
          ? `<p><strong>Meter:</strong> ${s.start_meter_reading} → ${s.end_meter_reading || '?'} km</p>
             <div style="display:flex;gap:0.5rem;margin:0.5rem 0;">
               ${startMeterUrl ? `<img src="${startMeterUrl}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">` : ''}
               ${endMeterUrl ? `<img src="${endMeterUrl}" style="width:80px;height:80px;object-fit:cover;border-radius:8px;">` : ''}
             </div>` : '';

        const overrideSection = `
          <hr><h4>Override</h4>
          <select id="sa-override-status" class="form-control" style="margin-bottom:0.5rem;">
            <option value="">— No override —</option>
            <option value="PRESENT" ${s.override_status === 'PRESENT' ? 'selected' : ''}>Present</option>
            <option value="ABSENT" ${s.override_status === 'ABSENT' ? 'selected' : ''}>Absent</option>
            <option value="HALF_DAY" ${s.override_status === 'HALF_DAY' ? 'selected' : ''}>Half Day</option>
          </select>
          <textarea id="sa-override-reason" class="form-control" rows="2" placeholder="Reason for override">${UI.escape(s.override_reason || '')}</textarea>
          <button class="btn btn-primary" id="sa-override-btn" style="margin-top:0.5rem;">Save Override</button>`;

        const body = `
          <p><strong>${UI.escape(s.user_name)}</strong> — ${s.shift_date}</p>
          <p>${startTime} — ${endTime} | ${s.belt_code ? UI.escape(s.belt_code) + ' — ' + UI.escape(s.belt_name) : 'No belt'}</p>
          ${flags.length ? '<p>' + flags.map(f => `<span class="status-pill status-warn">${f}</span>`).join(' ') + '</p>' : ''}
          <div style="display:flex;gap:1rem;margin:1rem 0;">
            ${startUrl ? `<div style="text-align:center;"><img src="${startUrl}" style="width:100px;height:100px;object-fit:cover;border-radius:12px;"><p style="font-size:0.75rem;">Start</p></div>` : ''}
            ${endUrl ? `<div style="text-align:center;"><img src="${endUrl}" style="width:100px;height:100px;object-fit:cover;border-radius:12px;"><p style="font-size:0.75rem;">End</p></div>` : ''}
          </div>
          ${meterHtml}
          <p><strong>GPS:</strong> Start: ${s.start_latitude || '-'}, ${s.start_longitude || '-'} (${s.start_distance_km ? s.start_distance_km + ' km from belt' : 'n/a'}) | End: ${s.end_latitude || '-'}, ${s.end_longitude || '-'}</p>
          <p><strong>Activities:</strong> ${actHtml}</p>
          ${s.shift_notes ? `<p><strong>Notes:</strong> ${UI.escape(s.shift_notes)}</p>` : ''}
          ${overrideSection}
        `;

        UI.showModal('Shift Detail', body);

        // Wire override button
        document.getElementById('sa-override-btn')?.addEventListener('click', async () => {
          const status = document.getElementById('sa-override-status').value;
          const reason = document.getElementById('sa-override-reason').value;
          if (!status) { UI.toast('Select an override status.', 'bad'); return; }
          if (!reason.trim()) { UI.toast('Override reason is required.', 'bad'); return; }
          try {
            await Api.post('attendance/override', { shift_id: s.id, override_status: status, override_reason: reason });
            UI.toast('Override saved.', 'good');
            UI.closeModal();
            App.refresh();
          } catch (err) { UI.toast(err.message, 'bad'); }
        });
      } catch (err) { UI.toast(err.message, 'bad'); }
    };

    document.querySelectorAll('[data-sa-cell]').forEach(cell => {
      cell.addEventListener('click', () => openDetail(cell.dataset.saCell));
    });
    document.querySelectorAll('[data-sa-row]').forEach(row => {
      row.addEventListener('click', () => openDetail(row.dataset.saRow));
    });

    // Summary + Activity Types nav
    document.querySelector('[data-sa-summary]')?.addEventListener('click', () => {
      App.navigate('attendance.shift_review', { ...params, view: 'summary' });
    });
    document.querySelector('[data-sa-activity-mgmt]')?.addEventListener('click', () => {
      App.navigate('attendance.activity_types');
    });
  }
});
```

- [ ] **Step 2: Verify JS syntax**

```powershell
& "C:\xampp\node_portable\node.exe" --check public\js\views\modules.js
```

- [ ] **Step 3: Commit**

```
git add public/js/views/modules.js
git commit -m "feat: OPS Shift Review view — calendar grid + list + detail modal + override"
```

---

## Task 9: Frontend — Activity Types management view

**Files:**
- Modify: `public/js/views/modules.js` (add `attendance.activity_types` view)

- [ ] **Step 1: Add `attendance.activity_types` view**

```js
Views.register('attendance.activity_types', {
  async render() {
    const data = await Api.get('attendance/activity-types');
    const items = normalizeItems(data);
    const rows = items.map(at => `
      <tr>
        <td>${UI.escape(at.activity_key)}</td>
        <td>${UI.escape(at.label)}</td>
        <td>${at.sort_order}</td>
        <td>${parseInt(at.is_active) ? '<span class="status-pill status-good">Active</span>' : '<span class="status-pill status-bad">Inactive</span>'}</td>
        <td><button class="btn btn-ghost btn-sm" data-sa-edit-activity='${JSON.stringify({ id: at.id, activity_key: at.activity_key, label: at.label, sort_order: at.sort_order, is_active: at.is_active })}'>Edit</button></td>
      </tr>
    `).join('');

    const actions = UI.button('Add Activity', { icon: 'ph-plus', kind: 'btn-primary', attr: 'data-sa-add-activity' })
      + UI.button('Back to Review', { icon: 'ph-arrow-left', attr: 'data-sa-back' });

    return UI.page('Activity Types', 'Manage shift activity options', actions)
      + UI.panel('Activity Types', `
        <table class="table">
          <thead><tr><th>Key</th><th>Label</th><th>Order</th><th>Status</th><th></th></tr></thead>
          <tbody>${rows || '<tr><td colspan="5">No activity types</td></tr>'}</tbody>
        </table>`);
  },

  async afterRender() {
    document.querySelector('[data-sa-back]')?.addEventListener('click', () => {
      App.navigate('attendance.shift_review');
    });

    const openForm = (existing) => {
      const fields = [
        { name: 'label', label: 'Label', type: 'text', required: true, value: existing?.label || '' },
        { name: 'sort_order', label: 'Sort Order', type: 'number', value: existing?.sort_order ?? 0 },
        { name: 'is_active', label: 'Active', type: 'select', value: existing ? String(existing.is_active) : '1', options: [{ value: '1', label: 'Active' }, { value: '0', label: 'Inactive' }] },
      ];
      if (existing?.id) fields.unshift({ name: 'id', type: 'hidden', value: existing.id });
      if (existing?.activity_key) fields.unshift({ name: 'activity_key', type: 'hidden', value: existing.activity_key });

      openSimpleForm(existing ? 'Edit Activity Type' : 'New Activity Type', fields, 'Save', (payload) => {
        payload.is_active = payload.is_active === '1' || payload.is_active === true;
        simpleAction('attendance/activity-type-save', payload, 'Activity type saved');
      });
    };

    document.querySelector('[data-sa-add-activity]')?.addEventListener('click', () => openForm(null));
    document.querySelectorAll('[data-sa-edit-activity]').forEach(btn => {
      btn.addEventListener('click', () => openForm(JSON.parse(btn.dataset.saEditActivity)));
    });
  }
});
```

- [ ] **Step 2: Verify JS syntax**

```powershell
& "C:\xampp\node_portable\node.exe" --check public\js\views\modules.js
```

- [ ] **Step 3: Commit**

```
git add public/js/views/modules.js
git commit -m "feat: Activity Types management view for OPS"
```

---

## Task 10: Frontend — Update dashboard references + CSS + cache bust

**Files:**
- Modify: `public/js/views/modules.js` (update `green_belt.watering_oversight` and `governance.alert_panel` attendance references)
- Modify: `public/css/style.css` (add chip-active + calendar styles)
- Modify: `public/index.html` (cache bust)

- [ ] **Step 1: Update `green_belt.watering_oversight` view**

In the `green_belt.watering_oversight` view (around line 2628), change:
```js
Api.get('attendance/list', { date }),
```
to:
```js
Api.get('attendance/review-list', { month: date.slice(0, 7) }),
```

And update the attendance panel rendering to work with the new data format. The new API returns `shifts` array instead of flat items. Filter shifts for the selected date:

```js
const allShifts = attendanceResp.shifts || [];
const attendance = allShifts.filter(s => s.shift_date === date);
```

Update the column rendering to use new field names (`user_name` instead of `supervisor_name`, shift status derived from `started_at`/`completed_at` instead of `attendance_status`).

- [ ] **Step 2: Update `governance.alert_panel` dashboard**

In the `governance.alert_panel` view (around line 5542), the "Attendance Missing Today" panel references `data.attendance_missing_today`. This comes from the dashboard API — update the `DashboardService` to query `shift_attendance` instead of `supervisor_attendance` for the missing attendance list. Or if this is too complex for now, replace the panel with a link to the Shift Review page.

Simplest approach: replace the attendance panel content with a link:
```js
const attendanceMissing = (data.attendance_missing_today || []);
```

If the dashboard API hasn't changed yet, keep the panel but note it needs a backend update. Add a TODO comment and handle gracefully (show "See Shift Review" link if API returns empty).

- [ ] **Step 3: Add CSS for chip-active and calendar**

Add to `public/css/style.css`:

```css
/* Shift Attendance */
.chip-active { background: var(--accent) !important; color: #fff !important; }
.sa-belt-activities { transition: all 0.2s; }
```

- [ ] **Step 4: Cache bust**

In `public/index.html`, bump:
- `css/style.css?v=23` → `?v=24`
- `js/views/modules.js?v=52` → `?v=53`
- `js/core/navigation.js?v=13` → `?v=14`

- [ ] **Step 5: Verify JS syntax**

```powershell
& "C:\xampp\node_portable\node.exe" --check public\js\views\modules.js
& "C:\xampp\node_portable\node.exe" --check public\js\core\navigation.js
```

- [ ] **Step 6: Commit**

```
git add public/js/views/modules.js public/css/style.css public/index.html
git commit -m "feat: update dashboard references, add chip-active CSS, cache bust"
```

---

## Task 11: Backend — Update DashboardService for new attendance model

**Files:**
- Modify: `app/services/DashboardService.php` (update attendance_missing_today query)

- [ ] **Step 1: Find and update the attendance_missing_today query**

In `DashboardService.php`, find the query that generates `attendance_missing_today`. It currently queries `supervisor_attendance`. Change it to query `shift_attendance`:

Old pattern (queries for supervisors without a `supervisor_attendance` row today):
```sql
SELECT ... FROM users WHERE role = supervisor AND id NOT IN (SELECT supervisor_user_id FROM supervisor_attendance WHERE date = today)
```

New pattern (queries for eligible users without a `shift_attendance` row today):
```sql
SELECT u.id, u.full_name AS name
FROM users u
INNER JOIN roles r ON r.id = u.role_id
WHERE r.role_key IN ('GREEN_BELT_SUPERVISOR','HEAD_SUPERVISOR')
  AND u.is_active = 1
  AND u.id NOT IN (
    SELECT sa.user_id FROM shift_attendance sa WHERE sa.shift_date = CURDATE()
  )
```

- [ ] **Step 2: Verify PHP syntax**

```powershell
& "C:\xampp\php\php.exe" -l app\services\DashboardService.php
```

- [ ] **Step 3: Commit**

```
git add app/services/DashboardService.php
git commit -m "feat: update DashboardService attendance query for shift_attendance"
```

---

## Task 12: Governance docs update

**Files:**
- Modify: `docs/AGENT_START.md`
- Modify: `docs/PRODUCT_BACKLOG.md`
- Modify: `docs/PRODUCT_LOG.md`
- Modify: `docs/AI_TOOL_HANDOFF_GUIDE.md`

- [ ] **Step 1: Update AGENT_START.md**

- "Last updated by" line
- "Last commit" line
- "What was recently completed" — add shift attendance row
- "Current focus" — update
- "What NOT to touch" — add: `shift_attendance` table, `attendance_activity_types`, `shift_activities`, old `supervisor_attendance` is dropped
- "Known open issues" — note if any

- [ ] **Step 2: Update PRODUCT_BACKLOG.md**

- Add `attendance.shift` page entry under a new "Attendance" section or update existing MONITORING_TEAM / HEAD_SUPERVISOR / GBS sections
- Update improvement sequence table if applicable

- [ ] **Step 3: Update PRODUCT_LOG.md**

Append dated entry documenting:
- Why self-service replaces oversight model
- Schema decisions (new table vs modify old, upload parent_type addition)
- Grace period logic (15 min late, 10 min early)
- GPS threshold 3km (green belts can be 1-2km long)
- Per-belt activity logging (not flat)
- Vehicle meter tracking (optional toggle)
- Activity types OPS-managed (not hardcoded)

- [ ] **Step 4: Update AI_TOOL_HANDOFF_GUIDE.md**

Add pitfalls:
- `shift_attendance` UNIQUE on (user_id, shift_date) — handle duplicate key on start-shift
- `shift_activities` has no UNIQUE constraint (NULL belt_id issue) — dedup in service layer
- SHIFT_ATTENDANCE upload parent_type — parent_id is initially 0, updated after shift row created
- Old `supervisor_attendance` table is DROPPED — do not reference it
- `attendance/override` is `capability => 'manage'` — OPS_MANAGER only
- `attendance/start-shift` and `attendance/complete-shift` are `capability => 'upload'` — GBS and HS (both in UPLOAD permission group via HEAD_SUPERVISOR's MANAGE group which includes upload)
- Migration 007 — next agent uses 008

- [ ] **Step 5: Commit**

```
git add docs/AGENT_START.md docs/PRODUCT_BACKLOG.md docs/PRODUCT_LOG.md docs/AI_TOOL_HANDOFF_GUIDE.md
git commit -m "docs: update governance docs for shift attendance feature"
```

---

## Summary

| Task | What | Files |
|---|---|---|
| 1 | Migration: 3 new tables, ENUM, settings, RBAC, drop old | migration + schema doc |
| 2 | Repositories: ShiftAttendanceRepository + AttendanceActivityRepository | 2 new files |
| 3 | Service: ShiftAttendanceService + UploadStorageService prefix | 1 new + 1 modified |
| 4 | Controller: ShiftAttendanceController (9 routes) | 1 new file |
| 5 | Routes + RBAC config + delete old files | config + delete 3 files |
| 6 | Frontend: navigation.js update | 1 modified |
| 7 | Frontend: My Shift view (start/complete/summary) | modules.js |
| 8 | Frontend: OPS Shift Review (calendar + list + detail + override) | modules.js |
| 9 | Frontend: Activity Types management | modules.js |
| 10 | Frontend: Dashboard refs + CSS + cache bust | modules.js + style.css + index.html |
| 11 | Backend: DashboardService attendance query update | 1 modified |
| 12 | Governance docs | 4 docs |
