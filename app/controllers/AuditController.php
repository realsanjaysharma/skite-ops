<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/AuditService.php';
require_once __DIR__ . '/../../config/constants.php';

class AuditController extends BaseController
{
    private AuditService $auditService;

    public function __construct()
    {
        $this->auditService = new AuditService();
    }

    /**
     * GET audit/list
     */
    public function list(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (empty($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
            return;
        }

        // Default strict filtering:
        // Ops/Management can see all.
        // Others can only see their own actions.
        $roleKey = $_SESSION['role_key'] ?? '';
        $isOpsOrManagement = in_array($roleKey, ['OPS_MANAGER', 'MANAGEMENT']);

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? DEFAULT_PAGE_LIMIT)));

        $filters = [];
        if (!empty($_GET['action_type'])) {
            $filters['action_type'] = $_GET['action_type'];
        }
        if (!empty($_GET['entity_type'])) {
            $filters['entity_type'] = $_GET['entity_type'];
        }
        if (!empty($_GET['entity_id'])) {
            $filters['entity_id'] = (int) $_GET['entity_id'];
        }
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        if (!$isOpsOrManagement) {
            // Force scoped filter
            $filters['actor_user_id'] = (int) $_SESSION['user_id'];
        } elseif (!empty($_GET['actor_user_id'])) {
            // Ops requested specific user
            $filters['actor_user_id'] = (int) $_GET['actor_user_id'];
        }

        try {
            $result = $this->auditService->listAudits($filters, $page, $limit);
            
            // Format log contents safely for UI (decode inner JSON if possible)
            $result['items'] = array_map(function ($item) {
                $item['old_values'] = $item['old_values_json'] ? json_decode($item['old_values_json'], true) : null;
                $item['new_values'] = $item['new_values_json'] ? json_decode($item['new_values_json'], true) : null;
                unset($item['old_values_json']);
                unset($item['new_values_json']);
                return $item;
            }, $result['items']);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
