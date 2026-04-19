<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/FreeMediaService.php';

/**
 * FreeMediaController
 *
 * Purpose:
 * HTTP layer for free_media_records lifecycle.
 *
 * Schema status enum: DISCOVERED → CONFIRMED_ACTIVE → EXPIRED | CONSUMED
 */
class FreeMediaController
{
    public function listFreeMedia(): void
    {
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

    /**
     * POST freemedia/confirm
     * 
     * Transitions DISCOVERED → CONFIRMED_ACTIVE.
     */
    public function confirmRecord(): void
    {
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
            $result = $service->confirmRecord(
                (int)$input['record_id'],
                $input['confirmed_date'],
                $input['expiry_date'] ?? null,
                (int)$_SESSION['user_id']
            );
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST freemedia/expire
     * 
     * Transitions CONFIRMED_ACTIVE → EXPIRED.
     */
    public function expireRecord(): void
    {
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
            $result = $service->expireRecord(
                (int)$input['record_id'],
                (int)$_SESSION['user_id']
            );
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST freemedia/consume
     * 
     * Transitions CONFIRMED_ACTIVE → CONSUMED.
     */
    public function consumeRecord(): void
    {
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
            $result = $service->consumeRecord(
                (int)$input['record_id'],
                (int)$_SESSION['user_id']
            );
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
