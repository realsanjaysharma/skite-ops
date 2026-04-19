<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/IssueService.php';

class IssueController
{
    private IssueService $issueService;

    public function __construct()
    {
        $this->issueService = new IssueService();
    }

    /**
     * GET issue/list
     */
    public function listIssues(): void
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
                'status' => $_GET['status'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'belt_id' => $_GET['belt_id'] ?? null,
                'site_id' => $_GET['site_id'] ?? null,
            ];

            $items = $this->issueService->listIssues($filters, $actorRoleKey);

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
     * GET issue/get
     */
    public function getIssue(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            Response::error('Method not allowed', 405);
            return;
        }

        if (empty($_GET['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        try {
            $actorRoleKey = $_SESSION['role_key'] ?? '';
            
            if (!in_array($actorRoleKey, ['OPS_MANAGER', 'HEAD_SUPERVISOR'])) {
                Response::error('Forbidden', 403);
                return;
            }

            $issue = $this->issueService->getIssue((int) $_GET['issue_id'], $actorRoleKey);
            
            if (!$issue) {
                Response::error('Issue not found', 404);
                return;
            }

            Response::success($issue);
            
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST issue/create
     */
    public function createIssue(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $actorUserId = (int) $_SESSION['user_id'];
        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->issueService->createIssue($input, $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST issue/in-progress
     */
    public function markInProgress(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->issueService->markInProgress((int) $input['issue_id'], $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST issue/close
     */
    public function closeIssue(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        $actorRoleKey = $_SESSION['role_key'] ?? '';
        $actorUserId = (int) $_SESSION['user_id'];

        try {
            $result = $this->issueService->closeIssue((int) $input['issue_id'], $actorUserId, $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
    
    /**
     * POST issue/link-task
     */
    public function linkTask(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Response::error('Method not allowed', 405);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        
        if (empty($input['issue_id']) || empty($input['task_id'])) {
            Response::error('Missing issue_id or task_id', 400);
            return;
        }

        $actorRoleKey = $_SESSION['role_key'] ?? '';

        try {
            $result = $this->issueService->linkTask((int) $input['issue_id'], (int) $input['task_id'], $actorRoleKey);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
