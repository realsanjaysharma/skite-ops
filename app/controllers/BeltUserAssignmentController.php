<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/BeltUserAssignmentService.php';

/**
 * BeltUserAssignmentController
 *
 * HTTP shape for generic belt-user assignment CRUD.
 * Only OPS_MANAGER can create/close.
 */
class BeltUserAssignmentController extends BaseController
{
    private BeltUserAssignmentService $service;

    public function __construct()
    {
        $this->service = new BeltUserAssignmentService();
    }

    /**
     * GET beltassignment/list
     */
    public function listAssignments(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $filters = [
                'assignment_type' => $_GET['assignment_type'] ?? null,
                'belt_id'         => $_GET['belt_id'] ?? null,
                'user_id'         => $_GET['user_id'] ?? null,
                'active_only'     => isset($_GET['active_only']) ? (bool) $_GET['active_only'] : true,
            ];

            $items = $this->service->listAssignments($filters);
            Response::success(['items' => $items]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST beltassignment/create
     */
    public function createAssignment(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->createAssignment($input, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST beltassignment/close
     */
    public function closeAssignment(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['assignment_id'])) {
            Response::error('Missing assignment_id', 400);
            return;
        }

        $actor = $this->getActor();

        try {
            $result = $this->service->closeAssignment(
                (int) $input['assignment_id'],
                $actor['user_id'],
                $actor['role_key']
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
