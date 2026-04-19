<?php

class FreeMediaController {
    public function listFreeMedia(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $service = new FreeMediaService();
            $result = $service->listFreeMedia($_GET);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function confirmRecord(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['record_id']) || empty($input['confirmed_date'])) {
            Response::error('record_id and confirmed_date are required', 400);
            return;
        }

        try {
            $service = new FreeMediaService();
            $result = $service->confirmRecord((int)$input['record_id'], $input['confirmed_date'], $input['notes'] ?? null, $_SESSION['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function resolveRecord(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['record_id']) || empty($input['resolved_date'])) {
            Response::error('record_id and resolved_date are required', 400);
            return;
        }

        try {
            $service = new FreeMediaService();
            $result = $service->resolveRecord((int)$input['record_id'], $input['resolved_date'], $input['notes'] ?? null, $_SESSION['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    public function markInvalid(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        if (empty($input['record_id'])) {
            Response::error('record_id is required', 400);
            return;
        }

        try {
            $service = new FreeMediaService();
            $result = $service->markInvalid((int)$input['record_id'], $input['notes'] ?? null, $_SESSION['user_id']);
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
