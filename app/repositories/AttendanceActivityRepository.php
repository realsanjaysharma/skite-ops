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
