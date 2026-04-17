<?php

/**
 * BeltAssignmentController
 *
 * Purpose:
 * Handles belt assignment HTTP requests for all 3 assignment types:
 * - Supervisor assignments
 * - Authority assignments
 * - Outsourced maintainer assignments
 *
 * Architecture Rule:
 * Controller -> Service -> Repository -> Database
 *
 * IMPORTANT RULES:
 * - Controller handles request/response only
 * - No business logic is allowed here
 * - No SQL is allowed here
 */

require_once __DIR__ . '/../services/BeltAssignmentService.php';
require_once __DIR__ . '/../helpers/Response.php';

class BeltAssignmentController
{
    /**
     * @var BeltAssignmentService
     */
    private $assignmentService;

    public function __construct()
    {
        $this->assignmentService = new BeltAssignmentService();
    }

    // =========================================
    // SUPERVISOR ASSIGNMENTS
    // =========================================

    /**
     * GET supervisorassignment/list
     */
    public function listSupervisorAssignments(): void
    {
        $this->handleList('supervisor', [
            'belt_id'             => $_GET['belt_id'] ?? null,
            'supervisor_user_id'  => $_GET['supervisor_user_id'] ?? null,
        ]);
    }

    /**
     * POST supervisorassignment/create
     */
    public function createSupervisorAssignment(): void
    {
        $this->handleCreate('supervisor', 'supervisor_user_id');
    }

    /**
     * POST supervisorassignment/close
     */
    public function closeSupervisorAssignment(): void
    {
        $this->handleClose('supervisor');
    }

    // =========================================
    // AUTHORITY ASSIGNMENTS
    // =========================================

    /**
     * GET authorityassignment/list
     */
    public function listAuthorityAssignments(): void
    {
        $this->handleList('authority', [
            'belt_id'            => $_GET['belt_id'] ?? null,
            'authority_user_id'  => $_GET['authority_user_id'] ?? null,
        ]);
    }

    /**
     * POST authorityassignment/create
     */
    public function createAuthorityAssignment(): void
    {
        $this->handleCreate('authority', 'authority_user_id');
    }

    /**
     * POST authorityassignment/close
     */
    public function closeAuthorityAssignment(): void
    {
        $this->handleClose('authority');
    }

    // =========================================
    // OUTSOURCED ASSIGNMENTS
    // =========================================

    /**
     * GET outsourcedassignment/list
     */
    public function listOutsourcedAssignments(): void
    {
        $this->handleList('outsourced', [
            'belt_id'              => $_GET['belt_id'] ?? null,
            'outsourced_user_id'   => $_GET['outsourced_user_id'] ?? null,
            'active_only'          => $_GET['active_only'] ?? null,
        ]);
    }

    /**
     * POST outsourcedassignment/create
     */
    public function createOutsourcedAssignment(): void
    {
        $this->handleCreate('outsourced', 'outsourced_user_id');
    }

    /**
     * POST outsourcedassignment/close
     */
    public function closeOutsourcedAssignment(): void
    {
        $this->handleClose('outsourced');
    }

    // =========================================
    // SHARED HANDLERS
    // =========================================

    /**
     * Handle list request for any assignment type.
     */
    private function handleList(string $type, array $filters): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        try {
            $result = $this->assignmentService->listAssignments($type, $filters);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Handle create request for any assignment type.
     *
     * The $userIdField maps the route-specific param name (e.g. 'supervisor_user_id')
     * to the generic 'user_id' expected by the service.
     */
    private function handleCreate(string $type, string $userIdField): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = $_SESSION['user_id'] ?? null;

        if (!$actorUserId) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            // Map type-specific user_id field to generic 'user_id'
            $serviceData = [
                'belt_id'    => $data['belt_id'] ?? null,
                'user_id'    => $data[$userIdField] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date'   => $data['end_date'] ?? null,
            ];

            $result = $this->assignmentService->createAssignment($type, $serviceData, (int) $actorUserId);

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Handle close request for any assignment type.
     */
    private function handleClose(string $type): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $actorUserId = $_SESSION['user_id'] ?? null;

        if (!$actorUserId) {
            Response::error('Unauthorized', 401);
            return;
        }

        try {
            $data = $this->getRequestData();

            if (empty($data['assignment_id']) || !is_numeric($data['assignment_id'])) {
                Response::error('Valid assignment_id is required.', 400);
                return;
            }

            if (empty($data['end_date'])) {
                Response::error('end_date is required.', 400);
                return;
            }

            $result = $this->assignmentService->closeAssignment(
                $type,
                (int) $data['assignment_id'],
                $data['end_date'],
                (int) $actorUserId
            );

            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * Parse JSON or form-encoded request body.
     */
    private function getRequestData(): array
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            if (!is_array($data)) {
                throw new InvalidArgumentException('Invalid JSON payload.');
            }

            return $data;
        }

        return $_POST;
    }
}
