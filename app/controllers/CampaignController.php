<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/CampaignService.php';
require_once __DIR__ . '/../services/FreeMediaService.php';

class CampaignController {
    public function listCampaigns(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new CampaignService();
            $result = $service->listCampaigns($_GET);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function getCampaign(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $campaignId = isset($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : 0;
        if (!$campaignId) {
            Response::error('campaign_id is required', 400);
            return;
        }

        try {
            $service = new CampaignService();
            $result = $service->getCampaign($campaignId);
            if (!$result) {
                Response::error('Campaign not found', 404);
                return;
            }
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function createCampaign(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        try {
            $service = new CampaignService();
            $actorId = $_SESSION['user_id'];
            $result = $service->createCampaign($input, $actorId);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function updateCampaign(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
        if (!$campaignId) {
            Response::error('campaign_id is required', 400);
            return;
        }

        try {
            $service = new CampaignService();
            $actorId = $_SESSION['user_id'];
            $result = $service->updateCampaign($campaignId, $input, $actorId);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function endCampaign(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
        if (!$campaignId) {
            Response::error('campaign_id is required', 400);
            return;
        }
        if (empty($input['actual_end_date'])) {
            Response::error('actual_end_date is required', 400);
            return;
        }

        try {
            $service = new CampaignService();
            $actorId = $_SESSION['user_id'];
            $result = $service->endCampaign($campaignId, $input['actual_end_date'], $actorId);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function confirmFreeMedia(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['campaign_id']) || empty($input['site_id']) || empty($input['expiry_date'])) {
            Response::error('campaign_id, site_id, and expiry_date are required', 400);
            return;
        }

        try {
            $service = new FreeMediaService();
            $actorId = $_SESSION['user_id'];
            $result = $service->createFromCampaignEnd((int)$input['campaign_id'], (int)$input['site_id'], $input['expiry_date'], $actorId);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
