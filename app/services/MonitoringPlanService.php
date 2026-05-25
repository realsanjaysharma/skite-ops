<?php
require_once __DIR__ . '/../repositories/MonitoringPlanRepository.php';
require_once __DIR__ . '/AuditService.php';

class MonitoringPlanService {
    private MonitoringPlanRepository $repo;
    private AuditService $auditService;

    public function __construct() {
        $this->repo = new MonitoringPlanRepository();
        $this->auditService = new AuditService();
    }

    public function listPlan(array $params): array {
        $month = $params['month'] ?? date('Y-m');
        $filters = [];
        if (!empty($params['site_category'])) $filters['site_category'] = $params['site_category'];
        if (!empty($params['lighting_type'])) $filters['lighting_type'] = $params['lighting_type'];
        if (!empty($params['route_or_group'])) $filters['route_or_group'] = $params['route_or_group'];

        // Use completion-aware query when filtering by completion_status
        $completionStatus = $params['completion_status'] ?? '';
        if ($completionStatus !== '' && in_array($completionStatus, ['completed', 'missed'], true)) {
            $filters['completion_status'] = $completionStatus;
            $rawItems = $this->repo->getPlanListWithCompletion($filters, $month);

            // Group by site_id (since the completion query returns per-due-date rows)
            $grouped = [];
            foreach ($rawItems as $row) {
                $siteId = (int) $row['site_id'];
                if (!isset($grouped[$siteId])) {
                    $grouped[$siteId] = [
                        'site_id' => $siteId,
                        'site_code' => $row['site_code'],
                        'location_text' => $row['location_text'],
                        'site_category' => $row['site_category'],
                        'lighting_type' => $row['lighting_type'],
                        'route_or_group' => $row['route_or_group'],
                        'selected_due_dates_count' => (int) $row['total_due_dates'],
                        'due_dates' => [],
                    ];
                }
                $grouped[$siteId]['due_dates'][] = $row['due_date'];
            }
            $items = array_values($grouped);
        } else {
            $items = $this->repo->getPlanList($filters, $month);
            foreach ($items as &$item) {
                $item['selected_due_dates_count'] = (int) $item['selected_due_dates_count'];
                $item['due_dates'] = $item['due_dates_list'] ? explode(',', $item['due_dates_list']) : [];
                unset($item['due_dates_list']);
            }
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => 1,
                'limit' => count($items) > 0 ? count($items) : 50,
                'total' => count($items)
            ],
            'month' => $month,
            'completion_status' => $completionStatus,
        ];
    }

    public function savePlan(array $data, int $actorId): void {
        if (empty($data['site_id']) || empty($data['plan_month'])) {
            throw new Exception("site_id and plan_month are required");
        }
        $siteId = (int) $data['site_id'];
        $month = $data['plan_month'];
        $dueDates = $data['due_dates'] ?? [];

        $this->repo->beginTransaction();
        try {
            $oldDates = $this->repo->getDueDatesForSiteAndMonth($siteId, $month);
            
            $this->repo->saveDueDates($siteId, $month, $dueDates, $actorId);
            
            $this->auditService->log(
                $actorId,
                'MONITORING_PLAN_SAVED',
                'sites',
                $siteId,
                ['month' => $month, 'due_dates' => $oldDates],
                ['month' => $month, 'due_dates' => $dueDates],
                null
            );
            $this->repo->commit();
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    public function copyNextMonth(array $data, int $actorId): void {
        if (empty($data['site_id']) || empty($data['source_month']) || empty($data['target_month'])) {
            throw new Exception("site_id, source_month, and target_month are required");
        }
        
        $siteId = (int) $data['site_id'];
        $sourceMonth = $data['source_month'];
        $targetMonth = $data['target_month'];

        $sourceDates = $this->repo->getDueDatesForSiteAndMonth($siteId, $sourceMonth);
        if (empty($sourceDates)) {
            throw new Exception("No plan found for source month");
        }

        $targetDates = $this->shiftDatesToMonth($sourceDates, $targetMonth);

        $this->repo->beginTransaction();
        try {
            $oldDates = $this->repo->getDueDatesForSiteAndMonth($siteId, $targetMonth);
            $this->repo->saveDueDates($siteId, $targetMonth, $targetDates, $actorId);
            
            $this->auditService->log(
                $actorId,
                'MONITORING_PLAN_COPIED',
                'sites',
                $siteId,
                ['month' => $targetMonth, 'due_dates' => $oldDates],
                ['month' => $targetMonth, 'due_dates' => $targetDates, 'copied_from' => $sourceMonth],
                null
            );
            
            $this->repo->commit();
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    public function bulkCopy(array $data, int $actorId): void {
        if (empty($data['source_month']) || empty($data['target_month'])) {
            throw new Exception("source_month and target_month are required");
        }
        $sourceMonth = $data['source_month'];
        $targetMonth = $data['target_month'];
        $replaceExisting = !empty($data['replace_existing']);

        $siteIds = [];
        if (!empty($data['site_ids']) && is_array($data['site_ids'])) {
            $siteIds = array_map('intval', $data['site_ids']);
        } elseif (!empty($data['route_or_group'])) {
            $siteIds = $this->repo->getSiteIdsByGroup($data['route_or_group']);
        }

        if (empty($siteIds)) {
            throw new Exception("No sites selected for bulk copy");
        }

        $sourceGroupKey = $data['route_or_group'] ?? null;

        $this->repo->beginTransaction();
        try {
            foreach ($siteIds as $siteId) {
                if (!$replaceExisting) {
                    $existing = $this->repo->getDueDatesForSiteAndMonth($siteId, $targetMonth);
                    if (!empty($existing)) {
                        continue;
                    }
                }

                $sourceDates = $this->repo->getDueDatesForSiteAndMonth($siteId, $sourceMonth);
                if (empty($sourceDates)) {
                    continue;
                }

                $targetDates = $this->shiftDatesToMonth($sourceDates, $targetMonth);
                $oldDates = $this->repo->getDueDatesForSiteAndMonth($siteId, $targetMonth);
                
                $this->repo->saveDueDates($siteId, $targetMonth, $targetDates, $actorId, $sourceGroupKey);
                
                $this->auditService->log(
                    $actorId,
                    'MONITORING_PLAN_BULK_COPIED',
                    'sites',
                    $siteId,
                    ['month' => $targetMonth, 'due_dates' => $oldDates],
                    ['month' => $targetMonth, 'due_dates' => $targetDates, 'copied_from' => $sourceMonth],
                    null
                );
            }
            $this->repo->commit();
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    private function shiftDatesToMonth(array $dates, string $targetMonth): array {
        $shifted = [];
        if (strpos($targetMonth, '-') === false) {
            return [];
        }
        
        list($targetYear, $targetM) = explode('-', $targetMonth);
        foreach ($dates as $date) {
            $parts = explode('-', $date);
            if (count($parts) === 3) {
                $day = $parts[2];
                if (!checkdate((int)$targetM, (int)$day, (int)$targetYear)) {
                    $day = date('t', strtotime("$targetYear-$targetM-01"));
                }
                $shifted[] = sprintf("%04d-%02d-%02d", $targetYear, $targetM, $day);
            }
        }
        return array_unique($shifted);
    }
}
