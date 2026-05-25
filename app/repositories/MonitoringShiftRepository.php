<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * MonitoringShiftRepository
 *
 * Data access for monitoring_shifts table.
 * Tracks monitoring shift start/end for MONITORING_TEAM users.
 *
 * Schema: monitoring_shifts (id, user_id, shift_date, started_at, completed_at,
 *         planned_count, completed_count, unplanned_count, created_at, updated_at)
 * Unique: (user_id, shift_date)
 */
class MonitoringShiftRepository extends BaseRepository
{
    /**
     * Find today's shift for a user. Returns null if no shift started.
     */
    public function findByUserAndDate(int $userId, string $date): ?array
    {
        return $this->fetchOne(
            "SELECT * FROM monitoring_shifts WHERE user_id = ? AND shift_date = ?",
            [$userId, $date]
        );
    }

    /**
     * Create a new shift record. Returns the new ID.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO monitoring_shifts
                (user_id, shift_date, started_at, planned_count, completed_count, unplanned_count)
             VALUES (?, ?, NOW(), ?, 0, 0)",
            [
                $data['user_id'],
                $data['shift_date'],
                $data['planned_count'],
            ]
        );
        return (int) $this->lastInsertId();
    }

    /**
     * Mark shift as completed.
     */
    public function completeShift(int $shiftId, int $completedCount, int $unplannedCount): bool
    {
        return $this->execute(
            "UPDATE monitoring_shifts
             SET completed_at = NOW(), completed_count = ?, unplanned_count = ?
             WHERE id = ? AND completed_at IS NULL",
            [$completedCount, $unplannedCount, $shiftId]
        );
    }

    /**
     * Increment the completed or unplanned count by 1.
     * Called after each monitoring upload.
     *
     * @param string $column Either 'completed_count' or 'unplanned_count'
     */
    public function incrementCount(int $userId, string $date, string $column): bool
    {
        if (!in_array($column, ['completed_count', 'unplanned_count'], true)) {
            throw new InvalidArgumentException("Invalid column: $column");
        }
        return $this->execute(
            "UPDATE monitoring_shifts
             SET {$column} = {$column} + 1
             WHERE user_id = ? AND shift_date = ? AND completed_at IS NULL",
            [$userId, $date]
        );
    }
}
