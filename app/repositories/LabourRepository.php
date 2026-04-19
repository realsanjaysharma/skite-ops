<?php

require_once __DIR__ . '/BaseRepository.php';

class LabourRepository extends BaseRepository
{
    /**
     * Find a labour entry record by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT le.*, 
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM labour_entries le
             LEFT JOIN users creator ON creator.id = le.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = le.override_by_user_id
             WHERE le.id = ?",
            [$id]
        );
    }

    /**
     * Find a labour entry by belt and date.
     * Enforced by unique constraint (belt_id, entry_date).
     */
    public function findByBeltAndDate(int $beltId, string $entryDate): ?array
    {
        return $this->fetchOne(
            "SELECT le.*,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM labour_entries le
             LEFT JOIN users creator ON creator.id = le.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = le.override_by_user_id
             WHERE le.belt_id = ? AND le.entry_date = ?",
            [$beltId, $entryDate]
        );
    }

    /**
     * List labour entry records with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['date'])) {
            $where[] = 'le.entry_date = ?';
            $params[] = $filters['date'];
        }

        if (!empty($filters['belt_id'])) {
            $where[] = 'le.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT le.*, 
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    creator.full_name AS created_by_user_name,
                    overrider.full_name AS override_by_user_name
             FROM labour_entries le
             INNER JOIN green_belts gb ON gb.id = le.belt_id
             LEFT JOIN users creator ON creator.id = le.created_by_user_id
             LEFT JOIN users overrider ON overrider.id = le.override_by_user_id
             {$whereClause}
             ORDER BY le.entry_date DESC, gb.belt_code ASC",
            $params
        );
    }

    /**
     * Create a new labour entry record.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO labour_entries (
                belt_id, 
                entry_date, 
                labour_count, 
                gardener_count, 
                night_guard_count, 
                created_by_user_id, 
                override_by_user_id, 
                override_reason
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['belt_id'],
                $data['entry_date'],
                (int) ($data['labour_count'] ?? 0),
                (int) ($data['gardener_count'] ?? 0),
                (int) ($data['night_guard_count'] ?? 0),
                $data['created_by_user_id'],
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing labour entry record.
     */
    public function update(array $data): bool
    {
        return $this->execute(
            "UPDATE labour_entries
             SET labour_count = ?,
                 gardener_count = ?,
                 night_guard_count = ?,
                 override_by_user_id = ?,
                 override_reason = ?,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = ?",
            [
                (int) ($data['labour_count'] ?? 0),
                (int) ($data['gardener_count'] ?? 0),
                (int) ($data['night_guard_count'] ?? 0),
                $data['override_by_user_id'] ?? null,
                $data['override_reason'] ?? null,
                $data['id'],
            ]
        );
    }
}
