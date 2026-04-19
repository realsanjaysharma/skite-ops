<?php

require_once __DIR__ . '/BaseRepository.php';

class WateringRepository extends BaseRepository
{
    /**
     * Find a watering record by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT wr.*, 
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM watering_records wr
             LEFT JOIN users creator ON creator.id = wr.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = wr.override_by_user_id
             WHERE wr.id = ?",
            [$id]
        );
    }

    /**
     * Find a watering record by belt_id and watering_date.
     * There should only be one due to the unique constraint.
     */
    public function findByBeltAndDate(int $beltId, string $wateringDate): ?array
    {
        return $this->fetchOne(
            "SELECT wr.*,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM watering_records wr
             LEFT JOIN users creator ON creator.id = wr.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = wr.override_by_user_id
             WHERE wr.belt_id = ? AND wr.watering_date = ?",
            [$beltId, $wateringDate]
        );
    }

    /**
     * List watering records with optional filters.
     * Note: This only returns explicit rows (DONE or NOT_REQUIRED).
     * The service layer is responsible for deriving PENDING status.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'wr.watering_date = ?';
            $params[] = $filters['date'];
        }

        if (!empty($filters['belt_id'])) {
            $where[] = 'wr.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        if (!empty($filters['status'])) {
            $where[] = 'wr.status = ?';
            $params[] = $filters['status'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT wr.*, 
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM watering_records wr
             INNER JOIN green_belts gb ON gb.id = wr.belt_id
             LEFT JOIN users creator ON creator.id = wr.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = wr.override_by_user_id
             {$whereClause}
             ORDER BY wr.watering_date DESC, gb.belt_code ASC",
            $params
        );
    }

    /**
     * Create a new watering record.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO watering_records (
                belt_id, 
                watering_date, 
                status, 
                reason_text, 
                created_by_user_id, 
                override_by_user_id, 
                override_reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $data['belt_id'],
                $data['watering_date'],
                $data['status'],
                $data['reason_text'] ?? null,
                $data['created_by_user_id'],
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing watering record.
     */
    public function update(array $data): bool
    {
        return $this->execute(
            "UPDATE watering_records
             SET status = ?,
                 reason_text = ?,
                 override_by_user_id = ?,
                 override_reason = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [
                $data['status'],
                $data['reason_text'] ?? null,
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
                $data['id'],
            ]
        );
    }
}
