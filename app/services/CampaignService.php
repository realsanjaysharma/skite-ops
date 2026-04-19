<?php

require_once __DIR__ . '/../repositories/CampaignRepository.php';
require_once __DIR__ . '/AuditService.php';

/**
 * CampaignService
 *
 * Purpose:
 * Business logic for campaigns and campaign_sites management.
 */
class CampaignService
{
    private CampaignRepository $repo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->repo = new CampaignRepository();
        $this->auditService = new AuditService();
    }

    public function listCampaigns(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 50)));

        $filters = [];
        if (!empty($params['status'])) $filters['status'] = $params['status'];
        if (!empty($params['client_name'])) $filters['client_name'] = $params['client_name'];
        if (!empty($params['site_category'])) $filters['site_category'] = $params['site_category'];

        $items = $this->repo->getList($filters, $page, $limit);
        $total = $this->repo->countList($filters);

        foreach ($items as &$item) {
            $item['active_sites_count'] = (int)$item['active_sites_count'];
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }

    public function getCampaign(int $campaignId): ?array
    {
        return $this->repo->findById($campaignId);
    }

    public function createCampaign(array $data, int $actorId): array
    {
        if (empty($data['campaign_code']) || empty($data['client_name']) || empty($data['campaign_name']) || empty($data['start_date']) || empty($data['expected_end_date'])) {
            throw new InvalidArgumentException("Missing required campaign fields.");
        }

        $siteIds = [];
        if (!empty($data['site_ids']) && is_array($data['site_ids'])) {
            $siteIds = array_map('intval', $data['site_ids']);
        }

        $this->repo->beginTransaction();
        try {
            $campaignId = $this->repo->createCampaign($data);

            if (!empty($siteIds)) {
                $this->repo->linkSites($campaignId, $siteIds, $data['start_date']);
            }

            $newState = $this->repo->findById($campaignId);

            $this->auditService->logAction(
                $actorId,
                'CAMPAIGN_CREATED',
                'campaigns',
                $campaignId,
                null,
                ['status' => $newState['status'], 'campaign_code' => $newState['campaign_code']]
            );

            $this->repo->commit();
            return $newState;
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    public function updateCampaign(int $campaignId, array $data, int $actorId): array
    {
        $campaign = $this->repo->findById($campaignId);
        if (!$campaign) {
            throw new InvalidArgumentException("Campaign not found.");
        }
        if ($campaign['status'] === 'ENDED') {
            throw new DomainException("Cannot edit an ended campaign.");
        }

        if (empty($data['client_name']) || empty($data['campaign_name']) || empty($data['expected_end_date'])) {
            throw new InvalidArgumentException("Missing required editable fields.");
        }

        $siteIds = [];
        if (isset($data['site_ids']) && is_array($data['site_ids'])) {
            $siteIds = array_map('intval', $data['site_ids']);
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->updateCampaign($campaignId, $data);

            if (isset($data['site_ids'])) {
                $currentActive = $this->repo->getActiveSiteIds($campaignId);
                $toAdd = array_diff($siteIds, $currentActive);
                $toRemove = array_diff($currentActive, $siteIds);

                $today = date('Y-m-d');
                if (!empty($toAdd)) {
                    $this->repo->linkSites($campaignId, $toAdd, $today);
                }
                if (!empty($toRemove)) {
                    $this->repo->releaseSites($campaignId, $toRemove, $today);
                }
            }

            $newState = $this->repo->findById($campaignId);

            $this->auditService->logAction(
                $actorId,
                'CAMPAIGN_UPDATED',
                'campaigns',
                $campaignId,
                ['status' => $campaign['status']],
                ['status' => $newState['status']]
            );

            $this->repo->commit();
            return $newState;
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }

    public function endCampaign(int $campaignId, string $actualEndDate, int $actorId): array
    {
        $campaign = $this->repo->findById($campaignId);
        if (!$campaign) {
            throw new InvalidArgumentException("Campaign not found.");
        }
        if ($campaign['status'] === 'ENDED') {
            throw new DomainException("Campaign is already ended.");
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->endCampaign($campaignId, $actualEndDate);

            $newState = $this->repo->findById($campaignId);

            $this->auditService->logAction(
                $actorId,
                'CAMPAIGN_ENDED',
                'campaigns',
                $campaignId,
                ['status' => $campaign['status']],
                ['status' => $newState['status'], 'actual_end_date' => $actualEndDate]
            );

            $this->repo->commit();
            return $newState;
        } catch (Throwable $e) {
            $this->repo->rollback();
            throw $e;
        }
    }
}
