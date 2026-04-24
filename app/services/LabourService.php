<?php

require_once __DIR__ . '/../repositories/LabourRepository.php';
require_once __DIR__ . '/../repositories/BeltRepository.php';
require_once __DIR__ . '/../services/AuditService.php';

class LabourService
{
    private LabourRepository $labourRepo;
    private BeltRepository $beltRepo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->labourRepo = new LabourRepository();
        $this->beltRepo = new BeltRepository();
        $this->auditService = new AuditService();
    }

    /**
     * Mark daily labour counts for a belt.
     * Enforces role constraints, same-day rules, and audit requirements.
     */
    public function markLabourCounts(array $data, int $actorUserId, string $actorRoleKey): array
    {
        $beltId = (int) $data['belt_id'];
        $date = $data['entry_date'];
        
        $today = date('Y-m-d');
        
        $belt = $this->beltRepo->findById($beltId);
        if (!$belt) {
            throw new InvalidArgumentException("Belt not found.");
        }

        $isSameDay = ($date === $today);

        $overrideByUserId = null;
        $overrideReason = null;

        // Apply role-based rules
        // Head Supervisor: Same day only, maintained belts.
        // Ops Manager: Can override past dates or unmaintained belts, requires reason text when doing so.
        
        if ($actorRoleKey === 'HEAD_SUPERVISOR') {
            if (!$isSameDay) {
                throw new DomainException("Head Supervisors can only enter labour counts for the same day.");
            }
            if ($belt['maintenance_mode'] !== 'MAINTAINED') {
                throw new DomainException("Head Supervisors can only enter labour counts for maintained belts.");
            }
        } elseif ($actorRoleKey === 'OPS_MANAGER') {
            $isNormalFlow = $isSameDay && $belt['maintenance_mode'] === 'MAINTAINED';
            
            if (!$isNormalFlow && empty($data['override_reason'])) {
                throw new DomainException("Ops override requires a reason when outside normal flow (same-day maintained).");
            }

            if (!$isNormalFlow) {
                $overrideByUserId = $actorUserId;
                $overrideReason = $data['override_reason'];
            }
        } else {
            throw new DomainException("Role not authorized to enter labour counts.");
        }

        // Check for existing record
        $existing = $this->labourRepo->findByBeltAndDate($beltId, $date);

        if ($existing) {
            // Update existing row
            // Head Supervisor can update same-day. 
            // Ops updates to existing records might be considered corrections.
            if ($actorRoleKey === 'OPS_MANAGER') {
                if (empty($overrideReason) && empty($data['override_reason'])) {
                     throw new DomainException("Correction of existing record requires an override reason.");
                }
                $overrideByUserId = $actorUserId;
                $overrideReason = $data['override_reason'] ?? $overrideReason;
            } elseif ($actorRoleKey === 'HEAD_SUPERVISOR') {
               // Head supervisor can correct their own same day entry.
               if (!$isSameDay) {
                   throw new DomainException("Head Supervisors cannot correct past labour entries.");
               }
            }

            $updateData = [
                'id' => $existing['id'],
                'labour_count' => $data['labour_count'],
                'gardener_count' => $data['gardener_count'],
                'night_guard_count' => $data['night_guard_count'],
                'override_by_user_id' => $overrideByUserId ?? $existing['override_by_user_id'],
                'override_reason' => $overrideReason ?? $existing['override_reason'],
            ];

            $this->labourRepo->update($updateData);

            if ($overrideByUserId || 
                $existing['labour_count'] != $data['labour_count'] || 
                $existing['gardener_count'] != $data['gardener_count'] || 
                $existing['night_guard_count'] != $data['night_guard_count']) 
            {
                $this->auditService->logAction(
                    $actorUserId,
                    'UPDATE',
                    'labour_entries',
                    $existing['id'],
                    $existing,
                    $updateData,
                    $overrideReason
                );
            }

            return $this->labourRepo->findById((int) $existing['id']);

        } else {
            // Create new row
            $insertData = [
                'belt_id' => $beltId,
                'entry_date' => $date,
                'labour_count' => $data['labour_count'],
                'gardener_count' => $data['gardener_count'],
                'night_guard_count' => $data['night_guard_count'],
                'created_by_user_id' => $actorUserId,
                'override_by_user_id' => $overrideByUserId,
                'override_reason' => $overrideReason,
            ];

            $newId = $this->labourRepo->create($insertData);
            
            if ($overrideByUserId) {
                $this->auditService->logAction(
                    $actorUserId,
                    'CREATE',
                    'labour_entries',
                    $newId,
                    null,
                    $insertData,
                    $overrideReason
                );
            }

            return $this->labourRepo->findById($newId);
        }
    }

    /**
     * List labour entry records.
     */
    public function listLabourEntries(array $filters): array
    {
        return $this->labourRepo->findAll($filters);
    }
}
