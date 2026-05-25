<?php

require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/../repositories/MonitoringPlanRepository.php';
require_once __DIR__ . '/../repositories/MonitoringShiftRepository.php';
require_once __DIR__ . '/../repositories/IssueRepository.php';
require_once __DIR__ . '/AuditService.php';

/**
 * MonitoringUploadService
 *
 * Orchestration for the monitoring upload page:
 * - Enriched site card data (planned + unplanned + search)
 * - Shift lifecycle (start / complete)
 * - Post-upload side effects (last_monitored, due_date completion, shift counts)
 * - Quick issue creation from condition tags
 * - Issue resolution from field with mandatory photo proof
 *
 * Does NOT own the upload transaction — UploadService manages its own internally.
 */
class MonitoringUploadService
{
    private SiteRepository $siteRepo;
    private MonitoringPlanRepository $planRepo;
    private MonitoringShiftRepository $shiftRepo;
    private IssueRepository $issueRepo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->siteRepo = new SiteRepository();
        $this->planRepo = new MonitoringPlanRepository();
        $this->shiftRepo = new MonitoringShiftRepository();
        $this->issueRepo = new IssueRepository();
        $this->auditService = new AuditService();
    }

    // ─── Enriched Site Queries ─────────────────────────────────────────

    /**
     * Get today's planned sites with enriched card data.
     */
    public function getPlannedSites(int $userId): array
    {
        $today = date('Y-m-d');
        $month = date('Y-m');

        $allSites = $this->planRepo->getPlanList([], $month);
        $plannedSiteIds = [];

        foreach ($allSites as $site) {
            $dueDates = !empty($site['due_dates_list'])
                ? explode(',', $site['due_dates_list'])
                : [];
            if (in_array($today, $dueDates, true)) {
                $plannedSiteIds[] = (int) $site['site_id'];
            }
        }

        if (empty($plannedSiteIds)) {
            return ['items' => [], 'planned_count' => 0, 'completed_count' => 0];
        }

        $enriched = $this->siteRepo->findEnrichedSites($plannedSiteIds, $userId);
        $items = $this->formatSiteCards($enriched);

        $completedCount = 0;
        foreach ($items as $item) {
            if ($item['uploaded_today']) $completedCount++;
        }

        return [
            'items' => $items,
            'planned_count' => count($items),
            'completed_count' => $completedCount,
        ];
    }

    /**
     * Get distinct routes for a site category (for route chips).
     */
    public function getRoutesByCategory(string $category): array
    {
        $validCategories = ['GREEN_BELT', 'CITY', 'HIGHWAY'];
        if (!in_array($category, $validCategories, true)) {
            throw new InvalidArgumentException("Invalid site_category: $category");
        }
        return $this->siteRepo->getRoutesByCategory($category);
    }

    /**
     * Get sites for unplanned browsing by category + route.
     */
    public function browseSites(string $category, string $route, int $userId): array
    {
        $filters = [
            'site_category' => $category,
            'route_or_group' => $route,
        ];
        $enriched = $this->siteRepo->findEnrichedSites([], $userId, $filters);
        $items = $this->formatSiteCards($enriched);

        $completedCount = 0;
        foreach ($items as $item) {
            if ($item['uploaded_today']) $completedCount++;
        }

        return [
            'items' => $items,
            'total' => count($items),
            'completed_count' => $completedCount,
        ];
    }

    /**
     * Search sites by client/location/code with enriched data.
     */
    public function searchSites(string $query, int $userId): array
    {
        $enriched = $this->siteRepo->searchSitesEnriched($query, $userId);
        return ['items' => $this->formatSiteCards($enriched)];
    }

    // ─── Shift Lifecycle ───────────────────────────────────────────────

    /**
     * Start a monitoring shift for today.
     */
    public function startShift(int $userId): array
    {
        $today = date('Y-m-d');
        $existing = $this->shiftRepo->findByUserAndDate($userId, $today);

        if ($existing) {
            return $existing;
        }

        $summary = $this->planRepo->getTodaysPlanSummary();

        $shiftId = $this->shiftRepo->create([
            'user_id' => $userId,
            'shift_date' => $today,
            'planned_count' => $summary['planned_count'],
        ]);

        $this->auditService->logAction(
            $userId,
            'MONITORING_SHIFT_STARTED',
            'monitoring_shifts',
            $shiftId,
            null,
            ['shift_date' => $today, 'planned_count' => $summary['planned_count']]
        );

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    /**
     * Complete today's monitoring shift.
     */
    public function completeShift(int $userId): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        if (!$shift) {
            throw new DomainException("No shift started for today.");
        }

        if ($shift['completed_at'] !== null) {
            return $shift;
        }

        $this->shiftRepo->completeShift(
            (int) $shift['id'],
            (int) $shift['completed_count'],
            (int) $shift['unplanned_count']
        );

        $updated = $this->shiftRepo->findByUserAndDate($userId, $today);

        $this->auditService->logAction(
            $userId,
            'MONITORING_SHIFT_COMPLETED',
            'monitoring_shifts',
            (int) $shift['id'],
            ['completed_at' => null],
            ['completed_at' => $updated['completed_at'],
             'completed_count' => $updated['completed_count'],
             'unplanned_count' => $updated['unplanned_count']]
        );

        return $updated;
    }

    /**
     * Get today's shift status (or null if not started).
     */
    public function getTodayShift(int $userId): ?array
    {
        return $this->shiftRepo->findByUserAndDate($userId, date('Y-m-d'));
    }

    // ─── Post-Upload Side Effects ──────────────────────────────────────

    /**
     * Called after a successful monitoring upload.
     * Updates last_monitored, marks due date completed, increments shift count.
     *
     * This runs AFTER UploadService commits its own transaction.
     */
    public function handlePostUploadSideEffects(int $siteId, int $userId): void
    {
        $today = date('Y-m-d');

        // 1. Update site's last_monitored tracking
        $this->siteRepo->updateLastMonitored($siteId, $userId);

        // 2. Mark today's due date completed (if exists)
        $this->planRepo->markCompleted($siteId, $today);

        // 3. Increment shift counter (if shift active)
        $plannedSiteIds = $this->planRepo->getTodaysPlannedSiteIds();
        $isPlanned = in_array($siteId, $plannedSiteIds, true);
        $column = $isPlanned ? 'completed_count' : 'unplanned_count';
        $this->shiftRepo->incrementCount($userId, $today, $column);
    }

    // ─── Quick Issue Report ────────────────────────────────────────────

    /**
     * Create an issue from a monitoring condition observation.
     * Used when site_condition is not GOOD (DAMAGED, FADED, etc.).
     *
     * @param int    $siteId    The site with the issue
     * @param string $condition The site_condition ENUM value
     * @param string $comment   Optional description
     * @param int    $userId    The monitoring person reporting it
     * @return array The created issue record
     */
    public function reportConditionIssue(int $siteId, string $condition, ?string $comment, int $userId): array
    {
        $validConditions = ['DAMAGED', 'FADED', 'CREATIVE_MISSING', 'LIGHTS_OFF'];
        if (!in_array($condition, $validConditions, true)) {
            throw new InvalidArgumentException("Invalid condition for issue: $condition");
        }

        $site = $this->siteRepo->findById($siteId);
        if (!$site) {
            throw new InvalidArgumentException("Site not found.");
        }

        $conditionLabel = str_replace('_', ' ', ucfirst(strtolower($condition)));
        $title = "Site Condition: {$conditionLabel} — " . ($site['location_text'] ?? $site['site_code']);

        $issueData = [
            'source_type' => 'MONITORING_CONDITION',
            'site_id' => $siteId,
            'title' => $title,
            'description' => $comment,
            'priority' => in_array($condition, ['DAMAGED', 'CREATIVE_MISSING'], true) ? 'HIGH' : 'MEDIUM',
            'status' => 'OPEN',
            'raised_by_user_id' => $userId,
        ];

        $issueId = $this->issueRepo->create($issueData);

        $this->auditService->logAction(
            $userId,
            'MONITORING_ISSUE_REPORTED',
            'issues',
            $issueId,
            null,
            $issueData
        );

        return $this->issueRepo->findById($issueId);
    }

    // ─── Issue Resolution from Field ───────────────────────────────────

    /**
     * Resolve an issue from the field with mandatory photo proof.
     * The photo upload is handled separately (by UploadService), this just
     * transitions the issue status.
     *
     * MONITORING_TEAM can resolve site issues — this extends the existing
     * closeIssue() which is OPS_MANAGER only. This is a field-resolution
     * with required photo proof, audited separately.
     */
    public function resolveIssueFromField(int $issueId, ?string $comment, int $userId): array
    {
        $issue = $this->issueRepo->findById($issueId);
        if (!$issue) {
            throw new InvalidArgumentException("Issue not found.");
        }

        if (!in_array($issue['status'], ['OPEN', 'IN_PROGRESS'], true)) {
            throw new DomainException("Only OPEN or IN_PROGRESS issues can be resolved.");
        }

        $this->issueRepo->update([
            'id' => $issueId,
            'status' => 'CLOSED',
            'closed_by_user_id' => $userId,
            'closed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditService->logAction(
            $userId,
            'ISSUE_RESOLVED_FROM_FIELD',
            'issues',
            $issueId,
            ['status' => $issue['status']],
            ['status' => 'CLOSED', 'closed_by_user_id' => $userId, 'resolution_note' => $comment]
        );

        return $this->issueRepo->findById($issueId);
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    /**
     * Format raw enriched site rows into frontend card data.
     */
    private function formatSiteCards(array $rows): array
    {
        return array_map(static function (array $row): array {
            $creativeUrl = null;
            if (!empty($row['creative_upload_id'])) {
                $creativeUrl = '../index.php?route=upload/serve&id=' . $row['creative_upload_id'];
            }

            return [
                'id' => (int) $row['id'],
                'site_code' => $row['site_code'],
                'location_text' => $row['location_text'],
                'site_category' => $row['site_category'],
                'route_or_group' => $row['route_or_group'],
                'board_width_ft' => $row['board_width_ft'] ? (int) $row['board_width_ft'] : null,
                'board_height_ft' => $row['board_height_ft'] ? (int) $row['board_height_ft'] : null,
                'latitude' => $row['latitude'] ? (float) $row['latitude'] : null,
                'longitude' => $row['longitude'] ? (float) $row['longitude'] : null,
                'client_name' => $row['client_name'] ?? null,
                'creative_url' => $creativeUrl,
                'last_monitored_at' => $row['last_monitored_at'],
                'last_monitored_by' => $row['last_monitored_by_name'] ?? null,
                'uploaded_today' => !empty($row['uploaded_today_at']),
                'uploaded_today_at' => $row['uploaded_today_at'],
                'open_issue_count' => (int) ($row['open_issue_count'] ?? 0),
            ];
        }, $rows);
    }
}
