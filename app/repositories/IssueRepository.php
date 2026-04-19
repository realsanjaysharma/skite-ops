<?php

require_once __DIR__ . '/BaseRepository.php';

/**
 * IssueRepository
 *
 * Purpose:
 * Data access for `issues` table.
 *
 * Schema Reference: docs/06_schema/schema_v1_full.sql — issues
 *
 * Columns: id, source_type, source_reference_id, belt_id, site_id,
 *          title, description, priority (LOW|MEDIUM|HIGH|CRITICAL),
 *          status (OPEN|IN_PROGRESS|CLOSED),
 *          raised_by_user_id, closed_by_user_id, closed_at,
 *          created_at, updated_at
 *
 * Note: Issue-to-task linking is done via tasks.linked_issue_id (on the tasks table),
 *       NOT via a column on the issues table.
 */
class IssueRepository extends BaseRepository
{
    /**
     * Find an issue by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT i.*,
                    creator.full_name AS raised_by_user_name,
                    closer.full_name AS closed_by_user_name,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    s.site_code,
                    s.location_text AS site_location
             FROM issues i
             LEFT JOIN users creator ON creator.id = i.raised_by_user_id
             LEFT JOIN users closer ON closer.id = i.closed_by_user_id
             LEFT JOIN green_belts gb ON gb.id = i.belt_id
             LEFT JOIN sites s ON s.id = i.site_id
             WHERE i.id = ?",
            [$id]
        );
    }

    /**
     * List issues with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'i.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 'i.priority = ?';
            $params[] = $filters['priority'];
        }

        if (!empty($filters['belt_id'])) {
            $where[] = 'i.belt_id = ?';
            $params[] = (int) $filters['belt_id'];
        }

        if (!empty($filters['site_id'])) {
            $where[] = 'i.site_id = ?';
            $params[] = (int) $filters['site_id'];
        }

        // Scope limit for Head Supervisor
        if (!empty($filters['restrict_to_belts']) && $filters['restrict_to_belts'] === true) {
            $where[] = 'i.belt_id IS NOT NULL';
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT i.*,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    s.site_code,
                    s.location_text AS site_location
             FROM issues i
             LEFT JOIN green_belts gb ON gb.id = i.belt_id
             LEFT JOIN sites s ON s.id = i.site_id
             {$whereClause}
             ORDER BY CASE i.status
                WHEN 'OPEN' THEN 1
                WHEN 'IN_PROGRESS' THEN 2
                WHEN 'CLOSED' THEN 3
                ELSE 4
             END ASC, i.created_at DESC",
            $params
        );
    }

    /**
     * Create a new issue.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO issues (
                source_type,
                source_reference_id,
                belt_id,
                site_id,
                title,
                description,
                priority,
                status,
                raised_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['source_type'],
                $data['source_reference_id'] ?? null,
                $data['belt_id'] ?? null,
                $data['site_id'] ?? null,
                $data['title'],
                $data['description'] ?? null,
                $data['priority'],
                $data['status'],
                $data['raised_by_user_id'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing issue.
     *
     * Note: Issue-to-task linking is done via tasks.linked_issue_id, not here.
     */
    public function update(array $data): bool
    {
        $fields = [];
        $params = [];

        $allowed = ['status', 'priority', 'title', 'description', 'closed_by_user_id', 'closed_at'];

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
            "UPDATE issues SET {$setClause} WHERE id = ?",
            $params
        );
    }
}
