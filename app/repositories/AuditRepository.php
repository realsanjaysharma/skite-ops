<?php

/**
 * AuditRepository
 *
 * Purpose:
 * Writes immutable audit entries into audit_logs.
 *
 * IMPORTANT RULES:
 * - Repository only performs database writes
 * - No business logic or actor resolution here
 * - old/new values are stored as JSON strings
 */

require_once __DIR__ . '/BaseRepository.php';

class AuditRepository extends BaseRepository
{
    /**
     * Insert a single audit log row.
     */
    public function log(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $overrideReason = null
    ): bool {
        $oldValueJson = $oldValues !== null ? json_encode($oldValues) : null;
        $newValueJson = $newValues !== null ? json_encode($newValues) : null;

        return $this->execute(
            "INSERT INTO audit_logs
            (actor_user_id, action_type, entity_type, entity_id, old_values_json, new_values_json, override_reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $userId,
                $action,
                $entityType,
                $entityId,
                $oldValueJson,
                $newValueJson,
                $overrideReason,
            ]
        );
    }
    
    /**
     * Builds WHERE clause and parameter array based on filters.
     */
    private function buildFilterClause(array $filters): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['actor_user_id'])) {
            $where[] = 'actor_user_id = ?';
            $params[] = (int) $filters['actor_user_id'];
        }

        if (!empty($filters['action_type'])) {
            $where[] = 'action_type = ?';
            $params[] = $filters['action_type'];
        }

        if (!empty($filters['entity_type'])) {
            $where[] = 'entity_type = ?';
            $params[] = $filters['entity_type'];
        }

        if (!empty($filters['entity_id'])) {
            $where[] = 'entity_id = ?';
            $params[] = (int) $filters['entity_id'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(created_at) >= ?';
            $params[] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(created_at) <= ?';
            $params[] = $filters['date_to'];
        }

        $clause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        return ['clause' => $clause, 'params' => $params];
    }

    /**
     * Retrieve audit logs with optional filtering and pagination.
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $filterResult = $this->buildFilterClause($filters);
        $clause = $filterResult['clause'];
        $params = $filterResult['params'];

        $offset = ($page - 1) * $limit;

        return $this->fetchAll(
            "SELECT a.*, u.full_name as actor_user_name 
             FROM audit_logs a 
             LEFT JOIN users u ON a.actor_user_id = u.id 
             $clause 
             ORDER BY a.created_at DESC 
             LIMIT $limit OFFSET $offset",
            $params
        );
    }

    /**
     * Count total audit logs matching filters.
     */
    public function countAll(array $filters = []): int
    {
        $filterResult = $this->buildFilterClause($filters);
        $clause = $filterResult['clause'];
        $params = $filterResult['params'];

        $result = $this->fetchOne("SELECT count(*) as total FROM audit_logs $clause", $params);
        return (int) ($result['total'] ?? 0);
    }
}
