<?php

require_once __DIR__ . '/../repositories/SiteRepository.php';
require_once __DIR__ . '/AuditService.php';

class SiteService {
    private SiteRepository $repo;
    private AuditService $auditService;

    const ALLOWED_CATEGORIES = ['GREEN_BELT', 'CITY', 'HIGHWAY'];
    const ALLOWED_LIGHTING_TYPES = ['LIT', 'NON_LIT'];

    public function __construct() {
        $this->repo = new SiteRepository();
        $this->auditService = new AuditService();
    }

    public function listSites(array $params): array {
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $limit = isset($params['limit']) ? (int)$params['limit'] : 50;

        if ($page < 1) $page = 1;
        if ($limit < 1 || $limit > 100) $limit = 50;

        $filters = [];
        if (isset($params['site_category'])) {
            $filters['site_category'] = $params['site_category'];
        }
        if (isset($params['lighting_type'])) {
            $filters['lighting_type'] = $params['lighting_type'];
        }
        if (isset($params['is_active'])) {
            $filters['is_active'] = $params['is_active'];
        }
        if (isset($params['route_or_group'])) {
            $filters['route_or_group'] = $params['route_or_group'];
        }
        if (isset($params['green_belt_id'])) {
            $filters['green_belt_id'] = $params['green_belt_id'];
        }

        $items = $this->repo->findAll($filters, $page, $limit);
        $total = $this->repo->countAll($filters);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }

    public function getSite(int $siteId): ?array {
        return $this->repo->findById($siteId);
    }

    public function createSite(array $data, int $actorUserId): array {
        if (empty($data['site_code'])) {
            throw new Exception("site_code is required");
        }
        if (empty($data['site_category']) || !in_array($data['site_category'], self::ALLOWED_CATEGORIES)) {
            throw new Exception("Invalid or missing site_category");
        }
        if (empty($data['lighting_type']) || !in_array($data['lighting_type'], self::ALLOWED_LIGHTING_TYPES)) {
            throw new Exception("Invalid or missing lighting_type");
        }

        $existing = $this->repo->findBySiteCode($data['site_code']);
        if ($existing) {
            throw new Exception("A site with this site_code already exists.");
        }

        $this->repo->beginTransaction();
        try {
            $id = $this->repo->create($data);

            $this->auditService->log(
                $actorUserId,
                'SITE_CREATED',
                'sites',
                $id,
                null,
                $data,
                null
            );

            $this->repo->commit();
            return $this->repo->findById($id);
        } catch (Throwable $t) {
            $this->repo->rollback();
            throw $t;
        }
    }

    public function updateSite(int $siteId, array $data, int $actorUserId): array {
        $existing = $this->repo->findById($siteId);
        if (!$existing) {
            throw new Exception("Site not found");
        }

        if (empty($data['site_category']) || !in_array($data['site_category'], self::ALLOWED_CATEGORIES)) {
            throw new Exception("Invalid or missing site_category");
        }
        if (empty($data['lighting_type']) || !in_array($data['lighting_type'], self::ALLOWED_LIGHTING_TYPES)) {
            throw new Exception("Invalid or missing lighting_type");
        }

        $this->repo->beginTransaction();
        try {
            $this->repo->update($siteId, $data);

            // Fetch new state for audit
            $newState = $this->repo->findById($siteId);

            $this->auditService->log(
                $actorUserId,
                'SITE_UPDATED',
                'sites',
                $siteId,
                $existing,
                $newState,
                null
            );

            $this->repo->commit();
            return $newState;
        } catch (Throwable $t) {
            $this->repo->rollback();
            throw $t;
        }
    }
}
