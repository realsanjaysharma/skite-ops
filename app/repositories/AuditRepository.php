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
            (actor_user_id, action_type, entity_type, entity_id, old_value, new_value, override_reason, created_at)
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
}
