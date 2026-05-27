<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../services/BoardIssueService.php';

/**
 * BoardIssueController
 *
 * HTTP shape for electrician board issue resolution.
 */
class BoardIssueController extends BaseController
{
    private BoardIssueService $service;

    public function __construct()
    {
        $this->service = new BoardIssueService();
    }

    /**
     * GET boardissue/list
     */
    public function listIssues(): void
    {
        if (!$this->requireMethod('GET')) return;

        $actor = $this->getActor();

        try {
            $items = $this->service->getIssueList($actor['user_id']);
            Response::success(['items' => $items]);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET boardissue/detail
     */
    public function getDetail(): void
    {
        if (!$this->requireMethod('GET')) return;

        if (empty($_GET['issue_id'])) {
            Response::error('Missing issue_id', 400);
            return;
        }

        $actor = $this->getActor();

        try {
            $result = $this->service->getIssueDetail(
                (int) $_GET['issue_id'],
                $actor['user_id']
            );
            Response::success($result);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST boardissue/start
     */
    public function startFix(): void
    {
        if (!$this->requireMethod('POST')) return;

        $input = $this->getInput();

        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id', 400);
            return;
        }

        $actor = $this->getActor();

        try {
            $result = $this->service->startFix(
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
     * POST boardissue/resolve
     */
    public function resolve(): void
    {
        if (!$this->requireMethod('POST')) return;

        $actor = $this->getActor();
        $input = $this->getInput();

        if (empty($input['issue_id'])) {
            Response::error('Missing issue_id', 400);
            return;
        }

        try {
            $result = $this->service->resolveIssue(
                (int) $input['issue_id'],
                $input,
                $_FILES,
                $actor['user_id'],
                $actor['role_key']
            );
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }
}
