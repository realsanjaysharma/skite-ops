<?php

require_once __DIR__ . '/../services/SystemSettingsService.php';
require_once __DIR__ . '/../helpers/Response.php';

class SystemSettingsController extends BaseController
{
    private $service;

    public function __construct()
    {
        $this->service = new SystemSettingsService();
    }

    public function list(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $settings = $this->service->listSettings();
            Response::success(['items' => $settings]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function update(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $this->getInput();
        $key = $input['setting_key'] ?? null;
        $value = $input['setting_value'] ?? null;
        
        $actorId = $_SESSION['user_id'] ?? 0;

        if (!$key) {
            Response::error('Missing required validation fields: setting_key');
            return;
        }

        try {
            $this->service->updateSetting($key, $value, $actorId);
            Response::success(['message' => 'Setting updated successfully']);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
