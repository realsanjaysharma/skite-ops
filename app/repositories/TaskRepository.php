<?php

require_once __DIR__ . '/BaseRepository.php';

class TaskRepository extends BaseRepository
{
    /**
     * Find a task by ID.
     */
    public function findById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT t.*, 
                    assigner.full_name AS assigned_by_user_name,
                    lead.full_name AS assigned_lead_user_name
             FROM tasks t
             LEFT JOIN users assigner ON assigner.id = t.assigned_by_user_id
             LEFT JOIN users lead ON lead.id = t.assigned_lead_user_id
             WHERE t.id = ?",
            [$id]
        );
    }

    /**
     * Create a new task.
     */
    public function create(array $data): int
    {
        $this->execute(
            "INSERT INTO tasks (
                request_id,
                linked_issue_id,
                task_source_type,
                assigned_by_user_id,
                assigned_lead_user_id,
                task_category,
                vertical_type,
                work_description,
                location_text,
                priority,
                start_date,
                expected_close_date,
                status,
                progress_percent,
                is_archived
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $data['request_id'] ?? null,
                $data['linked_issue_id'] ?? null,
                $data['task_source_type'],
                $data['assigned_by_user_id'],
                $data['assigned_lead_user_id'] ?? null,
                $data['task_category'] ?? null,
                $data['vertical_type'] ?? null,
                $data['work_description'] ?? null,
                $data['location_text'] ?? null,
                $data['priority'] ?? 'MEDIUM',
                $data['start_date'] ?? null,
                $data['expected_close_date'] ?? null,
                $data['status'] ?? 'OPEN',
                0, // initial progress_percent
                0  // initial is_archived
            ]
        );

        return (int) $this->lastInsertId();
    }

    /**
     * List tasks with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $where = ['t.is_archived = 0'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $where[] = 't.priority = ?';
            $params[] = $filters['priority'];
        }
        
        if (!empty($filters['vertical_type'])) {
            $where[] = 't.vertical_type = ?';
            $params[] = $filters['vertical_type'];
        }

        if (!empty($filters['assigned_lead_user_id'])) {
            $where[] = 't.assigned_lead_user_id = ?';
            $params[] = (int) $filters['assigned_lead_user_id'];
        }
        
        if (isset($filters['include_archived']) && $filters['include_archived'] === true) {
            $where[0] = '1=1'; // strip the default archive exclusion
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT t.*, 
                    assigner.full_name AS assigned_by_user_name,
                    lead.full_name AS assigned_lead_user_name
             FROM tasks t
             LEFT JOIN users assigner ON assigner.id = t.assigned_by_user_id
             LEFT JOIN users lead ON lead.id = t.assigned_lead_user_id
             {$whereClause}
             ORDER BY CASE t.status
                WHEN 'RUNNING' THEN 1
                WHEN 'OPEN' THEN 2
                WHEN 'BLOCKED' THEN 3
                WHEN 'COMPLETED' THEN 4
                ELSE 5
             END ASC, t.created_at DESC",
            $params
        );
    }

    /**
     * Specialized retrieval for commercial tracking views with deep joins.
     */
    public function findProgressById(int $id): ?array
    {
        return $this->fetchOne(
            "SELECT t.*, 
                    assigner.full_name AS assigned_by_user_name,
                    lead.full_name AS assigned_lead_user_name,
                    req.client_name,
                    req.campaign_id,
                    req.site_id AS request_site_id
             FROM tasks t
             LEFT JOIN users assigner ON assigner.id = t.assigned_by_user_id
             LEFT JOIN users lead ON lead.id = t.assigned_lead_user_id
             LEFT JOIN task_requests req ON req.id = t.request_id
             WHERE t.id = ?",
            [$id]
        );
    }

    /**
     * List task progress explicitly mapped for commercial scoping where deep request filters apply.
     */
    public function findAllProgress(array $filters = []): array
    {
        $where = ['t.is_archived = 0'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 't.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['client_name'])) {
            $where[] = 'req.client_name LIKE ?';
            $params[] = '%' . $filters['client_name'] . '%';
        }

        if (!empty($filters['campaign_id'])) {
            $where[] = 'req.campaign_id = ?';
            $params[] = (int) $filters['campaign_id'];
        }

        if (!empty($filters['site_id'])) {
            // Task might have site_id implicitly via request or issue linking, mapped broadly
            // For now, mapping exclusively to request_id's site_id
            $where[] = 'req.site_id = ?';
            $params[] = (int) $filters['site_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 't.start_date >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 't.start_date <= ?';
            $params[] = $filters['date_to'];
        }

        $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

        return $this->fetchAll(
            "SELECT t.*, 
                    assigner.full_name AS assigned_by_user_name,
                    lead.full_name AS assigned_lead_user_name,
                    req.client_name,
                    req.campaign_id,
                    req.site_id AS request_site_id
             FROM tasks t
             LEFT JOIN users assigner ON assigner.id = t.assigned_by_user_id
             LEFT JOIN users lead ON lead.id = t.assigned_lead_user_id
             LEFT JOIN task_requests req ON req.id = t.request_id
             {$whereClause}
             ORDER BY t.start_date DESC",
            $params
        );
    }

    /**
     * Update an existing task.
     */
    public function update(array $data): bool
    {
        $fields = [];
        $params = [];
        
        $allowed = [
            'request_id', 'linked_issue_id', 'task_source_type', 'assigned_lead_user_id', 
            'task_category', 'vertical_type', 'work_description', 'location_text', 
            'priority', 'start_date', 'expected_close_date', 'actual_close_date',
            'status', 'progress_percent', 'remark_1', 'remark_2', 'completion_note',
            'is_archived', 'archived_at'
        ];
        
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
            "UPDATE tasks SET {$setClause} WHERE id = ?",
            $params
        );
    }
}
