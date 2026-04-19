<?php

class AuthorityViewService {
    private AuthorityViewRepository $repo;

    public function __construct() {
        $this->repo = new AuthorityViewRepository();
    }

    public function getView(array $params, int $actorId, array $allowedModuleKeys): array {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 50)));

        $filters = [];
        if (!empty($params['date'])) $filters['date'] = $params['date'];
        if (!empty($params['belt_id'])) $filters['belt_id'] = $params['belt_id'];
        if (!empty($params['supervisor_user_id'])) $filters['supervisor_user_id'] = $params['supervisor_user_id'];
        if (!empty($params['work_type'])) $filters['work_type'] = $params['work_type'];

        $allowedBeltIds = null;
        if (!in_array('green_belt.master', $allowedModuleKeys, true)) {
            $allowedBeltIds = $this->repo->getAssignedBeltIdsForAuthority($actorId);
        }

        $items = $this->repo->getList($filters, $page, $limit, $allowedBeltIds);
        $total = $this->repo->countList($filters, $allowedBeltIds);

        foreach ($items as &$item) {
            $item['upload_id'] = (int)$item['upload_id'];
            $item['preview_photo'] = $item['file_path'];
            unset($item['file_path']);
            
            $hour = (int)date('H', strtotime($item['timestamp']));
            $item['time_of_day'] = ($hour < 12) ? 'Morning' : 'Evening';
            
            if (!empty($item['gps_latitude']) && !empty($item['gps_longitude'])) {
                $item['gps_string'] = $item['gps_latitude'] . ', ' . $item['gps_longitude'];
            } else {
                $item['gps_string'] = null;
            }
            unset($item['gps_latitude'], $item['gps_longitude']);
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

    public function getSummary(array $params, int $actorId, array $allowedModuleKeys): array {
        $filters = [];
        if (!empty($params['date'])) $filters['date'] = $params['date'];
        if (!empty($params['belt_id'])) $filters['belt_id'] = $params['belt_id'];
        if (!empty($params['supervisor_user_id'])) $filters['supervisor_user_id'] = $params['supervisor_user_id'];
        if (!empty($params['work_type'])) $filters['work_type'] = $params['work_type'];

        $allowedBeltIds = null;
        if (!in_array('green_belt.master', $allowedModuleKeys, true)) {
            $allowedBeltIds = $this->repo->getAssignedBeltIdsForAuthority($actorId);
        }

        return $this->repo->getSummaryStats($filters, $allowedBeltIds);
    }
    
    public function getShareHelper(array $params, int $actorId, array $allowedModuleKeys): array {
        $summary = $this->getSummary($params, $actorId, $allowedModuleKeys);
        
        $dateStr = !empty($params['date']) ? $params['date'] : date('Y-m-d');
        
        $lines = [];
        $lines[] = "*Skite Ops Authority Summary*";
        $lines[] = "Date: " . $dateStr;
        if (!empty($params['work_type'])) {
            $lines[] = "Work Type: " . $params['work_type'];
        }
        $lines[] = "";
        $lines[] = "- Active Belts: " . $summary['total_belts'];
        $lines[] = "- Morning Photos: " . $summary['total_morning_photos'];
        $lines[] = "- Evening Photos: " . $summary['total_evening_photos'];
        $lines[] = "- Total Photos: " . $summary['total_photos'];
        
        $messageText = implode("\n", $lines);
        $encodedText = urlencode($messageText);
        $whatsappUrl = "https://wa.me/?text=" . $encodedText;
        
        return [
            'message_text' => $messageText,
            'whatsapp_url' => $whatsappUrl
        ];
    }
}
