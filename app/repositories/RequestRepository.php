<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * RequestRepository
 *
 * Purpose:
 * Data access for `task_requests` table.
 *
 * Schema Reference: docs/06_schema/schema_v1_full.sql — task_requests
 *
 * Columns: id, requester_user_id, request_source_role, request_type,
 *          client_name, campaign_id, site_id, belt_id, description,
 *          status (SUBMITTED|APPROVED|REJECTED|CONVERTED),
 *          reviewed_by_user_id, reviewed_at, rejection_reason,
 *          created_at, updated_at
 */
class RequestRepository extends BaseRepository
{
    /**
     * Find a request by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT r.*,
                    creator.full_name AS requester_name,
                    reviewer.full_name AS reviewer_name,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    s.site_code,
                    s.location_text AS site_location,
                    c.campaign_name
             FROM task_requests r
             LEFT JOIN users creator ON creator.id = r.requester_user_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
             LEFT JOIN green_belts gb ON gb.id = r.belt_id
             LEFT JOIN sites s ON s.id = r.site_id
             LEFT JOIN campaigns c ON c.id = r.campaign_id
             WHERE r.id = ?",
            [$id]
        );
    }

    /**
     * List requests with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['requester_user_id'])) {
            $where[] = 'r.requester_user_id = ?';
            $params[] = (int) $filters['requester_user_id'];
        }

        if (!empty($filters['client_name'])) {
            $where[] = 'r.client_name LIKE ?';
            $params[] = '%' . $filters['client_name'] . '%';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT r.*,
                    creator.full_name AS requester_name,
                    reviewer.full_name AS reviewer_name,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    s.site_code,
                    s.location_text AS site_location,
                    c.campaign_name
             FROM task_requests r
             LEFT JOIN users creator ON creator.id = r.requester_user_id
             LEFT JOIN users reviewer ON reviewer.id = r.reviewed_by_user_id
             LEFT JOIN green_belts gb ON gb.id = r.belt_id
             LEFT JOIN sites s ON s.id = r.site_id
             LEFT JOIN campaigns c ON c.id = r.campaign_id
             {$whereClause}
             ORDER BY CASE r.status
                WHEN 'SUBMITTED' THEN 1
                WHEN 'APPROVED' THEN 2
                WHEN 'CONVERTED' THEN 3
                WHEN 'REJECTED' THEN 4
                ELSE 5
             END ASC, r.created_at DESC",
            $params
        );
    }

    /**
     * Create a new request.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO task_requests (
                requester_user_id,
                request_source_role,
                request_type,
                client_name,
                campaign_id,
                site_id,
                belt_id,
                description,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['requester_user_id'],
                $data['request_source_role'],
                $data['request_type'],
                $data['client_name'] ?? null,
                $data['campaign_id'] ?? null,
                $data['site_id'] ?? null,
                $data['belt_id'] ?? null,
                $data['description'],
                $data['status'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing request (for approve/reject flows).
     */
    public function update(array $data): bool
    {
        $fields = [];
        $params = [];

        $allowed = ['status', 'reviewed_by_user_id', 'reviewed_at', 'rejection_reason'];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = CURRENT_TIMESTAMP';
        $params[] = $data['id'];

        $setClause = implode(', ', $fields);

        return $this->execute(
            "UPDATE task_requests SET {$setClause} WHERE id = ?",
            $params
        );
    }
}
