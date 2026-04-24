<?php

require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/IssueService.php';

/**
 * IssueController
 *
 * Architecture: HTTP shape only. Role enforcement is in AuthMiddleware.
 * Scope constraints (belt-only for Head Supervisor, Ops-only close) live in IssueService.
 */
class IssueController extends BaseController
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
        if (!$this->requireMethod('GET')) return;

        try {
            $filters = [
                'status'   => $_GET['status'] ?? null,
                'priority' => $_GET['priority'] ?? null,
                'belt_id'  => $_GET['belt_id'] ?? null,
                'site_id'  => $_GET['site_id'] ?? null,
            ];

            $actor = $this->getActor();
            $items = $this->issueService->listIssues($filters, $actor['role_key']);

            Response::success([
                'items'      => $items,
                'pagination' => ['page' => 1, 'limit' => count($items), 'total' => count($items)],
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
        if (!$this->requireMethod('GET')) return;

        if (empty($_GET['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        try {
            $actor = $this->getActor();
            $issue = $this->issueService->getIssue(
                (int) $_GET['issue_id'],
                $actor['role_key']
            );

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
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();
        $actor = $this->getActor();

        try {
            $result = $this->issueService->createIssue(
                $input,
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

    /**
     * POST issue/in-progress
     */
    public function markInProgress(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        try {
            $actor = $this->getActor();
            $result = $this->issueService->markInProgress(
                (int) $input['issue_id'],
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

    /**
     * POST issue/close
     */
    public function closeIssue(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id param', 400);
            return;
        }

        try {
            $actor = $this->getActor();
            $result = $this->issueService->closeIssue(
                (int) $input['issue_id'],
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

    /**
     * POST issue/link-task
     */
    public function linkTask(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['issue_id']) || empty($input['task_id'])) {
            Response::error('Missing issue_id or task_id', 400);
            return;
        }

        try {
            $actor = $this->getActor();
            $result = $this->issueService->linkTask(
                (int) $input['issue_id'],
                (int) $input['task_id'],
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
