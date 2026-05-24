<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

/**
 * MonitoringUploadController
 *
 * Purpose:
 * Handles the MONITORING_TEAM landing page route (monitoring/upload)
 * and ad-hoc site search for monitoring upload form.
 */
class MonitoringUploadController extends BaseController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }

    /**
     * GET monitoring/upload
     * Landing route for MONITORING_TEAM.
     * Returns their recent monitoring uploads.
     */
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $roleKey = $_SESSION['role_key'] ?? '';
        if ($roleKey !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];

        try {
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to'   => $_GET['date_to'] ?? null,
            ];
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

            $result = $this->uploadService->listCreatorUploads($actorUserId, $filters, $page, $limit);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/site-search?q=...
     * Searches active sites by site_code prefix for ad-hoc monitoring uploads.
     * Returns up to 20 matches.
     */
    public function siteSearch(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $roleKey = $_SESSION['role_key'] ?? '';
        if ($roleKey !== 'MONITORING_TEAM' && $roleKey !== 'OPS_MANAGER') {
            Response::error('Access denied', 403);
            return;
        }

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 1) {
            Response::success(['items' => []]);
            return;
        }

        try {
            $siteRepo = new SiteRepository();
            $rows = $siteRepo->searchBySiteCode($query);

            $items = array_map(static function (array $row): array {
                return [
                    'id'       => (int) $row['id'],
                    'label'    => trim($row['site_code'] . ' - ' . $row['location_text']),
                    'category' => $row['site_category'],
                ];
            }, $rows);

            Response::success(['items' => $items]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
