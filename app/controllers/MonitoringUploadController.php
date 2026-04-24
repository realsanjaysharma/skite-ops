<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/UploadService.php';

/**
 * MonitoringUploadController
 *
 * Purpose:
 * Handles the MONITORING_TEAM landing page route (monitoring/upload).
 * Returns their recent monitoring uploads scoped to their creator_user_id.
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
}
