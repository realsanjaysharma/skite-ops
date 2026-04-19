<?php

require_once __DIR__ . '/../repositories/WorkerRepository.php';

class WorkerService
{
    private WorkerRepository $workerRepo;

    public function __construct()
    {
        $this->workerRepo = new WorkerRepository();
    }

    /**
     * Create a fabrication worker. Ops only.
     */
    public function createWorker(array $data, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can create workers.");
        }

        if (empty($data['worker_name'])) {
            throw new InvalidArgumentException("worker_name is required.");
        }

        $newId = $this->workerRepo->create($data);
        return $this->workerRepo->findById($newId);
    }

    /**
     * List workers.
     */
    public function listWorkers(array $filters, string $actorRoleKey): array
    {
        $allowedRoles = ['OPS_MANAGER', 'FABRICATION_LEAD', 'HEAD_SUPERVISOR'];
        if (!in_array($actorRoleKey, $allowedRoles, true)) {
            throw new DomainException("Role not authorized to view workers list.");
        }

        return $this->workerRepo->findAll($filters);
    }

    /**
     * Get a worker by ID.
     */
    public function getWorker(int $workerId, string $actorRoleKey): ?array
    {
        $allowedRoles = ['OPS_MANAGER', 'FABRICATION_LEAD', 'HEAD_SUPERVISOR'];
        if (!in_array($actorRoleKey, $allowedRoles, true)) {
            return null;
        }

        return $this->workerRepo->findById($workerId);
    }

    /**
     * Update worker. Ops only.
     */
    public function updateWorker(array $data, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER') {
            throw new DomainException("Only Ops can update workers.");
        }

        $workerId = (int) $data['worker_id'];
        $worker = $this->workerRepo->findById($workerId);
        
        if (!$worker) {
            throw new InvalidArgumentException("Worker not found.");
        }

        $data['id'] = $workerId;
        unset($data['worker_id']);

        $this->workerRepo->update($data);

        return $this->workerRepo->findById($workerId);
    }

    /**
     * Get real-time worker availability.
     */
    public function getWorkerAvailability(string $date, ?string $skillTag, string $actorRoleKey): array
    {
        $allowedRoles = ['OPS_MANAGER', 'FABRICATION_LEAD', 'HEAD_SUPERVISOR'];
        if (!in_array($actorRoleKey, $allowedRoles, true)) {
            throw new DomainException("Role not authorized to view worker availability.");
        }

        $stats = $this->workerRepo->getAvailabilityStats($date, $skillTag);
        
        $result = [
            'AVAILABLE' => [],
            'OCCUPIED' => [],
            'NOT_AVAILABLE' => []
        ];

        foreach ($stats as $row) {
            $formattedRow = [
                'id' => $row['worker_id'],
                'worker_name' => $row['worker_name'],
                'skill_tag' => $row['skill_tag'],
            ];

            if ($row['is_active'] != 1 || empty($row['attendance_status']) || $row['attendance_status'] === 'ABSENT') {
                $result['NOT_AVAILABLE'][] = $formattedRow;
            } elseif ($row['attendance_status'] === 'PRESENT' || $row['attendance_status'] === 'HALF_DAY') {
                if ($row['active_assignments_count'] > 0) {
                    $result['OCCUPIED'][] = $formattedRow;
                } else {
                    $result['AVAILABLE'][] = $formattedRow;
                }
            } else {
                $result['NOT_AVAILABLE'][] = $formattedRow;
            }
        }

        return $result;
    }
}
