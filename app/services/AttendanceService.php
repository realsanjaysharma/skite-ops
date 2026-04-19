<?php

require_once __DIR__ . '/../repositories/AttendanceRepository.php';
require_once __DIR__ . '/../repositories/UserRepository.php';
require_once __DIR__ . '/../repositories/AuditRepository.php';

class AttendanceService
{
    private AttendanceRepository $attendanceRepo;
    private UserRepository $userRepo;
    private AuditRepository $auditRepo;

    public function __construct()
    {
        $this->attendanceRepo = new AttendanceRepository();
        $this->userRepo = new UserRepository();
        $this->auditRepo = new AuditRepository();
    }

    /**
     * Mark a supervisor's attendance.
     * Enforces role constraints, same-day rules, and audit requirements.
     */
    public function markAttendance(array $data, int $actorUserId, string $actorRoleKey): array
    {
        $supervisorUserId = (int) $data['supervisor_user_id'];
        $date = $data['attendance_date'];
        $status = $data['status'];
        
        $today = date('Y-m-d');
        
        if (!in_array($status, ['PRESENT', 'ABSENT'], true)) {
            throw new InvalidArgumentException("Invalid status. Must be PRESENT or ABSENT.");
        }

        $supervisor = $this->userRepo->findById($supervisorUserId);
        if (!$supervisor) {
            throw new InvalidArgumentException("Supervisor not found.");
        }

        $isSameDay = ($date === $today);

        $overrideByUserId = null;
        $overrideReason = null;

        // Apply role-based rules
        // Head Supervisor: Same day only, no reason required normally.
        // Ops Manager: Can override past dates, requires reason text when doing so.
        
        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            if (!$isSameDay) {
                throw new DomainException("Head Supervisors can only mark same-day attendance.");
            }
        } elseif ($actorRoleKey === 'OPS_MANAGER') {
            if (!$isSameDay && empty($data['override_reason'])) {
                throw new DomainException("Ops override requires a reason when outside normal flow (same-day).");
            }

            if (!$isSameDay) {
                $overrideByUserId = $actorUserId;
                $overrideReason = $data['override_reason'];
            }
        } else {
            throw new DomainException("Role not authorized to mark attendance.");
        }

        // Check for existing record
        $existing = $this->attendanceRepo->findBySupervisorAndDate($supervisorUserId, $date);

        if ($existing) {
            // Update existing row
            // Head Supervisor can update same-day. Ops can update any day. 
            // Ops updates to existing records might be considered corrections.
            if ($existing['status'] !== $status && $actorRoleKey === 'OPS_MANAGER') {
                if (empty($overrideReason) && empty($data['override_reason'])) {
                     throw new DomainException("Correction of existing record requires an override reason.");
                }
                $overrideByUserId = $actorUserId;
                $overrideReason = $data['override_reason'] ?? $overrideReason;
            } elseif ($existing['status'] !== $status && $actorRoleKey === 'HEAD_SUPERVISOR') {
               // Head supervisor can correct their own same day mistake.
               if (!$isSameDay) {
                   throw new DomainException("Head Supervisors cannot correct past attendance.");
               }
            }

            $updateData = [
                'id' => $existing['id'],
                'status' => $status,
                'override_by_user_id' => $overrideByUserId ?? $existing['override_by_user_id'],
                'override_reason' => $overrideReason ?? $existing['override_reason'],
            ];

            $this->attendanceRepo->update($updateData);

            if ($existing['status'] !== $status || $overrideByUserId) {
                $this->auditRepo->logAction(
                    $actorUserId,
                    'UPDATE',
                    'supervisor_attendance',
                    $existing['id'],
                    $existing,
                    $updateData,
                    $overrideReason
                );
            }

            return $this->attendanceRepo->findById((int) $existing['id']);

        } else {
            // Create new row
            $insertData = [
                'supervisor_user_id' => $supervisorUserId,
                'attendance_date' => $date,
                'status' => $status,
                'created_by_user_id' => $actorUserId,
                'override_by_user_id' => $overrideByUserId,
                'override_reason' => $overrideReason,
            ];

            $newId = $this->attendanceRepo->create($insertData);
            
            if ($overrideByUserId) {
                $this->auditRepo->logAction(
                    $actorUserId,
                    'CREATE',
                    'supervisor_attendance',
                    $newId,
                    null,
                    $insertData,
                    $overrideReason
                );
            }

            return $this->attendanceRepo->findById($newId);
        }
    }

    /**
     * List attendance status for supervisors.
     */
    public function listAttendanceRecords(array $filters): array
    {
        return $this->attendanceRepo->findAll($filters);
    }
}
