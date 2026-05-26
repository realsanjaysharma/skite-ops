# Shift Attendance — Design Spec

**Date:** 2026-05-26
**Status:** Approved
**Scope:** Self-service photo-proof shift attendance for field roles

---

## 1. Problem

The current `supervisor_attendance` system is an oversight model — HEAD_SUPERVISOR or OPS_MANAGER marks a supervisor PRESENT/ABSENT from a desk. There is no proof the supervisor was actually at their belt, no GPS verification, no photo evidence, and no record of what they did during the day.

This spec replaces the old system with a self-service shift attendance flow where supervisors (and head supervisors) check in and check out with camera selfies, GPS, optional vehicle odometer readings, and end-of-day activity logging per belt.

---

## 2. Roles & Access

| Role | What they do |
|---|---|
| GREEN_BELT_SUPERVISOR | Start shift (belt + selfie + GPS + optional meter), complete shift (activities per belt + selfie + optional meter). Self-service. |
| HEAD_SUPERVISOR | Same self-service flow but no belt selection (oversight role). Activities are flat (not belt-grouped). |
| OPS_MANAGER | Review all shifts (calendar grid + list view), view selfies + GPS + activities, override status, manage activity types. |

**Module key:** `attendance.shift` — new top-level section, extensible to future roles.

**RBAC changes:**
- GREEN_BELT_SUPERVISOR gets new scope: `attendance.shift`
- HEAD_SUPERVISOR: replace old `green_belt.supervisor_attendance` with `attendance.shift`
- OPS_MANAGER: replace old `green_belt.supervisor_attendance` with `attendance.shift`

---

## 3. Schema

### 3.1 New table: `shift_attendance`

```sql
CREATE TABLE shift_attendance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    role_key VARCHAR(50) NOT NULL,
    shift_date DATE NOT NULL,
    belt_id BIGINT UNSIGNED NULL,              -- check-in belt; NULL for HEAD_SUPERVISOR

    -- Start shift
    started_at DATETIME NULL,
    start_upload_id BIGINT UNSIGNED NULL,       -- selfie FK
    start_latitude DECIMAL(10,7) NULL,
    start_longitude DECIMAL(10,7) NULL,
    start_distance_km DECIMAL(6,2) NULL,        -- distance from belt GPS; NULL if no belt or no belt GPS
    start_location_flag TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = beyond threshold

    -- End shift
    completed_at DATETIME NULL,
    end_upload_id BIGINT UNSIGNED NULL,         -- selfie FK
    end_latitude DECIMAL(10,7) NULL,
    end_longitude DECIMAL(10,7) NULL,
    end_distance_km DECIMAL(6,2) NULL,
    end_location_flag TINYINT(1) NOT NULL DEFAULT 0,

    -- Vehicle / meter
    has_vehicle TINYINT(1) NOT NULL DEFAULT 0,
    start_meter_reading DECIMAL(10,1) NULL,     -- odometer km
    start_meter_upload_id BIGINT UNSIGNED NULL,  -- meter photo FK
    end_meter_reading DECIMAL(10,1) NULL,
    end_meter_upload_id BIGINT UNSIGNED NULL,    -- meter photo FK

    -- Flags (computed on write for fast monthly queries)
    is_late_start TINYINT(1) NOT NULL DEFAULT 0,
    is_early_end TINYINT(1) NOT NULL DEFAULT 0,

    -- Notes
    shift_notes TEXT NULL,                      -- optional free-text from end-of-day

    -- OPS override
    override_by_user_id BIGINT UNSIGNED NULL,
    override_reason TEXT NULL,
    override_status ENUM('PRESENT','ABSENT','HALF_DAY') NULL,  -- NULL = no override

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
);
```

### 3.2 New table: `shift_activities`

```sql
CREATE TABLE shift_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    shift_attendance_id BIGINT UNSIGNED NOT NULL,
    belt_id BIGINT UNSIGNED NULL,              -- NULL for HEAD_SUPERVISOR (flat activities)
    activity_key VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_sact_shift FOREIGN KEY (shift_attendance_id)
        REFERENCES shift_attendance(id) ON DELETE CASCADE,
    CONSTRAINT fk_sact_belt FOREIGN KEY (belt_id)
        REFERENCES green_belts(id) ON DELETE RESTRICT,
    -- Note: no UNIQUE constraint here because belt_id can be NULL (HEAD_SUPERVISOR)
    -- and MySQL treats NULL != NULL in unique indexes. Duplicate prevention is
    -- enforced in ShiftAttendanceService via INSERT IGNORE or pre-check.
    KEY idx_sact_shift_belt (shift_attendance_id, belt_id),
    KEY idx_sact_belt (belt_id),
    KEY idx_sact_activity (activity_key)
);
```

### 3.3 New table: `attendance_activity_types`

```sql
CREATE TABLE attendance_activity_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    activity_key VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_activity_key (activity_key)
);
```

**Default seed data:**

| activity_key | label | sort_order |
|---|---|---|
| WATERING | Watering | 1 |
| PLANTING | Planting | 2 |
| PRUNING | Pruning / Trimming | 3 |
| GRASS_CUTTING | Grass Cutting | 4 |
| CLEANING | Cleaning | 5 |
| REPAIR_WORK | Repair Work | 6 |
| PEST_CONTROL | Pest Control | 7 |
| LABOUR_SUPERVISION | Labour Supervision | 8 |
| SITE_INSPECTION | Site Inspection | 9 |
| AUTHORITY_MEETING | Authority Meeting | 10 |
| MATERIAL_EQUIPMENT | Material / Equipment | 11 |

### 3.4 Upload surface

Add `ATTENDANCE` to the `upload_surface` ENUM on the `uploads` table.

### 3.5 System settings

| setting_key | default_value | description |
|---|---|---|
| `attendance_shift_start_time` | `09:00` | Expected shift start time |
| `attendance_shift_end_time` | `17:00` | Expected shift end time |
| `attendance_late_grace_minutes` | `15` | Minutes after start before flagging late |
| `attendance_early_grace_minutes` | `10` | Minutes before end before flagging early |
| `attendance_location_threshold_km` | `3` | GPS distance threshold for soft flag |

### 3.6 Drop old table

Drop `supervisor_attendance` table. Remove `AttendanceController.php`, `AttendanceService.php`, `AttendanceRepository.php`. Remove old routes from `route_registry.php`. Remove old module key `green_belt.supervisor_attendance` from `rbac.php` and seed scopes.

---

## 4. Status Derivation

Status is **derived at query time**, not stored (except OPS overrides).

| Condition | Derived Status |
|---|---|
| No row for user+date | ABSENT |
| Row exists, `started_at` NOT NULL, `completed_at` IS NULL | STARTED (incomplete) |
| Row exists, `started_at` NOT NULL, `completed_at` NOT NULL | PRESENT |
| `override_status` IS NOT NULL | Override wins (PRESENT / ABSENT / HALF_DAY) |

---

## 5. Grace Periods & Flags

### 5.1 Late start

- **Grace window:** `attendance_shift_start_time` + `attendance_late_grace_minutes`
- Default: 9:00 + 15 min = 9:15
- Started at or before 9:15 → `is_late_start = 0`, no flag
- Started between 9:01 and 9:15 → soft toast warning at check-in: "You're checking in a bit late" but NOT flagged
- Started after 9:15 → `is_late_start = 1`, highlighted in monthly view

### 5.2 Early end

- **Grace window:** `attendance_shift_end_time` - `attendance_early_grace_minutes`
- Default: 17:00 - 10 min = 16:50
- Completed at or after 16:50 → `is_early_end = 0`, no flag
- Completed between 16:50 and 16:59 → allowed, no flag
- Completed before 16:50 → soft warning "You're ending early — this will be flagged" + `is_early_end = 1`
- No hard block on either — supervisor can always complete, just gets flagged

---

## 6. UX Flows

### 6.1 Supervisor "My Shift" Page

**State: No shift today**
- Belt dropdown (assigned belts only; pre-selected if 1 belt). HEAD_SUPERVISOR: no belt dropdown.
- "I used vehicle today" toggle (OFF by default)
  - If ON: number input "Start meter reading (km)" + camera-only photo of meter
- Big "Start Shift" button → opens camera (capture attribute, front-facing, no gallery)
- GPS captured via `navigator.geolocation` on submit click
- On submit: POST with selfie + belt_id + GPS + has_vehicle + meter data

**State: Shift active (started, not completed)**
- Green banner: "Active since 8:45 AM" (or warning-colored if late)
- Check-in belt name + selfie thumbnail + GPS badge
- Meter reading shown if has_vehicle
- "Complete Shift" button
  - If before grace-adjusted end time: soft warning toast

**State: Complete Shift panel**
- **GREEN_BELT_SUPERVISOR:** Assigned belt cards displayed. Tap belt to expand → activity chips underneath (multi-select toggle). Check-in belt is pre-expanded. At least 1 belt with 1 activity required.
- **HEAD_SUPERVISOR:** Flat row of activity chips (no belt grouping). At least 1 activity required.
- Optional notes text box (max 500 chars): "Anything else to note?"
- If `has_vehicle = 1`: number input "End meter reading (km)" + camera-only meter photo. Validation: end ≥ start.
- "Take End Selfie & Complete" button → opens camera → completes shift on capture

**State: Shift completed**
- Summary card: start/end times, duration, belt(s) worked, activities per belt, both selfie thumbnails, meter readings + distance if applicable
- Flags shown: late start badge, early end badge

### 6.2 OPS "Shift Review" Page

**Controls:**
- Month/year selector (defaults to current month)
- Role filter dropdown (All / GREEN_BELT_SUPERVISOR / HEAD_SUPERVISOR / future roles)
- View toggle: Calendar | List

**Calendar view:**
- Rows = supervisors (sorted alphabetically)
- Columns = days of the month
- Each cell = status icon:
  - ✅ green = PRESENT (shift completed, no flags)
  - ⚠️ yellow = PRESENT but flagged (late start or early end or GPS flag)
  - 🔶 orange = STARTED but not completed
  - ❌ red = ABSENT (no row)
  - 🔵 blue = OPS override applied
- Click cell → detail modal

**List view:**
- Filterable table: supervisor name, date, start time, end time, belt, activities count, flags, status
- Click row → detail modal

**Detail modal (both views):**
- Start selfie + end selfie side by side
- GPS coordinates + distance from belt (if applicable) + location flag badge
- Meter readings + meter photos + daily distance (if has_vehicle)
- Activity list grouped by belt
- Notes
- Override section: dropdown (PRESENT / ABSENT / HALF_DAY) + reason text + Save

### 6.3 Monthly Summary View

Accessible from Shift Review page or as a sub-view.

**Per-supervisor summary:**
- Total days present / absent / half-day / flagged
- Total km traveled (sum of daily meter distances)
- Activity breakdown (count of days each activity was performed)

**Per-belt summary:**
- Which supervisors worked there and how many days
- Activity breakdown across all supervisors for the belt
- Combined with upload `work_type` data for full picture

### 6.4 OPS Activity Type Management

Simple CRUD page accessible from Shift Review:
- Table: activity_key, label, sort_order, active/inactive toggle
- Add new via `openSimpleForm`
- Edit existing via `openSimpleForm`
- Deactivate (not delete) — deactivated types hidden from chip selection but preserved in historical data

---

## 7. API Routes

| Route | Method | Roles | Description |
|---|---|---|---|
| `attendance/my-shift` | GET | GBS, HS | Today's shift status + belt list + activity types |
| `attendance/start-shift` | POST | GBS, HS | Start shift: selfie upload + belt + GPS + vehicle/meter |
| `attendance/complete-shift` | POST | GBS, HS | Complete shift: selfie upload + activities + notes + meter |
| `attendance/review-list` | GET | OPS | All shifts for a month, supports calendar and list format |
| `attendance/review-detail` | GET | OPS, HS | Single shift detail with all photos, GPS, activities |
| `attendance/override` | POST | OPS | Set override_status + reason on a shift |
| `attendance/activity-types` | GET | OPS | List all activity types (active + inactive) |
| `attendance/activity-type-save` | POST | OPS | Create or update an activity type |
| `attendance/monthly-summary` | GET | OPS, HS | Aggregated monthly data per supervisor or per belt |

---

## 8. Backend Architecture

### 8.1 New files

```
app/controllers/ShiftAttendanceController.php
app/services/ShiftAttendanceService.php
app/repositories/ShiftAttendanceRepository.php
app/repositories/AttendanceActivityRepository.php
```

### 8.2 Responsibilities

**ShiftAttendanceController:** Request handling, input parsing, response shaping. Delegates to service.

**ShiftAttendanceService:**
- `getMyShift(userId, roleKey)` — today's shift + assigned belts + activity types
- `startShift(data, userId, roleKey)` — validate belt assignment (GBS), create shift row, handle selfie upload via UploadService, compute GPS distance + flags
- `completeShift(data, userId, roleKey)` — validate active shift exists, handle selfie + meter uploads, save activities per belt, compute end flags, set completed_at
- `getReviewList(filters)` — monthly shifts for all supervisors, formatted for calendar/list
- `getReviewDetail(shiftId)` — full shift detail with all photos + activities
- `overrideShift(shiftId, data, actorId)` — set override_status + reason, audit log
- `getMonthlySummary(filters)` — aggregated data

**ShiftAttendanceRepository:** SQL for shift_attendance table (CRUD, monthly queries, calendar aggregation).

**AttendanceActivityRepository:** SQL for shift_activities + attendance_activity_types (activity CRUD, per-shift activity insert/read, per-belt aggregation).

### 8.3 Transaction rules

- `startShift`: selfie upload via `UploadService::createUploadsForSurface()` (manages own transaction). Shift row insert auto-commits separately. Same pattern as MediaDiscoveryService.
- `completeShift`: selfie + meter uploads via UploadService (own transaction). Activity inserts + shift update in a separate service-layer transaction.
- `overrideShift`: single transaction for shift update + audit log.

### 8.4 GPS distance calculation

Server-side Haversine in `ShiftAttendanceService`. Compare supervisor GPS against `green_belts.latitude/longitude` for the selected belt. If belt has no GPS or no belt selected (HS), skip distance check. Flag if distance > `attendance_location_threshold_km` setting.

---

## 9. Upload Surface: ATTENDANCE

Add `ATTENDANCE` to the upload_surface ENUM. Upload config:

```php
'ATTENDANCE' => [
    'parent_type' => null,          // no parent entity — linked via shift FKs
    'allowed_roles' => ['GREEN_BELT_SUPERVISOR', 'HEAD_SUPERVISOR'],
    'max_files' => 1,               // one photo per upload call
    'file_types' => ['image/jpeg', 'image/png', 'image/webp'],
]
```

Each shift can have up to 4 uploads: start selfie, start meter photo, end meter photo, end selfie. Each is a separate `upload/create` call with surface `ATTENDANCE`.

---

## 10. What Gets Dropped

- `supervisor_attendance` table (DROP TABLE)
- `app/controllers/AttendanceController.php` (delete file)
- `app/services/AttendanceService.php` (delete file)
- `app/repositories/AttendanceRepository.php` (delete file)
- Old routes: `attendance/list`, `attendance/mark` from route_registry.php
- Old module key: `green_belt.supervisor_attendance` from rbac.php module_catalog
- Old frontend view: `Views.register('green_belt.supervisor_attendance', ...)` from modules.js
- Old navigation entry in navigation.js
- Old references in dashboards (OPS dashboard "Attendance Missing Today" panel, HEAD_SUPERVISOR watering oversight attendance table)

---

## 11. Role-Specific Behavior Summary

| Aspect | GREEN_BELT_SUPERVISOR | HEAD_SUPERVISOR |
|---|---|---|
| Belt at start | Required (assigned belts dropdown) | Not required |
| GPS validation | Soft flag if >3km from belt | GPS recorded, no distance check |
| Activity logging | Per-belt (assigned belts, multi-select chips) | Flat chips (no belt grouping) |
| `shift_attendance.belt_id` | Set to selected belt | NULL |
| `shift_activities.belt_id` | Required per activity | NULL |
| Vehicle toggle | Available | Available |
| My Shift page | Yes | Yes |
| Review page | No | View only (no override) |
| Override | No | No |
| Activity type management | No | No |

---

## 12. Future Extensibility

The `role_key` column on `shift_attendance` and the `attendance.shift` module key are designed for future roles:
- OUTSOURCED_MAINTAINER could get the same flow
- MONITORING_TEAM could optionally use it (currently has monitoring_shifts which is different)
- FABRICATION_LEAD could use it for workshop attendance

Adding a new role = grant `attendance.shift` scope + add role to allowed_roles in upload surface config + add role-specific logic in service layer (belt selection rules, activity grouping).

---

## 13. Not In Scope

- Real-time GPS tracking / geofencing
- Biometric verification (face recognition on selfie)
- Automatic attendance from upload activity (presence = uploads exist)
- Integration with any external HR/payroll system
- Offline mode / queue-and-sync for poor connectivity areas
