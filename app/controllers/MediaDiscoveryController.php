<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/MediaDiscoveryService.php';

/**
 * MediaDiscoveryController
 *
 * Purpose:
 * Handles the media-discovery page endpoints for MONITORING_TEAM.
 *
 * Routes:
 *   POST discovery/submit    -> submit a new discovery (photos + optional GPS + comment)
 *   GET  discovery/my-list   -> list the actor's own discovery uploads with pagination
 *
 * Architecture: Controller -> Service -> Repository -> Database
 */
class MediaDiscoveryController extends BaseController
{
    private MediaDiscoveryService $discoveryService;

    public function __construct()
    {
        $this->discoveryService = new MediaDiscoveryService();
    }

    /**
     * POST discovery/submit
     * Accepts multipart form-data with photos[] + optional GPS + free-text comment.
     */
    public function submit(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        if (($actor['role_key'] ?? '') !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $rawFiles = $_FILES['photos'] ?? [];
        if (empty($rawFiles) || empty($rawFiles['name']) || empty($rawFiles['name'][0])) {
            Response::error('At least one photo is required.', 422);
            return;
        }

        $data = [
            'comment_text' => $_POST['comment_text'] ?? null,
            'browser_lat'  => $_POST['browser_lat'] ?? null,
            'browser_lng'  => $_POST['browser_lng'] ?? null,
            'exif_lat'     => $_POST['exif_lat'] ?? null,
            'exif_lng'     => $_POST['exif_lng'] ?? null,
        ];

        try {
            $result = $this->discoveryService->submitDiscovery($data, $rawFiles, (int)$actor['user_id']);
            Response::success($result);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 422);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET discovery/my-list
     * Returns the actor's own discovery uploads with pagination.
     */
    public function myList(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();
        if (($actor['role_key'] ?? '') !== 'MONITORING_TEAM') {
            Response::error('Access denied', 403);
            return;
        }

        $page  = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));

        try {
            $result = $this->discoveryService->listMyDiscoveries((int)$actor['user_id'], $page, $limit);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
