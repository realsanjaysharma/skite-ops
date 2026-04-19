<?php

class FreeMediaService {
    private FreeMediaRepository $repo;
    private AuditService $auditService;

    public function __construct() {
        $this->repo = new FreeMediaRepository();
        $this->auditService = new AuditService();
    }

    public function listFreeMedia(array $params): array {
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

    public function confirmRecord(int $recordId, string $confirmedDate, ?string $notes, int $actorId): array {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new Exception("Record not found");
        if ($record['status'] !== 'DISCOVERED') throw new Exception("Only DISCOVERED records can be confirmed");

        $this->repo->updateStatus($recordId, 'CONFIRMED', 'confirmed_date', $confirmedDate, $notes);
        
        $newState = $this->repo->findById($recordId);
        $this->auditService->log($actorId, 'FREE_MEDIA_CONFIRMED', 'free_media_records', $recordId, $record, $newState, null);
        return $newState;
    }

    public function resolveRecord(int $recordId, string $resolvedDate, ?string $notes, int $actorId): array {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new Exception("Record not found");
        if (!in_array($record['status'], ['DISCOVERED', 'CONFIRMED'])) {
            throw new Exception("Record cannot be resolved from its current status");
        }

        $this->repo->updateStatus($recordId, 'RESOLVED', 'resolved_date', $resolvedDate, $notes);
        
        $newState = $this->repo->findById($recordId);
        $this->auditService->log($actorId, 'FREE_MEDIA_RESOLVED', 'free_media_records', $recordId, $record, $newState, null);
        return $newState;
    }

    public function markInvalid(int $recordId, ?string $notes, int $actorId): array {
        $record = $this->repo->findById($recordId);
        if (!$record) throw new Exception("Record not found");
        if ($record['status'] !== 'DISCOVERED') {
            throw new Exception("Only DISCOVERED records can be marked invalid");
        }

        $this->repo->updateStatus($recordId, 'INVALID', 'resolved_date', date('Y-m-d'), $notes);
        
        $newState = $this->repo->findById($recordId);
        $this->auditService->log($actorId, 'FREE_MEDIA_INVALIDATED', 'free_media_records', $recordId, $record, $newState, null);
        return $newState;
    }

    public function createFromCampaignEnd(int $campaignId, int $siteId, string $expiryDate, int $actorId): array {
        $data = [
            'site_id' => $siteId,
            'source_type' => 'CAMPAIGN_END',
            'source_reference_id' => $campaignId,
            'discovered_date' => date('Y-m-d'),
            'confirmed_date' => date('Y-m-d'),
            'notes' => 'Confirmed from campaign end. Expected Expiry: ' . $expiryDate,
            'created_by_user_id' => $actorId
        ];
        
        $id = $this->repo->createConfirmedRecord($data);
        $newState = $this->repo->findById($id);
        $this->auditService->log($actorId, 'FREE_MEDIA_CONFIRMED_FROM_CAMPAIGN', 'free_media_records', $id, null, $newState, null);
        return $newState;
    }
}
