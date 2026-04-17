<?php

/**
 * BeltAssignmentService
 *
 * Purpose:
 * Business logic for belt assignment management:
 * - Supervisor assignments
 * - Authority assignments
 * - Outsourced maintainer assignments
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - All business validation lives here
 * - No HTTP/session access
 * - No SQL queries (delegate to repository)
 * - Audit logging for governed mutations
 *
 * Design decisions:
 * - Assignment type ('supervisor', 'authority', 'outsourced') is validated here
 * - Belt existence and user existence are validated before creating
 * - Closing an already-closed assignment is rejected
 */

require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/AuditService.php';

class BeltAssignmentService
{
    /**
     * @var BeltRepository
     */
    private $beltRepo;

    /**
     * @var BeltAssignmentRepository
     */
    private $assignmentRepo;

    /**
     * @var UserRepository
     */
    private $userRepo;

    /**
     * @var AuditService
     */
    private $auditService;

    /**
     * Maps assignment type to the expected user role keys.
     * Used to validate that the assigned user has the correct role.
     */
    private const ROLE_KEY_MAP = [
        'supervisor' => 'GREEN_BELT_SUPERVISOR',
        'authority'  => 'AUTHORITY_REPRESENTATIVE',
        'outsourced' => 'OUTSOURCED_MAINTAINER',
    ];

    /**
     * Maps assignment type to entity_type for audit logging.
     */
    private const AUDIT_ENTITY_MAP = [
        'supervisor' => 'belt_supervisor_assignment',
        'authority'  => 'belt_authority_assignment',
        'outsourced' => 'belt_outsourced_assignment',
    ];

    public function __construct()
    {
        $this->beltRepo = new BeltRepository();
        $this->assignmentRepo = new BeltAssignmentRepository();
        $this->userRepo = new UserRepository();
        $this->auditService = new AuditService();
    }

    /**
     * List assignments by type with optional filters.
     *
     * Supported filters vary by type:
     * - supervisor: belt_id, supervisor_user_id (mapped to user_id internally)
     * - authority: belt_id, authority_user_id
     * - outsourced: belt_id, outsourced_user_id, active_only
     */
    public function listAssignments(string $type, array $filters): array
    {
        // Validate type
        $this->assignmentRepo->getTableConfig($type);

        // Normalize user_id filter from route-specific param names
        $normalizedFilters = $this->normalizeFilters($type, $filters);

        return $this->assignmentRepo->findAll($type, $normalizedFilters);
    }

    /**
     * Create a new assignment.
     *
     * Validates:
     * - belt exists
     * - user exists and is active
     * - user has the correct role for this assignment type
     * - start_date is provided
     */
    public function createAssignment(string $type, array $data, int $actorUserId): array
    {
        // Validate type
        $this->assignmentRepo->getTableConfig($type);

        // Required fields
        if (empty($data['belt_id']) || !is_numeric($data['belt_id'])) {
            throw new InvalidArgumentException('Valid belt_id is required.');
        }

        if (empty($data['user_id']) || !is_numeric($data['user_id'])) {
            throw new InvalidArgumentException('Valid user_id is required.');
        }

        if (empty($data['start_date'])) {
            throw new InvalidArgumentException('start_date is required.');
        }

        $beltId = (int) $data['belt_id'];
        $userId = (int) $data['user_id'];

        // Belt must exist
        $belt = $this->beltRepo->findById($beltId);

        if (!$belt) {
            throw new InvalidArgumentException('Belt not found.');
        }

        // User must exist and not be deleted
        $user = $this->userRepo->getUserById($userId);

        if (!$user) {
            throw new InvalidArgumentException('User not found.');
        }

        if (!$user['is_active']) {
            throw new InvalidArgumentException('User is not active.');
        }

        $expectedRoleKey = self::ROLE_KEY_MAP[$type];

        if (($user['role_key'] ?? null) !== $expectedRoleKey) {
            throw new InvalidArgumentException(
                "Assigned user must have role {$expectedRoleKey} for {$type} assignments."
            );
        }

        // Validate date format
        $this->validateDateFormat($data['start_date'], 'start_date');

        if (!empty($data['end_date'])) {
            $this->validateDateFormat($data['end_date'], 'end_date');

            if ($data['end_date'] < $data['start_date']) {
                throw new InvalidArgumentException('end_date must be on or after start_date.');
            }
        }

        // Create assignment
        $newId = $this->assignmentRepo->create($type, $data);

        // Audit log
        $this->auditService->logAction(
            $actorUserId,
            strtoupper($type) . '_ASSIGNMENT_CREATED',
            self::AUDIT_ENTITY_MAP[$type],
            $newId,
            null,
            [
                'belt_id'    => $beltId,
                'user_id'    => $userId,
                'start_date' => $data['start_date'],
                'end_date'   => $data['end_date'] ?? null,
            ]
        );

        return $this->assignmentRepo->findById($type, $newId);
    }

    /**
     * Close an assignment by setting end_date.
     *
     * Validates:
     * - assignment exists
     * - assignment is not already closed
     * - end_date is provided and valid
     */
    public function closeAssignment(string $type, int $assignmentId, string $endDate, int $actorUserId): array
    {
        // Validate type
        $this->assignmentRepo->getTableConfig($type);

        // Assignment must exist
        $existing = $this->assignmentRepo->findById($type, $assignmentId);

        if (!$existing) {
            throw new InvalidArgumentException('Assignment not found.');
        }

        // Must not already be closed
        if ($existing['end_date'] !== null) {
            throw new InvalidArgumentException('Assignment is already closed.');
        }

        // Validate date format
        $this->validateDateFormat($endDate, 'end_date');

        // end_date must be on or after start_date
        if ($endDate < $existing['start_date']) {
            throw new InvalidArgumentException('end_date must be on or after start_date.');
        }

        // Close
        $this->assignmentRepo->close($type, $assignmentId, $endDate);

        // Audit log
        $this->auditService->logAction(
            $actorUserId,
            strtoupper($type) . '_ASSIGNMENT_CLOSED',
            self::AUDIT_ENTITY_MAP[$type],
            $assignmentId,
            ['end_date' => null],
            ['end_date' => $endDate]
        );

        return $this->assignmentRepo->findById($type, $assignmentId);
    }

    /**
     * Normalize filter param names to generic user_id for the repository.
     */
    private function normalizeFilters(string $type, array $filters): array
    {
        $normalized = [];

        if (!empty($filters['belt_id'])) {
            $normalized['belt_id'] = $filters['belt_id'];
        }

        if (!empty($filters['active_only'])) {
            $normalized['active_only'] = true;
        }

        // Map type-specific user param to generic user_id
        $userParamMap = [
            'supervisor' => 'supervisor_user_id',
            'authority'  => 'authority_user_id',
            'outsourced' => 'outsourced_user_id',
        ];

        $userParam = $userParamMap[$type] ?? null;

        if ($userParam && !empty($filters[$userParam])) {
            $normalized['user_id'] = $filters[$userParam];
        }

        return $normalized;
    }

    /**
     * Validate date format (YYYY-MM-DD).
     */
    private function validateDateFormat(string $date, string $fieldName): void
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);

        if (!$d || $d->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$fieldName} must be a valid date in YYYY-MM-DD format.");
        }
    }
}
