<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../services/ShiftAttendanceService.php';

class ShiftAttendanceController extends BaseController
{
    private ShiftAttendanceService $service;

    public function __construct()
    {
        $this->service = new ShiftAttendanceService();
    }

    /**
     * GET attendance/my-shift
     * Returns today's shift status, assigned belts, activity types.
     */
    public function myShift(): void
    {
        if (!$this->requireMethod('GET')) return;
        $actor = $this->getActor();

        try {
            $result = $this->service->getMyShift($actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/start-shift
     * Start shift with selfie, belt, GPS, optional meter.
     */
    public function startShift(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->startShift($input, $_FILES, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * POST attendance/complete-shift
     * Complete shift with selfie, activities, notes, optional meter.
     */
    public function completeShift(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->completeShift($input, $_FILES, $actor['user_id'], $actor['role_key']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET attendance/review-list
     * OPS: list shifts for a month.
     */
    public function reviewList(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $params = [
                'month' => $_GET['month'] ?? date('Y-m'),
                'role_key' => $_GET['role_key'] ?? '',
            ];
            $result = $this->service->getReviewList($params);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET attendance/review-detail
     * Full shift detail with photos and activities.
     */
    public function reviewDetail(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $shiftId = (int) ($_GET['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                throw new InvalidArgumentException('shift_id is required.');
            }
            $result = $this->service->getReviewDetail($shiftId);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/override
     * OPS override on a shift.
     */
    public function override(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $shiftId = (int) ($input['shift_id'] ?? 0);
            if ($shiftId <= 0) {
                throw new InvalidArgumentException('shift_id is required.');
            }
            $result = $this->service->overrideShift($shiftId, $input, $actor['user_id']);
            Response::success($result);
        } catch (DomainException $e) {
            Response::error($e->getMessage(), 403);
        } catch (InvalidArgumentException $e) {
            Response::error($e->getMessage(), 400);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 500);
        }
    }

    /**
     * GET attendance/activity-types
     * List all activity types (active + inactive).
     */
    public function activityTypes(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $result = $this->service->getActivityTypes();
            Response::success(['items' => $result]);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * POST attendance/activity-type-save
     * Create or update an activity type.
     */
    public function activityTypeSave(): void
    {
        if (!$this->requireMethod('POST')) return;
        $actor = $this->getActor();
        $input = $this->getInput();

        try {
            $result = $this->service->saveActivityType($input, $actor['user_id']);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }

    /**
     * GET attendance/monthly-summary
     * Aggregated monthly data.
     */
    public function monthlySummary(): void
    {
        if (!$this->requireMethod('GET')) return;

        try {
            $params = [
                'month' => $_GET['month'] ?? date('Y-m'),
                'group_by' => $_GET['group_by'] ?? 'user',
                'role_key' => $_GET['role_key'] ?? '',
            ];
            $result = $this->service->getMonthlySummary($params);
            Response::success($result);
        } catch (\Throwable $e) {
            Response::error($e->getMessage(), 400);
        }
    }
}
