<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/AttendanceService.php';

class AttendanceController
{
    private AttendanceService $attendanceService;

    public function __construct()
    {
        $this->attendanceService = new AttendanceService();
    }

    /**
     * GET attendance/list
     * Query params: date, supervisor_user_id
     */
    public function listAttendanceRecords(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $actorRoleKey = $_SESSION['role_key'] ?? '';

            if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR'])) {
                Response::error('Forbidden', 403);
                return;
            }

            $filters = [
                'date' => $_GET['date'] ?? null,
                'supervisor_user_id' => $_GET['supervisor_user_id'] ?? null,
            ];

            $items = $this->attendanceService->listAttendanceRecords($filters);

            Response::success([
                'items' => $items,
                'pagination' => [
                    'page' => 1,
                    'limit' => count($items),
                    'total' => count($items)
                ]
            ]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/mark
     * JSON body: supervisor_user_id, attendance_date, status, override_reason
     */
    public function markAttendance(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

        if (empty($input['supervisor_user_id']) || empty($input['attendance_date']) || empty($input['status'])) {
            Response::error('Missing required fields: supervisor_user_id, attendance_date, status', 400);
            return;
        }

        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR'])) {
            Response::error('Forbidden', 403);
            return;
        }

        try {
            $result = $this->attendanceService->markAttendance($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
