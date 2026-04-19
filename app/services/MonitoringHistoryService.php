<?php

class MonitoringHistoryService {
    private MonitoringHistoryRepository $repo;

    public function __construct() {
        $this->repo = new MonitoringHistoryRepository();
    }

    public function getHistory(array $params): array {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 50)));

        $filters = [];
        if (!empty($params['date_from'])) $filters['date_from'] = $params['date_from'];
        if (!empty($params['date_to'])) $filters['date_to'] = $params['date_to'];
        if (!empty($params['site_id'])) $filters['site_id'] = $params['site_id'];
        if (!empty($params['site_category'])) $filters['site_category'] = $params['site_category'];
        if (!empty($params['client_name'])) $filters['client_name'] = $params['client_name'];
        if (!empty($params['campaign_id'])) $filters['campaign_id'] = $params['campaign_id'];
        if (isset($params['discovery_mode']) && $params['discovery_mode'] !== '') {
            $filters['discovery_mode'] = filter_var($params['discovery_mode'], FILTER_VALIDATE_BOOLEAN);
        }

        $items = $this->repo->getHistory($filters, $page, $limit);
        
        foreach ($items as &$item) {
            $item['upload_id'] = (int) $item['upload_id'];
            $item['site_id'] = (int) $item['site_id'];
            $item['is_discovery_mode'] = (int) $item['is_discovery_mode'];
            $item['has_comment'] = !empty($item['comment_text']);
            $item['preview_photo'] = $item['file_path'];
            unset($item['file_path']);
        }

        $total = $this->repo->countHistory($filters);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total
            ]
        ];
    }
}
