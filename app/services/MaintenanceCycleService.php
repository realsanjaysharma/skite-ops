<?php

/**
 * MaintenanceCycleService
 *
 * Purpose:
 * Business logic for maintenance cycle list/start/close behavior.
 */

require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../repositories/MaintenanceCycleRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/../../config/database.php';

class MaintenanceCycleService
{
    /**
     * @var BeltRepository
     */
    private $beltRepo;

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

    public function __construct()
    {
        $this->beltRepo = new BeltRepository();
        $this->cycleRepo = new MaintenanceCycleRepository();
        $this->auditService = new AuditService();
        $this->db = Database::getConnection();
    }

    public function listCycles(array $filters, string $actorRoleKey): array
    {
        $normalized = [];

        if (!empty($filters['belt_id'])) {
            if (!is_numeric($filters['belt_id'])) {
                throw new InvalidArgumentException('Valid belt_id is required.');
            }

            $beltId = (int) $filters['belt_id'];
            $belt = $this->beltRepo->findById($beltId);

            if (!$belt) {
                throw new InvalidArgumentException('Belt not found.');
            }

            $this->assertActorCanAccessBelt($belt, $actorRoleKey);
            $normalized['belt_id'] = $beltId;
        }

        if (!empty($filters['status'])) {
            $status = strtoupper((string) $filters['status']);

            if (!in_array($status, ['ACTIVE', 'CLOSED'], true)) {
                throw new InvalidArgumentException('status must be ACTIVE or CLOSED.');
            }

            $normalized['status'] = $status;
        }

        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            $normalized['maintenance_mode'] = 'MAINTAINED';
        }

        return $this->cycleRepo->findAll($normalized);
    }

    public function startCycle(array $data, int $actorUserId, string $actorRoleKey): array
    {
        if (empty($data['belt_id']) || !is_numeric($data['belt_id'])) {
            throw new InvalidArgumentException('Valid belt_id is required.');
        }

        if (empty($data['start_date'])) {
            throw new InvalidArgumentException('start_date is required.');
        }

        $beltId = (int) $data['belt_id'];
        $startDate = trim((string) $data['start_date']);
        $this->validateDateFormat($startDate, 'start_date');

        $belt = $this->beltRepo->findById($beltId);

        if (!$belt) {
            throw new InvalidArgumentException('Belt not found.');
        }

        $this->assertActorCanAccessBelt($belt, $actorRoleKey);
        $this->assertCycleCanStart($belt);

        if ($this->cycleRepo->findActiveByBeltId($beltId)) {
            throw new InvalidArgumentException('An active cycle already exists for this belt.');
        }

        $this->db->beginTransaction();

        try {
            $newId = $this->cycleRepo->create($beltId, $actorUserId, $startDate);

            $this->auditService->logAction(
                $actorUserId,
                'CYCLE_STARTED',
                'maintenance_cycle',
                $newId,
                null,
                [
                    'belt_id' => $beltId,
                    'start_date' => $startDate,
                ]
            );

            $this->db->commit();

            return $this->cycleRepo->findById($newId);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function closeCycle(int $cycleId, string $endDate, string $closeReason, int $actorUserId, string $actorRoleKey): array
    {
        if ($cycleId <= 0) {
            throw new InvalidArgumentException('Valid cycle_id is required.');
        }

        $endDate = trim($endDate);
        $closeReason = trim($closeReason);

        if ($closeReason === '') {
            throw new InvalidArgumentException('close_reason is required.');
        }

        $this->validateDateFormat($endDate, 'end_date');

        $existing = $this->cycleRepo->findById($cycleId);

        if (!$existing) {
            throw new InvalidArgumentException('Cycle not found.');
        }

        if ($existing['end_date'] !== null) {
            throw new InvalidArgumentException('Cycle is already closed.');
        }

        if ($endDate < $existing['start_date']) {
            throw new InvalidArgumentException('end_date must be on or after start_date.');
        }

        $belt = $this->beltRepo->findById((int) $existing['belt_id']);

        if (!$belt) {
            throw new InvalidArgumentException('Belt not found.');
        }

        $this->assertActorCanAccessBelt($belt, $actorRoleKey);

        $this->db->beginTransaction();

        try {
            $this->cycleRepo->close($cycleId, $endDate, $closeReason, $actorUserId);

            $this->auditService->logAction(
                $actorUserId,
                'CYCLE_CLOSED',
                'maintenance_cycle',
                $cycleId,
                [
                    'end_date' => null,
                    'close_reason' => $existing['close_reason'],
                    'closed_by_user_id' => $existing['closed_by_user_id'],
                ],
                [
                    'end_date' => $endDate,
                    'close_reason' => $closeReason,
                    'closed_by_user_id' => $actorUserId,
                ]
            );

            $this->db->commit();

            return $this->cycleRepo->findById($cycleId);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    private function assertCycleCanStart(array $belt): void
    {
        if ($belt['maintenance_mode'] !== 'MAINTAINED') {
            throw new InvalidArgumentException('Cycles can only be started on maintained belts.');
        }

        if ((int) $belt['is_hidden'] === 1) {
            throw new InvalidArgumentException('Cannot start a cycle on a hidden belt.');
        }

        if ($belt['permission_status'] === 'EXPIRED') {
            throw new InvalidArgumentException('Cannot start a cycle on a belt with expired permission.');
        }
    }

    private function assertActorCanAccessBelt(array $belt, string $actorRoleKey): void
    {
        if ($actorRoleKey === 'OPS_MANAGER') {
            return;
        }

        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            if ($belt['maintenance_mode'] !== 'MAINTAINED') {
                throw new RuntimeException('Forbidden');
            }

            return;
        }

        throw new RuntimeException('Forbidden');
    }

    private function validateDateFormat(string $date, string $fieldName): void
    {
        $parsed = DateTime::createFromFormat('Y-m-d', $date);

        if (!$parsed || $parsed->format('Y-m-d') !== $date) {
            throw new InvalidArgumentException("{$fieldName} must be a valid date in YYYY-MM-DD format.");
        }
    }
}
