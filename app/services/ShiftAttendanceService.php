<?php

require_once __DIR__ . '/../repositories/ShiftAttendanceRepository.php';
require_once __DIR__ . '/../repositories/AttendanceActivityRepository.php';
require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/UploadStorageService.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/SystemSettingsService.php';

class ShiftAttendanceService
{
    private ShiftAttendanceRepository $shiftRepo;
    private AttendanceActivityRepository $activityRepo;
    private BeltAssignmentRepository $beltAssignmentRepo;
    private UploadRepository $uploadRepo;
    private UploadStorageService $storageService;
    private AuditService $auditService;
    private SystemSettingsService $settingsService;

    public function __construct()
    {
        $this->shiftRepo = new ShiftAttendanceRepository();
        $this->activityRepo = new AttendanceActivityRepository();
        $this->beltAssignmentRepo = new BeltAssignmentRepository();
        $this->uploadRepo = new UploadRepository();
        $this->storageService = new UploadStorageService();
        $this->auditService = new AuditService();
        $this->settingsService = new SystemSettingsService();
    }

    // ─── My Shift (GET) ───────────────────────────────────────────

    /**
     * Get today's shift status, assigned belts (for GBS), and activity types.
     */
    public function getMyShift(int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        $belts = [];
        if ($roleKey === 'GREEN_BELT_SUPERVISOR') {
            $allAssignments = $this->beltAssignmentRepo->findByUserId('supervisor', $userId);
            $belts = array_values(array_filter($allAssignments, function ($a) {
                return $a['end_date'] === null;
            }));
        }

        $activityTypes = $this->activityRepo->getActivityTypes(true);

        $activities = [];
        $labourPhotos = [];
        if ($shift) {
            $activities = $this->activityRepo->getActivitiesByShift((int) $shift['id']);
            $labourPhotos = $this->shiftRepo->getLabourPhotos((int) $shift['id']);
        }

        return [
            'shift' => $shift,
            'belts' => $belts,
            'activity_types' => $activityTypes,
            'activities' => $activities,
            'labour_photos' => $labourPhotos,
            'settings' => [
                'shift_start_time' => $this->getSetting('attendance_shift_start_time', '09:00'),
                'shift_end_time' => $this->getSetting('attendance_shift_end_time', '17:00'),
                'late_grace_minutes' => (int) $this->getSetting('attendance_late_grace_minutes', '15'),
                'early_grace_minutes' => (int) $this->getSetting('attendance_early_grace_minutes', '10'),
            ],
        ];
    }

    // ─── Start Shift ──────────────────────────────────────────────

    /**
     * Start a shift for today. Handles selfie upload, GPS, belt validation, meter.
     */
    public function startShift(array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');

        // Check for existing shift
        $existing = $this->shiftRepo->findByUserAndDate($userId, $today);
        if ($existing) {
            return $existing; // already started — return existing
        }

        $beltId = null;
        $startDistanceKm = null;
        $startLocationFlag = false;

        // Belt validation for GREEN_BELT_SUPERVISOR
        if ($roleKey === 'GREEN_BELT_SUPERVISOR') {
            if (empty($data['belt_id'])) {
                throw new InvalidArgumentException('Belt selection is required.');
            }
            $beltId = (int) $data['belt_id'];

            // Verify belt assignment
            $assignments = $this->beltAssignmentRepo->findByUserId('supervisor', $userId);
            $activeAssignments = array_filter($assignments, function ($a) {
                return $a['end_date'] === null;
            });
            $activeBeltIds = array_column($activeAssignments, 'belt_id');

            if (!in_array($beltId, array_map('intval', $activeBeltIds), true)) {
                throw new DomainException('You are not assigned to this belt.');
            }
        }

        // Store selfie upload
        $selfieUploadId = $this->storeAttendancePhoto($rawFiles, 'files', $userId);

        // GPS distance calculation
        $lat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $lng = isset($data['longitude']) ? (float) $data['longitude'] : null;

        if ($beltId !== null && $lat !== null && $lng !== null) {
            $beltGps = $this->getBeltGps($beltId);
            if ($beltGps && $beltGps['latitude'] && $beltGps['longitude']) {
                $startDistanceKm = $this->haversineKm(
                    $lat, $lng,
                    (float) $beltGps['latitude'], (float) $beltGps['longitude']
                );
                $threshold = (float) $this->getSetting('attendance_location_threshold_km', '3');
                $startLocationFlag = ($startDistanceKm > $threshold);
            }
        }

        // Late start flag
        $isLateStart = $this->isLateStart();

        // Vehicle / meter
        $hasVehicle = !empty($data['has_vehicle']);
        $startMeterReading = null;
        $startMeterUploadId = null;

        if ($hasVehicle) {
            if (!isset($data['start_meter_reading']) || $data['start_meter_reading'] === '') {
                throw new InvalidArgumentException('Start meter reading is required when vehicle is used.');
            }
            $startMeterReading = (float) $data['start_meter_reading'];
            $startMeterUploadId = $this->storeAttendancePhoto($rawFiles, 'meter_photo', $userId);
        }

        // Create shift row
        $shiftId = $this->shiftRepo->create([
            'user_id' => $userId,
            'role_key' => $roleKey,
            'shift_date' => $today,
            'belt_id' => $beltId,
            'start_upload_id' => $selfieUploadId,
            'start_latitude' => $lat,
            'start_longitude' => $lng,
            'start_distance_km' => $startDistanceKm,
            'start_location_flag' => $startLocationFlag,
            'has_vehicle' => $hasVehicle,
            'start_meter_reading' => $startMeterReading,
            'start_meter_upload_id' => $startMeterUploadId,
            'is_late_start' => $isLateStart,
        ]);

        // Update parent_id on the upload rows to point to this shift
        if ($selfieUploadId) {
            $this->uploadRepo->updateParentId($selfieUploadId, $shiftId);
        }
        if ($startMeterUploadId) {
            $this->uploadRepo->updateParentId($startMeterUploadId, $shiftId);
        }

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    // ─── Complete Shift ───────────────────────────────────────────

    /**
     * Complete today's shift. Handles selfie, activities, notes, meter.
     */
    public function completeShift(array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        if (!$shift) {
            throw new DomainException('No active shift found for today. Start your shift first.');
        }

        if ($shift['completed_at'] !== null) {
            throw new DomainException('Shift is already completed.');
        }

        $shiftId = (int) $shift['id'];

        // Parse activities
        $activities = json_decode($data['activities'] ?? '[]', true);
        if (!is_array($activities) || count($activities) === 0) {
            throw new InvalidArgumentException('At least one activity is required.');
        }

        // Validate activity keys exist
        $validKeys = array_column($this->activityRepo->getActivityTypes(true), 'activity_key');
        foreach ($activities as $act) {
            if (!in_array($act['activity_key'] ?? '', $validKeys, true)) {
                throw new InvalidArgumentException("Invalid activity: " . ($act['activity_key'] ?? ''));
            }
            // Validate belt_id for GBS
            if ($roleKey === 'GREEN_BELT_SUPERVISOR' && empty($act['belt_id'])) {
                throw new InvalidArgumentException('Belt is required for each activity.');
            }
        }

        // Store end selfie
        $endUploadId = $this->storeAttendancePhoto($rawFiles, 'files', $userId);

        // GPS
        $endLat = isset($data['latitude']) ? (float) $data['latitude'] : null;
        $endLng = isset($data['longitude']) ? (float) $data['longitude'] : null;
        $endDistanceKm = null;
        $endLocationFlag = false;

        $beltId = $shift['belt_id'] ? (int) $shift['belt_id'] : null;
        if ($beltId !== null && $endLat !== null && $endLng !== null) {
            $beltGps = $this->getBeltGps($beltId);
            if ($beltGps && $beltGps['latitude'] && $beltGps['longitude']) {
                $endDistanceKm = $this->haversineKm(
                    $endLat, $endLng,
                    (float) $beltGps['latitude'], (float) $beltGps['longitude']
                );
                $threshold = (float) $this->getSetting('attendance_location_threshold_km', '3');
                $endLocationFlag = ($endDistanceKm > $threshold);
            }
        }

        // Vehicle / meter
        $endMeterReading = null;
        $endMeterUploadId = null;
        if ((int) $shift['has_vehicle'] === 1) {
            if (!isset($data['end_meter_reading']) || $data['end_meter_reading'] === '') {
                throw new InvalidArgumentException('End meter reading is required.');
            }
            $endMeterReading = (float) $data['end_meter_reading'];
            if ($endMeterReading < (float) $shift['start_meter_reading']) {
                throw new InvalidArgumentException('End meter reading must be >= start reading.');
            }
            $endMeterUploadId = $this->storeAttendancePhoto($rawFiles, 'meter_photo_end', $userId);
        }

        // Early end flag
        $isEarlyEnd = $this->isEarlyEnd();
        $shiftNotes = trim($data['shift_notes'] ?? '');
        if (strlen($shiftNotes) > 500) {
            $shiftNotes = substr($shiftNotes, 0, 500);
        }

        // Transaction: update shift + insert activities
        $this->shiftRepo->beginTransaction();
        try {
            $this->shiftRepo->completeShift($shiftId, [
                'end_upload_id' => $endUploadId,
                'end_latitude' => $endLat,
                'end_longitude' => $endLng,
                'end_distance_km' => $endDistanceKm,
                'end_location_flag' => $endLocationFlag,
                'end_meter_reading' => $endMeterReading,
                'end_meter_upload_id' => $endMeterUploadId,
                'is_early_end' => $isEarlyEnd,
                'shift_notes' => $shiftNotes ?: null,
            ]);

            $this->activityRepo->insertShiftActivities($shiftId, $activities);

            $this->shiftRepo->commit();
        } catch (\Throwable $e) {
            $this->shiftRepo->rollback();
            throw $e;
        }

        // Update parent_id on upload rows
        if ($endUploadId) {
            $this->uploadRepo->updateParentId($endUploadId, $shiftId);
        }
        if ($endMeterUploadId) {
            $this->uploadRepo->updateParentId($endMeterUploadId, $shiftId);
        }

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    // ─── OPS Review ───────────────────────────────────────────────

    /**
     * List shifts for a month (calendar + list data).
     */
    public function getReviewList(array $params): array
    {
        $month = $params['month'] ?? date('Y-m');
        $roleFilter = !empty($params['role_key']) ? $params['role_key'] : null;

        $shifts = $this->shiftRepo->getMonthlyShifts($month, $roleFilter);
        $eligibleUsers = $this->shiftRepo->getShiftEligibleUsers($roleFilter);

        return [
            'shifts' => $shifts,
            'eligible_users' => $eligibleUsers,
            'month' => $month,
        ];
    }

    /**
     * Get full shift detail including photos and activities.
     */
    public function getReviewDetail(int $shiftId): array
    {
        $shift = $this->shiftRepo->findById($shiftId);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $activities = $this->activityRepo->getActivitiesByShift($shiftId);

        return [
            'shift' => $shift,
            'activities' => $activities,
        ];
    }

    /**
     * OPS override on a shift.
     */
    public function overrideShift(int $shiftId, array $data, int $actorId): array
    {
        $status = $data['override_status'] ?? '';
        if (!in_array($status, ['PRESENT', 'ABSENT', 'HALF_DAY'], true)) {
            throw new InvalidArgumentException('Invalid override status.');
        }

        $reason = trim($data['override_reason'] ?? '');
        if ($reason === '') {
            throw new InvalidArgumentException('Override reason is required.');
        }

        $shift = $this->shiftRepo->findById($shiftId);
        if (!$shift) {
            throw new InvalidArgumentException('Shift not found.');
        }

        $oldValues = [
            'override_status' => $shift['override_status'],
            'override_reason' => $shift['override_reason'],
        ];

        $this->shiftRepo->beginTransaction();
        try {
            $this->shiftRepo->setOverride($shiftId, $status, $actorId, $reason);

            $this->auditService->logAction(
                $actorId,
                'SHIFT_ATTENDANCE_OVERRIDE',
                'shift_attendance',
                $shiftId,
                $oldValues,
                ['override_status' => $status, 'override_reason' => $reason],
                $reason
            );

            $this->shiftRepo->commit();
        } catch (\Throwable $e) {
            $this->shiftRepo->rollback();
            throw $e;
        }

        return $this->shiftRepo->findById($shiftId);
    }

    // ─── Monthly Summary ──────────────────────────────────────────

    /**
     * Aggregated monthly data per supervisor or per belt.
     */
    public function getMonthlySummary(array $params): array
    {
        $month = $params['month'] ?? date('Y-m');
        $groupBy = $params['group_by'] ?? 'user'; // 'user' or 'belt'
        $roleFilter = !empty($params['role_key']) ? $params['role_key'] : null;

        if ($groupBy === 'belt') {
            return [
                'group_by' => 'belt',
                'month' => $month,
                'items' => $this->shiftRepo->getMonthlySummaryByBelt($month),
            ];
        }

        return [
            'group_by' => 'user',
            'month' => $month,
            'items' => $this->shiftRepo->getMonthlySummaryByUser($month, $roleFilter),
        ];
    }

    /**
     * Save labour count + male/female + photos on an active shift.
     */
    public function saveLabourCount(array $data, array $rawFiles, int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $shift = $this->shiftRepo->findByUserAndDate($userId, $today);

        if (!$shift) {
            throw new DomainException('No active shift found for today.');
        }
        if ($shift['completed_at'] !== null) {
            throw new DomainException('Shift is already completed.');
        }

        $shiftId = (int) $shift['id'];
        $labourCount = (int) ($data['labour_count'] ?? 0);
        $maleCount = isset($data['male_count']) ? (int) $data['male_count'] : null;
        $femaleCount = isset($data['female_count']) ? (int) $data['female_count'] : null;
        $varianceNotes = trim($data['labour_variance_notes'] ?? '');

        // Validate labour count is non-negative
        if ($labourCount < 0) {
            throw new InvalidArgumentException('Labour count must be non-negative.');
        }

        // Validate male/female sum
        if ($labourCount > 0) {
            if ($maleCount === null || $femaleCount === null) {
                throw new InvalidArgumentException('Male and female counts are required when labour count > 0.');
            }
            if ($maleCount < 0 || $femaleCount < 0) {
                throw new InvalidArgumentException('Male and female counts must be non-negative.');
            }
            if (($maleCount + $femaleCount) !== $labourCount) {
                throw new InvalidArgumentException('Male + Female must equal total labour count.');
            }
        } else {
            $maleCount = 0;
            $femaleCount = 0;
        }

        // For HS: check variance
        if ($roleKey === 'HEAD_SUPERVISOR') {
            $gbsSummary = $this->shiftRepo->getGbsLabourSummary($today);
            $gbsTotal = 0;
            foreach ($gbsSummary as $row) {
                $gbsTotal += (int) ($row['labour_count'] ?? 0);
            }

            $variance = $labourCount - $gbsTotal;
            if ($variance !== 0 && $varianceNotes === '') {
                throw new InvalidArgumentException(
                    "Labour count differs from supervisor total by {$variance}. Explanation is required."
                );
            }
        }

        // Save to shift row
        $this->shiftRepo->updateLabourCount($shiftId, [
            'labour_count' => $labourCount,
            'male_count' => $maleCount,
            'female_count' => $femaleCount,
            'labour_variance_notes' => $varianceNotes ?: null,
        ]);

        // Store labour proof photos
        if (!empty($rawFiles['files']) && !empty($rawFiles['files']['tmp_name'])) {
            $normalized = $this->storageService->normalizeFiles($rawFiles['files']);
            $validated = $this->storageService->validateFiles($normalized);

            foreach ($validated as $file) {
                $stored = $this->storageService->storeValidatedFile($file, 'SHIFT_ATTENDANCE', $shiftId);

                $this->uploadRepo->create([
                    'parent_type' => 'SHIFT_ATTENDANCE',
                    'parent_id' => $shiftId,
                    'upload_type' => 'WORK',
                    'work_type' => 'LABOUR_PROOF',
                    'is_discovery_mode' => 0,
                    'file_path' => $stored['file_path'],
                    'original_file_name' => $stored['original_file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size_bytes' => $stored['file_size_bytes'],
                    'photo_label' => 'GENERAL',
                    'site_condition' => null,
                    'comment_text' => null,
                    'gps_latitude' => null,
                    'gps_longitude' => null,
                    'authority_visibility' => 'NOT_ELIGIBLE',
                    'created_by_user_id' => $userId,
                ]);
            }
        }

        return $this->shiftRepo->findByUserAndDate($userId, $today);
    }

    /**
     * Get labour summary for HS view: today's GBS labour counts.
     */
    public function getLabourSummary(int $userId, string $roleKey): array
    {
        $today = date('Y-m-d');
        $rows = $this->shiftRepo->getGbsLabourSummary($today);

        $sumLabour = 0;
        $sumMale = 0;
        $sumFemale = 0;

        foreach ($rows as &$row) {
            $sumLabour += (int) ($row['labour_count'] ?? 0);
            $sumMale += (int) ($row['male_count'] ?? 0);
            $sumFemale += (int) ($row['female_count'] ?? 0);
        }
        unset($row);

        return [
            'items' => $rows,
            'totals' => [
                'sum_labour' => $sumLabour,
                'sum_male' => $sumMale,
                'sum_female' => $sumFemale,
            ],
        ];
    }

    // ─── Activity Types Management ────────────────────────────────

    public function getActivityTypes(): array
    {
        return $this->activityRepo->getActivityTypes(false);
    }

    public function saveActivityType(array $data, int $actorId): array
    {
        if (empty($data['label'])) {
            throw new InvalidArgumentException('Activity label is required.');
        }

        if (empty($data['id']) && empty($data['activity_key'])) {
            // Auto-generate key from label
            $data['activity_key'] = strtoupper(preg_replace('/[^A-Z0-9]+/', '_', strtoupper(trim($data['label']))));
            $data['activity_key'] = trim($data['activity_key'], '_');
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['is_active'] = isset($data['is_active']) ? (bool) $data['is_active'] : true;

        $id = $this->activityRepo->saveActivityType($data);

        return $this->activityRepo->findActivityTypeByKey($data['activity_key'] ?? '') ?: ['id' => $id];
    }

    // ─── Private Helpers ──────────────────────────────────────────

    /**
     * Store a single attendance photo. Returns the upload ID.
     * Uses UploadStorageService for file handling + direct UploadRepository insert.
     */
    private function storeAttendancePhoto(array $rawFiles, string $fieldName, int $userId): ?int
    {
        if (empty($rawFiles[$fieldName]) || empty($rawFiles[$fieldName]['tmp_name'])) {
            return null;
        }

        $fileData = $rawFiles[$fieldName];
        // Normalize single file to array format expected by storageService
        $normalized = $this->storageService->normalizeFiles($fileData);
        $validated = $this->storageService->validateFiles($normalized);

        if (empty($validated)) {
            return null;
        }

        $stored = $this->storageService->storeValidatedFile(
            $validated[0],
            'SHIFT_ATTENDANCE',
            0 // temporary parent_id — updated after shift row is created
        );

        $uploadId = $this->uploadRepo->create([
            'parent_type' => 'SHIFT_ATTENDANCE',
            'parent_id' => 0, // updated after shift row is created
            'upload_type' => 'WORK',
            'work_type' => null,
            'is_discovery_mode' => 0,
            'file_path' => $stored['file_path'],
            'original_file_name' => $stored['original_file_name'],
            'mime_type' => $stored['mime_type'],
            'file_size_bytes' => $stored['file_size_bytes'],
            'photo_label' => 'GENERAL',
            'site_condition' => null,
            'comment_text' => null,
            'gps_latitude' => null,
            'gps_longitude' => null,
            'authority_visibility' => 'NOT_ELIGIBLE',
            'created_by_user_id' => $userId,
        ]);

        return $uploadId;
    }

    /**
     * Get green belt GPS coordinates.
     */
    private function getBeltGps(int $beltId): ?array
    {
        return $this->shiftRepo->getBeltGps($beltId);
    }

    /**
     * Haversine distance in km between two GPS points.
     */
    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadiusKm * $c, 2);
    }

    /**
     * Check if current time is past the late-start grace window.
     */
    private function isLateStart(): bool
    {
        $startTime = $this->getSetting('attendance_shift_start_time', '09:00');
        $graceMinutes = (int) $this->getSetting('attendance_late_grace_minutes', '15');

        $deadline = strtotime("today {$startTime}") + ($graceMinutes * 60);
        return time() > $deadline;
    }

    /**
     * Check if current time is before the early-end grace window.
     */
    private function isEarlyEnd(): bool
    {
        $endTime = $this->getSetting('attendance_shift_end_time', '17:00');
        $graceMinutes = (int) $this->getSetting('attendance_early_grace_minutes', '10');

        $cutoff = strtotime("today {$endTime}") - ($graceMinutes * 60);
        return time() < $cutoff;
    }

    /**
     * Read a system setting with a fallback default.
     */
    private function getSetting(string $key, string $default): string
    {
        try {
            $settings = $this->settingsService->listSettings();
            foreach ($settings as $s) {
                if (($s['setting_key'] ?? '') === $key) {
                    return (string) $s['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // fall through to default
        }
        return $default;
    }
}
