<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/MonitoringUploadService.php';
require_once __DIR__ . '/../services/UploadService.php';
require_once __DIR__ . '/../repositories/SiteRepository.php';

/**
 * MonitoringUploadController
 *
 * Handles monitoring upload page endpoints:
 * - Landing (recent uploads)
 * - Enriched site queries (planned, browse, search)
 * - Shift lifecycle (start, complete, status)
 * - Quick issue report + issue resolution from field
 * - Creative upload for site detail page
 */
class MonitoringUploadController extends BaseController
{
    private UploadService $uploadService;
    private MonitoringUploadService $monService;

    public function __construct()
    {
        $this->uploadService = new UploadService();
        $this->monService = new MonitoringUploadService();
    }

    /**
     * GET monitoring/upload — recent uploads for the monitoring landing page.
     */
    public function index(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $filters = [
                'date_from' => $_GET['date_from'] ?? null,
                'date_to'   => $_GET['date_to'] ?? null,
            ];
            $page  = max(1, (int) ($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int) ($_GET['limit'] ?? 50)));

            $result = $this->uploadService->listCreatorUploads($actor['user_id'], $filters, $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/site-search?q=... — search by client/location/code.
     */
    public function siteSearch(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if (!in_array($actor['role_key'], ['MONITORING_TEAM', 'OPS_MANAGER'], true)) {
            Response::error('Access denied', 403);
            return;
        }

        $query = trim($_GET['q'] ?? '');
        if (strlen($query) < 1) {
            Response::success(['items' => []]);
            return;
        }

        try {
            $result = $this->monService->searchSites($query, $actor['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/browse-routes?category=HIGHWAY — distinct routes with counts.
     */
    public function browseRoutes(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $category = strtoupper(trim($_GET['category'] ?? ''));
        if (empty($category)) {
            Response::error('category is required', 400);
            return;
        }

        try {
            $routes = $this->monService->getRoutesByCategory($category);
            Response::success(['routes' => $routes]);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET monitoring/browse-sites?category=HIGHWAY&route=NH-24 — filtered site list.
     */
    public function browseSites(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $category = strtoupper(trim($_GET['category'] ?? ''));
        $route = trim($_GET['route'] ?? '');
        if (empty($category) || empty($route)) {
            Response::error('category and route are required', 400);
            return;
        }

        try {
            $result = $this->monService->browseSites($category, $route, $actor['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/start-shift — begin today's monitoring shift.
     */
    public function startShift(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $shift = $this->monService->startShift($actor['user_id']);
            Response::success($shift);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/complete-shift — end today's monitoring shift.
     */
    public function completeShift(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        try {
            $shift = $this->monService->completeShift($actor['user_id']);
            Response::success($shift);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST monitoring/resolve-issue — resolve issue from field with photo proof.
     * Photos must be uploaded separately via upload/create first.
     * Body: { issue_id, comment_text? }
     */
    public function resolveIssue(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if ($actor['role_key'] !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $input = $this->getInput();
        $issueId = (int) ($input['issue_id'] ?? 0);
        if (!$issueId) {
            Response::error('issue_id is required', 400);
            return;
        }

        try {
            $result = $this->monService->resolveIssueFromField(
                $issueId,
                $input['comment_text'] ?? null,
                $actor['user_id']
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST site/upload-creative — upload creative artwork for a site.
     * Accepts multipart form: site_id + single image file.
     * Updates sites.creative_upload_id to point to the new upload.
     *
     * Access: CLIENT_SERVICING, OPS_MANAGER, MEDIA_PLANNING
     */
    public function uploadCreative(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        $allowedRoles = ['CLIENT_SERVICING', 'OPS_MANAGER', 'MEDIA_PLANNING'];
        if (!in_array($actor['role_key'], $allowedRoles, true)) {
            Response::error('Access denied', 403);
            return;
        }

        $siteId = (int) ($_POST['site_id'] ?? 0);
        if (!$siteId) {
            Response::error('site_id is required', 400);
            return;
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            Response::error('A single image file is required', 400);
            return;
        }

        try {
            $siteRepo = new SiteRepository();
            $site = $siteRepo->findById($siteId);
            if (!$site) {
                Response::error('Site not found', 404);
                return;
            }

            // Upload the creative as a regular SITE upload
            $data = [
                'parent_type' => 'SITE',
                'parent_id' => $siteId,
                'upload_type' => 'WORK',
                'photo_label' => 'GENERAL',
                'comment_text' => 'Creative artwork',
            ];

            $result = $this->uploadService->createUploadsForSurface(
                'MONITORING',
                $data,
                ['file' => $_FILES['file']],
                $actor['user_id']
            );

            // Update the site's creative_upload_id
            $uploadId = $result['created_uploads'][0]['id'] ?? null;
            if ($uploadId) {
                $siteRepo->updateCreative($siteId, $uploadId);
            }

            Response::success([
                'site_id' => $siteId,
                'creative_upload_id' => $uploadId,
                'creative_url' => $uploadId ? "../index.php?route=upload/serve&id={$uploadId}" : null,
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
