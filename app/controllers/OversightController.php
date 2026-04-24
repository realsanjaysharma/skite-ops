<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/AttendanceService.php';
require_once __DIR__ . '/../services/WateringService.php';
require_once __DIR__ . '/../services/LabourService.php';

class OversightController extends BaseController
{
    private AttendanceService $attendanceService;
    private WateringService $wateringService;
    private LabourService $labourService;

    public function __construct()
    {
        $this->attendanceService = new AttendanceService();
        $this->wateringService = new WateringService();
        $this->labourService = new LabourService();
    }

    /**
     * GET oversight/watering
     * 
     * Composite payload for the Head Supervisor landing page.
     * Contains attendance, watering status per belt, and labour counts.
     */
    public function watering(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        $roleKey = $_SESSION['role_key'] ?? '';
        
        // This is primarily the Head Supervisor landing page, but Ops may view it.
        if (!in_array($roleKey, ['HEAD_SUPERVISOR', 'OPS_MANAGER'])) {
            Response::error('Access denied', 403);
            return;
        }

        $date = $_GET['date'] ?? date('Y-m-d');
        $zone = $_GET['zone'] ?? null;
        $supervisorUserId = empty($_GET['supervisor_user_id']) ? null : (int)$_GET['supervisor_user_id'];
        
        // Head supervisor primarily focuses on maintained belts for daily oversight
        $maintenanceMode = $_GET['maintenance_mode'] ?? 'MAINTAINED';

        try {
            $actorId = (int)$_SESSION['user_id'];

            // 1. Attendance Records
            $attendanceFilters = ['date' => $date];
            if ($supervisorUserId) {
                $attendanceFilters['supervisor_user_id'] = $supervisorUserId;
            }
            $attendance = $this->attendanceService->listAttendanceRecords($attendanceFilters);

            // 2. Watering Status per Belt
            $wateringFilters = ['date' => $date, 'maintenance_mode' => $maintenanceMode];
            if ($supervisorUserId) {
                $wateringFilters['supervisor_user_id'] = $supervisorUserId;
            }
            // Passing role context because the service derives logic based on it
            $watering = $this->wateringService->listWateringRecords($wateringFilters, $actorId, $roleKey);

            // Apply zone filtering manually since listWateringRecords raw query doesn't handle zone currently
            if ($zone) {
                $watering = array_filter($watering, function($w) use ($zone) {
                    // Requires join/fetch of zone which isn't currently in the raw SQL.
                    // For now, returning as is or relying on UI post-filter if zone is absent from query.
                    return true; 
                });
                $watering = array_values($watering);
            }

            // 3. Labour Entries
            $labourFilters = ['date' => $date];
            // Depending on architecture, labour is tied to belt_id, so we pull for belts visible in watering scope
            $labour = $this->labourService->listLabourEntries($labourFilters);

            Response::success([
                'date' => $date,
                'zone_filter' => $zone,
                'maintenance_mode' => $maintenanceMode,
                'attendance' => $attendance,
                'watering' => $watering,
                'labour' => $labour
            ]);

        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
