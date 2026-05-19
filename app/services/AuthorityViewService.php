<?php
require_once __DIR__ . '/../repositories/AuthorityViewRepository.php';

class AuthorityViewService {
    private AuthorityViewRepository $repo;

    public function __construct() {
        $this->repo = new AuthorityViewRepository();
    }

    public function getView(array $params, int $actorId, array $allowedModuleKeys): array {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, min(100, (int)($params['limit'] ?? 50)));

        $filters = $this->buildFilters($params);

        $allowedBeltIds = $this->resolveAllowedBeltIds($actorId, $allowedModuleKeys);

        $items = $this->repo->getList($filters, $page, $limit, $allowedBeltIds);
        $total = $this->repo->countList($filters, $allowedBeltIds);

        foreach ($items as &$item) {
            $item['upload_id'] = (int)$item['upload_id'];
            $item['belt_id'] = isset($item['belt_id']) ? (int)$item['belt_id'] : null;
            $item['file_size_bytes'] = isset($item['file_size_bytes']) ? (int)$item['file_size_bytes'] : 0;
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
        $filters = $this->buildFilters($params);
        $allowedBeltIds = $this->resolveAllowedBeltIds($actorId, $allowedModuleKeys);

        return $this->repo->getSummaryStats($filters, $allowedBeltIds);
    }

    /**
     * Belt options for the Authority View filter dropdown.
     *
     * Returns every belt ever assigned to the AR (active + historical) so the
     * AR can browse photos from past assignments. Ops-equivalent roles
     * (anyone with green_belt.master access) get the full belt catalogue
     * via the normal belt/list route, not this one.
     */
    public function getBeltOptionsForAuthority(int $actorId, array $allowedModuleKeys, array $params = []): array {
        if (in_array('green_belt.master', $allowedModuleKeys, true)) {
            // Ops-equivalent roles should use belt/list; refuse here so we do
            // not silently bypass that catalogue path.
            throw new RuntimeException('belt-options is only for authority-scoped roles');
        }
        $rows = $this->repo->getBeltOptionsForAuthority($actorId);

        // Compute per-belt photo counts under the same filters the gallery uses,
        // so the dropdown label can show "GB-001 — North Garden (12)".
        $countFilters = $this->buildFilters($params);
        unset($countFilters['belt_id']); // count for every belt option, not just the selected one
        $allBeltIds = array_map(static fn($r) => (int)$r['id'], $rows);
        $counts = [];
        if (!empty($allBeltIds)) {
            foreach ($this->repo->getBeltPhotoCounts($countFilters, $allBeltIds) as $crow) {
                $counts[(int)$crow['belt_id']] = (int)$crow['photo_count'];
            }
        }

        $options = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $count = $counts[$id] ?? 0;
            $base = $row['belt_code'] . ' — ' . $row['common_name'];
            $options[] = [
                'id' => $id,
                'belt_code' => $row['belt_code'],
                'common_name' => $row['common_name'],
                'photo_count' => $count,
                'label' => $base . ' (' . $count . ')',
            ];
        }
        return ['items' => $options];
    }

    private function buildFilters(array $params): array {
        $filters = [];
        if (!empty($params['date'])) $filters['date'] = $params['date'];
        if (!empty($params['date_from'])) $filters['date_from'] = $params['date_from'];
        if (!empty($params['date_to'])) $filters['date_to'] = $params['date_to'];
        if (!empty($params['belt_id'])) $filters['belt_id'] = $params['belt_id'];
        if (!empty($params['supervisor_user_id'])) $filters['supervisor_user_id'] = $params['supervisor_user_id'];
        if (!empty($params['work_type'])) $filters['work_type'] = $params['work_type'];
        return $filters;
    }

    private function resolveAllowedBeltIds(int $actorId, array $allowedModuleKeys): ?array {
        if (in_array('green_belt.master', $allowedModuleKeys, true)) {
            return null;
        }
        // Include historical assignments per product owner decision 2026-05-18:
        // AR can view photos from belts they were previously assigned to.
        return $this->repo->getAllAssignedBeltIdsForAuthority($actorId);
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
