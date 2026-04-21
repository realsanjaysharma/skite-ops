<?php

require_once __DIR__ . '/../services/AuthorityViewService.php';
require_once __DIR__ . '/../helpers/Response.php';

class AuthorityViewController {
    public function view(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new AuthorityViewService();
            $actorId = $_SESSION['user_id'];
            $allowedKeys = $_SESSION['allowed_module_keys'] ?? [];
            $result = $service->getView($_GET, $actorId, $allowedKeys);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function summary(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new AuthorityViewService();
            $actorId = $_SESSION['user_id'];
            $allowedKeys = $_SESSION['allowed_module_keys'] ?? [];
            $result = $service->getSummary($_GET, $actorId, $allowedKeys);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function shareHelper(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new AuthorityViewService();
            $actorId = $_SESSION['user_id'];
            $allowedKeys = $_SESSION['allowed_module_keys'] ?? [];
            $result = $service->getShareHelper($_GET, $actorId, $allowedKeys);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
