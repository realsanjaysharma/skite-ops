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
     * Get green belt GPS coordinates by belt ID.
     */
    public function getBeltGps(int $beltId): ?array
    {
        return $this->fetchOne(
            "SELECT latitude, longitude FROM green_belts WHERE id = ?",
            [$beltId]
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
