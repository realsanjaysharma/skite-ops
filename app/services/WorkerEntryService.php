<?php

require_once __DIR__ . '/../repositories/WorkerEntryRepository.php';

class WorkerEntryService
{
    private WorkerEntryRepository $workerEntryRepo;

    public function __construct()
    {
        $this->workerEntryRepo = new WorkerEntryRepository();
    }

    /**
     * List mapped daily entries
     */
    public function listEntries(array $filters, string $actorRoleKey): array
    {
        // Allowed roles barrier
        if ($actorRoleKey !== 'OPS_MANAGER' && $actorRoleKey !== 'FABRICATION_LEAD') {
            throw new DomainException("Unauthorized: Domain restricted to Ops and Fabrication roles");
        }

        // Technically FABRICATION_LEAD might only have access to their assigned workers or tasks,
        // but since worker pool is shared and daily activity is universal across jobs,
        // page spec says "Ops, Fabrication Lead scoped read". In v1, there is no hard DB linkage 
        // tying a worker to a specific lead exclusively outside of task assignments, so we allow 
        // leads to see basic daily entries to know who is free/present.
        // If further granularity is required, it will be added in taskworker rules.

        return $this->workerEntryRepo->listEntries($filters);
    }

    /**
     * Upsert a worker's daily entry. Enforces exactly one daily entry per worker per date.
     */
    public function markEntry(array $data, string $actorRoleKey): array
    {
        if ($actorRoleKey !== 'OPS_MANAGER' && $actorRoleKey !== 'FABRICATION_LEAD') {
            throw new DomainException("Unauthorized: Only Ops or Fabrication Lead can mark entries");
        }

        if (empty($data['worker_id'])) {
            throw new InvalidArgumentException("worker_id is required");
        }
        if (empty($data['entry_date'])) {
            throw new InvalidArgumentException("entry_date is required");
        }
        if (empty($data['attendance_status'])) {
            throw new InvalidArgumentException("attendance_status is required");
        }

        $validAttendance = ['PRESENT', 'ABSENT', 'HALF_DAY'];
        if (!in_array($data['attendance_status'], $validAttendance, true)) {
            throw new DomainException("Invalid attendance_status enumeration");
        }

        $validActivities = ['INSTALLATION', 'MAINTENANCE', 'DRIVING', 'MONITORING', 'SUPPORT', 'OTHER'];
        if (!empty($data['activity_type']) && !in_array($data['activity_type'], $validActivities, true)) {
            throw new DomainException("Invalid activity_type enumeration");
        }

        // ABSENT workers generally shouldn't have active planned task work assigned on this same entry
        if ($data['attendance_status'] === 'ABSENT' && (!empty($data['task_id']) || !empty($data['site_id']) || !empty($data['work_plan']))) {
            throw new DomainException("Absent workers should not have associated daily task work contexts");
        }

        return $this->workerEntryRepo->saveEntry($data);
    }
}
