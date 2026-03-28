<?php

/**
 * AuditService
 *
 * Purpose:
 * Accepts structured audit data and forwards it to the repository.
 *
 * IMPORTANT RULES:
 * - No business logic
 * - No session access
 * - No entity-specific rules here
 */

require_once __DIR__ . '/../repositories/AuditRepository.php';

class AuditService
{
    /**
     * @var AuditRepository
     */
    private $auditRepository;

    public function __construct()
    {
        $this->auditRepository = new AuditRepository();
    }

    /**
     * Persist one audit log entry.
     */
    public function logAction(
        int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $overrideReason = null
    ): void {
        $logged = $this->auditRepository->log(
            $userId,
            $action,
            $entityType,
            $entityId,
            $oldValues,
            $newValues,
            $overrideReason
        );

        if (!$logged) {
            throw new RuntimeException('Failed to write audit log.');
        }
    }
}
