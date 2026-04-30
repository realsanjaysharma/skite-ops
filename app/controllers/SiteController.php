<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/SiteService.php';

class SiteController extends BaseController {
    public function listSites(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new SiteService();
            $result = $service->listSites($_GET);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function getSite(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $siteId = isset($_GET['site_id']) ? (int)$_GET['site_id'] : 0;
        if (!$siteId) {
            Response::error('site_id is required', 400);
            return;
        }

        try {
            $service = new SiteService();
            $site = $service->getSite($siteId);
            if (!$site) {
                Response::error('Site not found', 404);
                return;
            }
            Response::success($site);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function createSite(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $service = new SiteService();
            $actorId = $_SESSION['user_id'];
            $site = $service->createSite($input, $actorId);
            Response::success($site);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function updateSite(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $siteId = isset($input['site_id']) ? (int)$input['site_id'] : 0;
        if (!$siteId) {
            Response::error('site_id is required', 400);
            return;
        }

        try {
            $service = new SiteService();
            $actorId = $_SESSION['user_id'];
            $site = $service->updateSite($siteId, $input, $actorId);
            Response::success($site);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET media/client-library
     * Auth: SALES_TEAM, CLIENT_SERVICING (VIEW group)
     *
     * Returns approved WORK uploads for green belt sites.
     * Read-only — no mutations.
     */
    public function clientMediaLibrary(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $db = Database::getConnection();

            $where = ["u.authority_visibility = 'APPROVED'", "u.parent_type = 'GREEN_BELT'", "u.upload_type = 'WORK'", "u.is_deleted = 0", "u.is_purged = 0"];
            $params = [];

            if (!empty($_GET['belt_id'])) {
                $where[] = "u.parent_id = ?";
                $params[] = (int)$_GET['belt_id'];
            }
            if (!empty($_GET['date_from'])) {
                $where[] = "DATE(u.created_at) >= ?";
                $params[] = $_GET['date_from'];
            }
            if (!empty($_GET['date_to'])) {
                $where[] = "DATE(u.created_at) <= ?";
                $params[] = $_GET['date_to'];
            }
            if (!empty($_GET['work_type'])) {
                $where[] = "u.work_type = ?";
                $params[] = $_GET['work_type'];
            }

            $page  = max(1, (int)($_GET['page'] ?? 1));
            $limit = 30;
            $offset = ($page - 1) * $limit;
            $whereClause = implode(' AND ', $where);

            $total = $db->prepare("SELECT COUNT(*) FROM uploads u LEFT JOIN green_belts gb ON gb.id = u.parent_id WHERE {$whereClause}");
            $total->execute($params);
            $total = (int)$total->fetchColumn();

            $stmt = $db->prepare("
                SELECT u.id, u.created_at, u.work_type, u.comment_text,
                       gb.belt_code, gb.common_name AS belt_name
                FROM uploads u
                LEFT JOIN green_belts gb ON gb.id = u.parent_id
                WHERE {$whereClause}
                ORDER BY u.created_at DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'items' => $items,
                'pagination' => [
                    'page'  => $page,
                    'limit' => $limit,
                    'total' => $total,
                ],
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
