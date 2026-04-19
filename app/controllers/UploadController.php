<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../services/UploadStorageService.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/../../config/constants.php';

class UploadController
{
    private UploadService $uploadService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
    }

    /**
     * GET upload/serve?id={upload_id}
     *
     * Streams the stored file to the browser with correct Content-Type.
     * Requires valid session. Blocks access to purged/deleted files.
     */
    public function serve(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $uploadId = (int) ($_GET['id'] ?? 0);
        if ($uploadId <= 0) {
            Response::error('upload id is required', 400);
            return;
        }

        // Must be authenticated
        if (empty($_SESSION['user_id'])) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $repo = new UploadRepository();
            $upload = $repo->findById($uploadId);

            if (!$upload) {
                Response::error('Upload not found', 404);
                return;
            }

            if (!empty($upload['is_purged']) || !empty($upload['is_deleted'])) {
                Response::error('File has been removed', 410);
                return;
            }

            $storageService = new UploadStorageService();
            $absolutePath = $storageService->getAbsolutePath($upload['file_path']);

            if (!$absolutePath || !is_file($absolutePath)) {
                Response::error('File not found on disk', 404);
                return;
            }

            // Stream the file
            $mimeType = $upload['mime_type'] ?? 'application/octet-stream';
            $originalName = $upload['original_file_name'] ?? basename($absolutePath);

            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: inline; filename="' . $originalName . '"');
            header('Content-Length: ' . filesize($absolutePath));
            header('Cache-Control: private, max-age=3600');

            readfile($absolutePath);
            exit;
        } catch (Throwable $e) {
            Response::error('Failed to serve file', 500);
        }
    }

    /**
     * POST upload/create — shared cross-role upload creation endpoint.
     */
    public function createUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $_POST;
        
        $roleKey = $_SESSION['role_key'] ?? '';
        
        $surface = $this->resolveSurfaceFromRole($roleKey);
        if (!$surface) {
            Response::error('Role is not allowed to create uploads', 403);
            return;
        }

        if (empty($input['parent_type']) || empty($input['parent_id']) || empty($input['upload_type'])) {
            Response::error('Missing required fields: parent_type, parent_id, upload_type', 400);
            return;
        }

        $files = $_FILES['files'] ?? [];

        try {
            $result = $this->uploadService->createUploadsForSurface($surface, $input, $files, $_SESSION['user_id']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET upload/my-list — creator-scoped upload list for My Uploads page.
     *
     * Response strips authority_visibility and review-state fields per Page Spec §9:
     * no approval badge, no rejected badge, no authority-visibility status.
     */
    public function myList(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $roleKey = $_SESSION['role_key'] ?? '';
        $surface = $this->resolveSurfaceFromRole($roleKey);

        if (!$surface) {
            Response::error('Role is not allowed to view uploads', 403);
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? DEFAULT_PAGE_LIMIT)));

        $filters = [];
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        try {
            $result = $this->uploadService->listCreatorUploads(
                (int) $_SESSION['user_id'],
                $filters,
                $page,
                $limit
            );

            // Shape response: strip authority-visibility and review-state fields
            // per Page Spec §9 (Supervisor My Uploads)
            $result['items'] = array_map(
                static function (array $upload): array {
                    return [
                        'id'              => (int) $upload['id'],
                        'parent_type'     => $upload['parent_type'],
                        'parent_id'       => (int) $upload['parent_id'],
                        'parent_name'     => $upload['parent_type'] === 'SITE' 
                                                ? ($upload['site_name'] ?? null)
                                                : ($upload['belt_name'] ?? null),
                        'upload_type'     => $upload['upload_type'],
                        'work_type'       => $upload['work_type'],
                        'is_discovery_mode' => (int) ($upload['is_discovery_mode'] ?? 0),
                        'comment_preview' => !empty($upload['comment_text'])
                            ? mb_substr($upload['comment_text'], 0, 80)
                            : null,
                        'created_at'      => $upload['created_at'],
                    ];
                },
                $result['items']
            );

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST upload/delete — self-delete within 5-minute window.
     *
     * Service enforces: ownership check, time-window, ISSUE restriction,
     * and deletion/purge guard.
     */
    public function deleteUpload(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['upload_id']) || !is_numeric($input['upload_id'])) {
            Response::error('upload_id is required', 400);
            return;
        }

        try {
            $result = $this->uploadService->softDeleteUpload(
                (int) $input['upload_id'],
                (int) $_SESSION['user_id']
            );

            Response::success([
                'deleted'   => true,
                'upload_id' => (int) $result['id'],
            ]);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Map role_key to upload surface identifier.
     */
    private function resolveSurfaceFromRole(string $roleKey): ?string 
    {
        switch ($roleKey) {
            case 'GREEN_BELT_SUPERVISOR':
                return 'SUPERVISOR';
            case 'OUTSOURCED_MAINTAINER':
                return 'OUTSOURCED';
            case 'MONITORING_TEAM':
                return 'MONITORING';
            case 'FABRICATION_LEAD':
                return 'TASK';
            default:
                return null;
        }
    }

    /**
     * GET upload/cleanup-list
     */
    public function cleanupList(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (!in_array($_SESSION['role_key'] ?? '', ['OPS_MANAGER', 'MANAGEMENT'])) {
            Response::error('Access denied', 403);
            return;
        }

        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? DEFAULT_PAGE_LIMIT)));

        $filters = [];
        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }
        if (!empty($_GET['belt_id'])) {
            $filters['belt_id'] = (int)$_GET['belt_id'];
        }
        if (!empty($_GET['supervisor_user_id'])) {
            $filters['supervisor_user_id'] = (int)$_GET['supervisor_user_id'];
        }

        try {
            $result = $this->uploadService->getCleanupList($filters, $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST upload/purge
     */
    public function purge(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (!in_array($_SESSION['role_key'] ?? '', ['OPS_MANAGER', 'MANAGEMENT'])) {
            Response::error('Access denied', 403);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['upload_ids']) || !is_array($input['upload_ids'])) {
            Response::error('upload_ids array is required', 400);
            return;
        }

        try {
            $this->uploadService->purgeUploads($input['upload_ids'], (int)$_SESSION['user_id']);
            Response::success(['message' => 'Uploads purged successfully']);
        } catch (DomainException $e) {
             Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
