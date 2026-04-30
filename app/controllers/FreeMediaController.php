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
class FreeMediaController extends BaseController
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

    /**
     * GET media/planning-view
     * Auth: MEDIA_PLANNING (VIEW group)
     *
     * Returns free_media_records enriched with site info and next monitoring due date.
     * Useful for planners prioritising which free-media sites to push.
     * Read-only.
     */
    public function planningView(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $repo = new FreeMediaRepository();
            $filters = [];
            if (!empty($_GET['status']))        $filters['status']        = $_GET['status'];
            if (!empty($_GET['site_category'])) $filters['site_category'] = $_GET['site_category'];
            if (!empty($_GET['route_or_group'])) $filters['route_or_group'] = $_GET['route_or_group'];

            $page  = max(1, (int)($_GET['page'] ?? 1));
            $limit = 30;

            // Use the existing repo for base list, then enrich with next monitoring due
            $db = Database::getConnection();

            $where = ["1=1"];
            $params = [];
            if (!empty($filters['status'])) {
                $where[] = "fm.status = ?";
                $params[] = $filters['status'];
            }
            if (!empty($filters['site_category'])) {
                $where[] = "s.site_category = ?";
                $params[] = $filters['site_category'];
            }
            if (!empty($filters['route_or_group'])) {
                $where[] = "s.route_or_group = ?";
                $params[] = $filters['route_or_group'];
            }

            $whereClause = implode(' AND ', $where);
            $offset = ($page - 1) * $limit;

            $countStmt = $db->prepare("
                SELECT COUNT(*) FROM free_media_records fm
                INNER JOIN sites s ON s.id = fm.site_id
                WHERE {$whereClause}
            ");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare("
                SELECT fm.id, fm.status, fm.discovered_date, fm.confirmed_date, fm.expiry_date,
                       s.site_code, s.location_text, s.site_category, s.route_or_group,
                       fm.site_id,
                       (SELECT MIN(d.due_date) FROM site_monitoring_due_dates d
                        WHERE d.site_id = fm.site_id AND d.due_date >= CURDATE()) AS next_monitoring_due
                FROM free_media_records fm
                INNER JOIN sites s ON s.id = fm.site_id
                WHERE {$whereClause}
                ORDER BY fm.status ASC, fm.discovered_date DESC
                LIMIT {$limit} OFFSET {$offset}
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            Response::success([
                'items' => $items,
                'pagination' => [
                    'page'  => $page,
                    'limit' => $limit,
                    'total' => $total,
                ],
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
