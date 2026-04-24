<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/AttendanceService.php';

/**
 * AttendanceController
 *
 * Architecture: Controller handles HTTP shape only.
 * Role/capability access is enforced by AuthMiddleware (module_key + capability).
 * Same-day constraints, override rules, and record scope are enforced in AttendanceService.
 */
class AttendanceController extends BaseController
{
    private AttendanceService $attendanceService;

    public function __construct()
    {
        $this->attendanceService = new AttendanceService();
    }

    /**
     * GET attendance/list
     */
    public function listAttendanceRecords(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $filters = [
                'date'                => $_GET['date'] ?? null,
                'supervisor_user_id'  => $_GET['supervisor_user_id'] ?? null,
            ];

            $items = $this->attendanceService->listAttendanceRecords($filters);

            Response::success([
                'items'      => $items,
                'pagination' => ['page' => 1, 'limit' => count($items), 'total' => count($items)],
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/mark
     */
    public function markAttendance(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = $this->getInput();

        if (empty($input['supervisor_user_id']) || empty($input['attendance_date']) || empty($input['status'])) {
            Response::error('Missing required fields: supervisor_user_id, attendance_date, status', 400);
            return;
        }

        try {
            $result = $this->attendanceService->markAttendance(
                $input,
                (int) $_SESSION['user_id'],
                (string) ($_SESSION['role_key'] ?? '')
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
