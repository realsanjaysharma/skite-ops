<?php

require_once __DIR__ . '/../repositories/BeltUserAssignmentRepository.php';
require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/AuditService.php';

/**
 * BeltUserAssignmentService
 *
 * Business logic for generic belt-user assignments (BOARD_MONITOR, ELECTRICIAN, etc.).
 * Only OPS_MANAGER can create/close assignments.
 */
class BeltUserAssignmentService
{
    private BeltUserAssignmentRepository $assignmentRepo;
    private BeltRepository $beltRepo;
    private UserRepository $userRepo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->assignmentRepo = new BeltUserAssignmentRepository();
        $this->beltRepo = new BeltRepository();
        $this->userRepo = new UserRepository();
        $this->auditService = new AuditService();
    }

    /**
     * List assignments with optional filters.
     */
    public function listAssignments(array $filters): array
    {
        return $this->assignmentRepo->findAll($filters);
    }

    /**
     * Create a new assignment after validation.
     */
    public function createAssignment(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException('Only OPS can create belt user assignments.');
        }

        $requiredFields = ['belt_id', 'user_id', 'assignment_type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Missing required field: {$field}");
            }
        }

        $beltId = (int) $data['belt_id'];
        $userId = (int) $data['user_id'];
        $assignmentType = (string) $data['assignment_type'];
        $startDate = $data['start_date'] ?? date('Y-m-d');

        // Validate belt exists
        $belt = $this->beltRepo->findById($beltId);
        if (!$belt) {
            throw new InvalidArgumentException('Belt not found.');
        }

        // Validate user exists and is active
        $user = $this->userRepo->findById($userId);
        if (!$user || (int) $user['is_deleted'] === 1 || (int) $user['is_active'] !== 1) {
            throw new InvalidArgumentException('User not found or inactive.');
        }

        // Check for overlapping active assignment
        $overlap = $this->assignmentRepo->findActiveOverlap($beltId, $userId, $assignmentType);
        if ($overlap) {
            throw new DomainException('An active assignment already exists for this user, belt, and type.');
        }

        $newId = $this->assignmentRepo->create([
            'belt_id' => $beltId,
            'user_id' => $userId,
            'assignment_type' => $assignmentType,
            'start_date' => $startDate,
        ]);

        $this->auditService->logAction(
            $actorUserId,
            'BELT_USER_ASSIGNMENT_CREATED',
            'belt_user_assignments',
            $newId,
            null,
            ['belt_id' => $beltId, 'user_id' => $userId, 'assignment_type' => $assignmentType]
        );

        return $this->assignmentRepo->findById($newId);
    }

    /**
     * Close an assignment by setting end_date.
     */
    public function closeAssignment(int $assignmentId, int $actorUserId, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException('Only OPS can close belt user assignments.');
        }

        $assignment = $this->assignmentRepo->findById($assignmentId);
        if (!$assignment) {
            throw new InvalidArgumentException('Assignment not found.');
        }

        if ($assignment['end_date'] !== null) {
            throw new DomainException('Assignment is already closed.');
        }

        $today = date('Y-m-d');
        $this->assignmentRepo->close($assignmentId, $today);

        $this->auditService->logAction(
            $actorUserId,
            'BELT_USER_ASSIGNMENT_CLOSED',
            'belt_user_assignments',
            $assignmentId,
            ['end_date' => null],
            ['end_date' => $today]
        );

        return $this->assignmentRepo->findById($assignmentId);
    }
}
