<?php

require_once __DIR__ . '/../repositories/FreeMediaRepository.php';
require_once __DIR__ . '/AuditService.php';

/**
 * FreeMediaService
 *
 * Purpose:
 * Business logic for free_media_records lifecycle.
 *
 * Schema status enum: DISCOVERED, CONFIRMED_ACTIVE, EXPIRED, CONSUMED
 */
class FreeMediaService
{
    private FreeMediaRepository $repo;
    private AuditService $auditService;

    public function __construct()
    {
        $this->repo = new FreeMediaRepository();
        $this->auditService = new AuditService();
    }

    public function listFreeMedia(array $params): array
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 50)));

        $filters = [];
        if (!empty($params['status'])) $filters['status'] = $params['status'];
        if (!empty($params['site_category'])) $filters['site_category'] = $params['site_category'];
        if (!empty($params['route_or_group'])) $filters['route_or_group'] = $params['route_or_group'];

        $items = $this->repo->getList($filters, $page, $limit);
        $total = $this->repo->countList($filters);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }

    /**
     * Confirm a DISCOVERED record → CONFIRMED_ACTIVE.
     */
    public function confirmRecord(int $recordId, string $confirmedDate, ?string $expiryDate, int $actorId): array
    {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new InvalidArgumentException("Record not found.");
        if ($record['status'] !== 'DISCOVERED') throw new DomainException("Only DISCOVERED records can be confirmed.");

        $this->repo->confirmRecord($recordId, $actorId, $confirmedDate, $expiryDate);

        $newState = $this->repo->findById($recordId);
        $this->auditService->logAction(
            $actorId,
            'FREE_MEDIA_CONFIRMED',
            'free_media_records',
            $recordId,
            ['status' => $record['status']],
            ['status' => $newState['status']]
        );
        return $newState;
    }

    /**
     * Expire a CONFIRMED_ACTIVE record → EXPIRED.
     */
    public function expireRecord(int $recordId, int $actorId): array
    {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new InvalidArgumentException("Record not found.");
        if ($record['status'] !== 'CONFIRMED_ACTIVE') {
            throw new DomainException("Only CONFIRMED_ACTIVE records can be expired.");
        }

        $this->repo->markExpired($recordId);

        $newState = $this->repo->findById($recordId);
        $this->auditService->logAction(
            $actorId,
            'FREE_MEDIA_EXPIRED',
            'free_media_records',
            $recordId,
            ['status' => $record['status']],
            ['status' => $newState['status']]
        );
        return $newState;
    }

    /**
     * Mark a CONFIRMED_ACTIVE record as CONSUMED (when assigned to a campaign).
     */
    public function consumeRecord(int $recordId, int $actorId): array
    {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new InvalidArgumentException("Record not found.");
        if ($record['status'] !== 'CONFIRMED_ACTIVE') {
            throw new DomainException("Only CONFIRMED_ACTIVE records can be consumed.");
        }

        $this->repo->markConsumed($recordId);

        $newState = $this->repo->findById($recordId);
        $this->auditService->logAction(
            $actorId,
            'FREE_MEDIA_CONSUMED',
            'free_media_records',
            $recordId,
            ['status' => $record['status']],
            ['status' => $newState['status']]
        );
        return $newState;
    }

    /**
     * Create a CONFIRMED_ACTIVE record from campaign end.
     */
    public function createFromCampaignEnd(int $campaignId, int $siteId, string $expiryDate, int $actorId): array
    {
        $data = [
            'site_id'              => $siteId,
            'source_type'          => 'CAMPAIGN_END',
            'source_reference_id'  => $campaignId,
            'discovered_date'      => date('Y-m-d'),
            'confirmed_by_user_id' => $actorId,
            'confirmed_date'       => date('Y-m-d'),
            'expiry_date'          => $expiryDate,
        ];

        $id = $this->repo->createConfirmedRecord($data);
        $newState = $this->repo->findById($id);
        $this->auditService->logAction(
            $actorId,
            'FREE_MEDIA_CONFIRMED_FROM_CAMPAIGN',
            'free_media_records',
            $id,
            null,
            ['status' => $newState['status'], 'source_type' => 'CAMPAIGN_END']
        );
        return $newState;
    }
}
