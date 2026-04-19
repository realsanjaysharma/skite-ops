<?php

require_once __DIR__ . '/BaseRepository.php';

class IssueRepository extends BaseRepository
{
    /**
     * Find an issue by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT i.*, 
                    creator.full_name AS created_by_user_name,
                    gb.belt_code,
                    gb.common_name AS belt_name,
                    s.site_code,
                    s.location_text AS site_location
             FROM issues i
             LEFT JOIN users creator ON creator.id = i.created_by_user_id
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
                issue_number,
                source_type,
                source_reference_id,
                belt_id,
                site_id,
                title,
                description,
                priority,
                status,
                created_by_user_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['issue_number'],
                $data['source_type'],
                $data['source_reference_id'] ?? null,
                $data['belt_id'] ?? null,
                $data['site_id'] ?? null,
                $data['title'],
                $data['description'] ?? null,
                $data['priority'],
                $data['status'],
                $data['created_by_user_id'],
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * Update an existing issue.
     */
    public function update(array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowed = ['status', 'linked_task_id', 'priority', 'title', 'description'];
        
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
    
    /**
     * Generate the next sequence number for an issue.
     * This is a simple implementation assuming no high-concurrency race conditions.
     */
    public function getNextIssueNumber(): string
    {
        $result = $this->fetchOne("SELECT MAX(id) as max_id FROM issues");
        $nextId = ($result['max_id'] ?? 0) + 1;
        return 'IS-' . str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);
    }
}
