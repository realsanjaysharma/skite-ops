<?php

/**
 * BeltService
 *
 * Purpose:
 * Business logic for Green Belt CRUD operations.
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - All business validation lives here
 * - No HTTP/session access
 * - No SQL queries (delegate to repository)
 * - Audit logging for governed mutations
 */

require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
require_once __DIR__ . '/../repositories/MaintenanceCycleRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

class BeltService
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
     * @var MaintenanceCycleRepository
     */
    private $cycleRepo;

    /**
     * @var AuditService
     */
    private $auditService;

    /**
     * @var PDO
     */
    private $db;

    /**
     * Valid enum values for validation.
     */
    private const VALID_PERMISSION_STATUSES = ['APPLIED', 'AGREEMENT_SIGNED', 'EXPIRED'];
    private const VALID_MAINTENANCE_MODES = ['MAINTAINED', 'OUTSOURCED'];
    private const VALID_WATERING_FREQUENCIES = ['DAILY', 'ALTERNATE_DAY', 'WEEKLY', 'NOT_REQUIRED'];

    public function __construct()
    {
        $this->beltRepo = new BeltRepository();
        $this->assignmentRepo = new BeltAssignmentRepository();
        $this->cycleRepo = new MaintenanceCycleRepository();
        $this->auditService = new AuditService();
        $this->db = Database::getConnection();
    }

    /**
     * List belts with filters and pagination.
     *
     * Returns: { items: [...], pagination: { page, limit, total } }
     */
    public function listBelts(array $filters, int $page, int $limit): array
    {
        if ($page < 1) {
            $page = 1;
        }

        if ($limit < 1 || $limit > 100) {
            $limit = DEFAULT_PAGE_LIMIT;
        }

        $items = $this->beltRepo->findAll($filters, $page, $limit);
        $total = $this->beltRepo->countAll($filters);

        return [
            'items' => $items,
            'pagination' => [
                'page'  => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    /**
     * Get a single belt with full detail context.
     *
     * Returns belt data plus:
     * - current and historical supervisor assignments
     * - current and historical authority assignments
     * - current and historical outsourced assignments
     */
    public function getBelt(int $id, ?string $actorRoleKey = null): array
    {
        $belt = $this->beltRepo->findById($id);

        if (!$belt) {
            throw new InvalidArgumentException('Belt not found.');
        }

        $this->assertActorCanReadBelt($belt, $actorRoleKey);

        // Fetch assignment histories
        $supervisorAssignments = $this->assignmentRepo->findByBeltId('supervisor', $id);
        $authorityAssignments = $this->assignmentRepo->findByBeltId('authority', $id);
        $outsourcedAssignments = $this->assignmentRepo->findByBeltId('outsourced', $id);
        $cycleHistory = $this->beltRepo->findCycleHistoryByBeltId($id);
        $wateringHistory = $this->beltRepo->findWateringHistoryByBeltId($id);
        $uploads = $this->beltRepo->findUploadsByBeltId($id);
        $issues = $this->beltRepo->findIssuesByBeltId($id);

        return [
            'belt' => $belt,
            'supervisor_assignments' => $supervisorAssignments,
            'authority_assignments' => $authorityAssignments,
            'outsourced_assignments' => $outsourcedAssignments,
            'recent_cycle_summary' => $this->beltRepo->findRecentCycleSummaryByBeltId($id),
            'recent_watering_summary' => $this->beltRepo->findRecentWateringSummaryByBeltId($id),
            'cycle_history' => $cycleHistory,
            'watering_history' => $wateringHistory,
            'uploads' => $uploads,
            'issues' => $issues,
        ];
    }

    /**
     * Create a new green belt.
     *
     * Validates:
     * - belt_code required and unique
     * - common_name required
     * - authority_name required
     * - permission_status is valid enum
     * - maintenance_mode is valid enum
     * - watering_frequency is valid enum
     * - if latitude is provided, longitude must also be provided (and vice versa)
     */
    public function createBelt(array $data, int $actorUserId): array
    {
        // Required field validation
        $this->validateRequiredFields($data);

        // Uniqueness check
        if ($this->beltRepo->beltCodeExists($data['belt_code'])) {
            throw new InvalidArgumentException('Belt code already exists.');
        }

        // Enum validation
        $this->validateEnums($data);

        // GPS pair validation
        $this->validateGpsPair($data);

        // Create
        $newId = $this->beltRepo->create($data);

        // Audit log
        $this->auditService->logAction(
            $actorUserId,
            'BELT_CREATED',
            'green_belt',
            $newId,
            null,
            $data
        );

        // Return created belt
        return $this->beltRepo->findById($newId);
    }

    /**
     * Update an existing green belt.
     *
     * belt_code is NOT editable after creation (immutable identifier).
     * All other fields from the create payload are editable.
     */
    public function updateBelt(array $data, int $actorUserId): array
    {
        if (empty($data['belt_id']) || !is_numeric($data['belt_id'])) {
            throw new InvalidArgumentException('Valid belt_id is required.');
        }

        $beltId = (int) $data['belt_id'];

        // Check belt exists
        $existing = $this->beltRepo->findById($beltId);

        if (!$existing) {
            throw new InvalidArgumentException('Belt not found.');
        }

        // Required fields for update (same as create minus belt_code)
        if (empty($data['common_name'])) {
            throw new InvalidArgumentException('common_name is required.');
        }

        if (empty($data['authority_name'])) {
            throw new InvalidArgumentException('authority_name is required.');
        }

        if (empty($data['permission_status'])) {
            throw new InvalidArgumentException('permission_status is required.');
        }

        if (empty($data['maintenance_mode'])) {
            throw new InvalidArgumentException('maintenance_mode is required.');
        }

        if (empty($data['watering_frequency'])) {
            throw new InvalidArgumentException('watering_frequency is required.');
        }

        // Enum validation
        $this->validateEnums($data);

        // GPS pair validation
        $this->validateGpsPair($data);

        // Capture old values for audit diff
        $oldValues = [
            'common_name'           => $existing['common_name'],
            'authority_name'        => $existing['authority_name'],
            'zone'                  => $existing['zone'],
            'location_text'         => $existing['location_text'],
            'latitude'              => $existing['latitude'],
            'longitude'             => $existing['longitude'],
            'permission_start_date' => $existing['permission_start_date'],
            'permission_end_date'   => $existing['permission_end_date'],
            'permission_status'     => $existing['permission_status'],
            'maintenance_mode'      => $existing['maintenance_mode'],
            'watering_frequency'    => $existing['watering_frequency'],
            'is_hidden'             => $existing['is_hidden'],
        ];

        $newValues = [
            'common_name'           => $data['common_name'],
            'authority_name'        => $data['authority_name'],
            'zone'                  => $data['zone'] ?? null,
            'location_text'         => $data['location_text'] ?? null,
            'latitude'              => $data['latitude'] ?? null,
            'longitude'             => $data['longitude'] ?? null,
            'permission_start_date' => $data['permission_start_date'] ?? null,
            'permission_end_date'   => $data['permission_end_date'] ?? null,
            'permission_status'     => $data['permission_status'],
            'maintenance_mode'      => $data['maintenance_mode'],
            'watering_frequency'    => $data['watering_frequency'],
            'is_hidden'             => $data['is_hidden'] ?? 0,
        ];

        $this->db->beginTransaction();

        try {
            // Update
            $this->beltRepo->update($beltId, $newValues);

            // Audit log
            $this->auditService->logAction(
                $actorUserId,
                'BELT_UPDATED',
                'green_belt',
                $beltId,
                $oldValues,
                $newValues
            );

            $this->closeActiveCycleIfBeltBecameInactive($existing, $newValues, $actorUserId);

            $this->db->commit();

            return $this->beltRepo->findById($beltId);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Validate required fields for belt creation.
     */
    private function validateRequiredFields(array $data): void
    {
        if (empty($data['belt_code'])) {
            throw new InvalidArgumentException('belt_code is required.');
        }

        if (empty($data['common_name'])) {
            throw new InvalidArgumentException('common_name is required.');
        }

        if (empty($data['authority_name'])) {
            throw new InvalidArgumentException('authority_name is required.');
        }

        if (empty($data['permission_status'])) {
            throw new InvalidArgumentException('permission_status is required.');
        }

        if (empty($data['maintenance_mode'])) {
            throw new InvalidArgumentException('maintenance_mode is required.');
        }

        if (empty($data['watering_frequency'])) {
            throw new InvalidArgumentException('watering_frequency is required.');
        }
    }

    /**
     * Validate enum values.
     */
    private function validateEnums(array $data): void
    {
        if (!in_array($data['permission_status'], self::VALID_PERMISSION_STATUSES, true)) {
            throw new InvalidArgumentException(
                'Invalid permission_status. Must be one of: ' . implode(', ', self::VALID_PERMISSION_STATUSES)
            );
        }

        if (!in_array($data['maintenance_mode'], self::VALID_MAINTENANCE_MODES, true)) {
            throw new InvalidArgumentException(
                'Invalid maintenance_mode. Must be one of: ' . implode(', ', self::VALID_MAINTENANCE_MODES)
            );
        }

        if (!in_array($data['watering_frequency'], self::VALID_WATERING_FREQUENCIES, true)) {
            throw new InvalidArgumentException(
                'Invalid watering_frequency. Must be one of: ' . implode(', ', self::VALID_WATERING_FREQUENCIES)
            );
        }
    }

    /**
     * If latitude is provided, longitude must also be present (and vice versa).
     */
    private function validateGpsPair(array $data): void
    {
        $hasLat = isset($data['latitude']) && $data['latitude'] !== '' && $data['latitude'] !== null;
        $hasLng = isset($data['longitude']) && $data['longitude'] !== '' && $data['longitude'] !== null;

        if ($hasLat !== $hasLng) {
            throw new InvalidArgumentException(
                'Both latitude and longitude must be provided together, or both omitted.'
            );
        }
    }

    private function assertActorCanReadBelt(array $belt, ?string $actorRoleKey): void
    {
        if ($actorRoleKey === null || $actorRoleKey === '' || $actorRoleKey === 'OPS_MANAGER') {
            return;
        }

        if ($actorRoleKey === 'HEAD_SUPERVISOR' && $belt['maintenance_mode'] === 'MAINTAINED') {
            return;
        }

        throw new RuntimeException('Forbidden');
    }

    private function closeActiveCycleIfBeltBecameInactive(array $existing, array $newValues, int $actorUserId): void
    {
        $becameHidden = (int) $existing['is_hidden'] === 0 && (int) $newValues['is_hidden'] === 1;
        $becameExpired = $existing['permission_status'] !== 'EXPIRED'
            && $newValues['permission_status'] === 'EXPIRED';

        if (!$becameHidden && !$becameExpired) {
            return;
        }

        $activeCycle = $this->cycleRepo->findActiveByBeltId((int) $existing['id']);

        if (!$activeCycle) {
            return;
        }

        $closeReason = $becameHidden && $becameExpired
            ? 'AUTO_CLOSED_BELT_HIDDEN_AND_PERMISSION_EXPIRED'
            : ($becameHidden ? 'AUTO_CLOSED_BELT_HIDDEN' : 'AUTO_CLOSED_PERMISSION_EXPIRED');

        $closeDate = date('Y-m-d');

        $this->cycleRepo->close(
            (int) $activeCycle['id'],
            $closeDate,
            $closeReason,
            $actorUserId
        );

        $this->auditService->logAction(
            $actorUserId,
            'CYCLE_AUTO_CLOSED',
            'maintenance_cycle',
            (int) $activeCycle['id'],
            [
                'end_date' => null,
                'close_reason' => $activeCycle['close_reason'],
                'closed_by_user_id' => $activeCycle['closed_by_user_id'],
            ],
            [
                'end_date' => $closeDate,
                'close_reason' => $closeReason,
                'closed_by_user_id' => $actorUserId,
            ],
            $closeReason
        );
    }
}
