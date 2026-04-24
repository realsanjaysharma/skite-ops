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
}
