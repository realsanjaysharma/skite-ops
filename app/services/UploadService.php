<?php

/**
 * UploadService
 *
 * Purpose:
 * Shared upload foundation for validation, storage, metadata persistence,
 * discovery-side effects, list access, and self-delete rules.
 */

require_once __DIR__ . '/../repositories/UploadRepository.php';
require_once __DIR__ . '/AuditService.php';
require_once __DIR__ . '/UploadStorageService.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/SystemSettingsService.php';

class UploadService
{
    private const SURFACE_CONFIG = [
        'SUPERVISOR' => [
            'parent_type' => 'GREEN_BELT',
            'allowed_upload_types' => ['WORK', 'ISSUE'],
            'allow_discovery_mode' => false,
        ],
        'OUTSOURCED' => [
            'parent_type' => 'GREEN_BELT',
            'allowed_upload_types' => ['WORK', 'ISSUE'],
            'allow_discovery_mode' => false,
        ],
        'MONITORING' => [
            'parent_type' => 'SITE',
            'allowed_upload_types' => ['WORK'],
            'allow_discovery_mode' => true,
        ],
        'TASK' => [
            'parent_type' => 'TASK',
            'allowed_upload_types' => ['WORK'],
            'allow_discovery_mode' => false,
        ],
    ];

    /**
     * @var UploadRepository
     */
    private $uploadRepository;

    /**
     * @var UploadStorageService
     */
    private $storageService;

    /**
     * @var AuditService
     */
    private $auditService;

    /**
     * @var PDO
     */
    private $db;

    public function __construct()
    {
        $this->uploadRepository = new UploadRepository();
        $this->storageService = new UploadStorageService();
        $this->auditService = new AuditService();
        $this->db = Database::getConnection();
    }

    /**
     * Create one or more upload rows plus stored files for a specific surface.
     */
    public function createUploadsForSurface(string $surface, array $data, array $rawFiles, int $actorUserId): array
    {
        $surface = strtoupper(trim($surface));
        $surfaceConfig = self::SURFACE_CONFIG[$surface] ?? null;

        if ($surfaceConfig === null) {
            throw new InvalidArgumentException('Unsupported upload surface.');
        }

        $normalized = $this->normalizeCreateData($surface, $surfaceConfig, $data);

        if (!$this->uploadRepository->parentExists($normalized['parent_type'], $normalized['parent_id'])) {
            throw new InvalidArgumentException('Upload parent record not found.');
        }

        $this->verifyRecordScope($surface, $normalized['parent_id'], $actorUserId);

        $files = $this->storageService->normalizeFiles($rawFiles);
        $validatedFiles = $this->storageService->validateFiles($files);
        $created = [];
        $storedPaths = [];

        $this->uploadRepository->beginTransaction();

        try {
            foreach ($validatedFiles as $validatedFile) {
                $stored = $this->storageService->storeValidatedFile(
                    $validatedFile,
                    $normalized['parent_type'],
                    $normalized['parent_id']
                );
                $storedPaths[] = $stored['file_path'];

                $uploadId = $this->uploadRepository->create([
                    'parent_type' => $normalized['parent_type'],
                    'parent_id' => $normalized['parent_id'],
                    'upload_type' => $normalized['upload_type'],
                    'work_type' => $normalized['work_type'],
                    'is_discovery_mode' => $normalized['is_discovery_mode'] ? 1 : 0,
                    'file_path' => $stored['file_path'],
                    'original_file_name' => $stored['original_file_name'],
                    'mime_type' => $stored['mime_type'],
                    'file_size_bytes' => $stored['file_size_bytes'],
                    'photo_label' => $normalized['photo_label'],
                    'comment_text' => $normalized['comment_text'],
                    'gps_latitude' => $normalized['gps_latitude'],
                    'gps_longitude' => $normalized['gps_longitude'],
                    'authority_visibility' => $normalized['authority_visibility'],
                    'created_by_user_id' => $actorUserId,
                ]);

                $createdUpload = $this->uploadRepository->findById($uploadId);

                if ($createdUpload === null) {
                    throw new RuntimeException('Failed to reload created upload.');
                }

                $created[] = $createdUpload;

                $this->auditService->logAction(
                    $actorUserId,
                    'UPLOAD_CREATED',
                    'upload',
                    $uploadId,
                    null,
                    [
                        'surface' => $surface,
                        'parent_type' => $createdUpload['parent_type'],
                        'parent_id' => (int) $createdUpload['parent_id'],
                        'upload_type' => $createdUpload['upload_type'],
                        'work_type' => $createdUpload['work_type'],
                        'photo_label' => $createdUpload['photo_label'],
                        'authority_visibility' => $createdUpload['authority_visibility'],
                        'is_discovery_mode' => (int) $createdUpload['is_discovery_mode'],
                    ]
                );
            }

            if ($normalized['parent_type'] === 'SITE' && $normalized['is_discovery_mode'] && isset($created[0]['id'])) {
                $this->createOrRefreshDiscoveryRecord($normalized['parent_id'], (int) $created[0]['id']);
            }

            $this->uploadRepository->commit();

            return [
                'created_uploads' => array_map(
                    static function (array $upload): array {
                        return [
                            'id' => (int) $upload['id'],
                            'parent_type' => $upload['parent_type'],
                            'parent_id' => (int) $upload['parent_id'],
                            'upload_type' => $upload['upload_type'],
                            'work_type' => $upload['work_type'],
                            'is_discovery_mode' => (int) $upload['is_discovery_mode'],
                            'photo_label' => $upload['photo_label'],
                            'authority_visibility' => $upload['authority_visibility'],
                            'created_at' => $upload['created_at'],
                        ];
                    },
                    $created
                ),
            ];
        } catch (Throwable $exception) {
            if ($this->uploadRepository->inTransaction()) {
                $this->uploadRepository->rollback();
            }

            foreach ($storedPaths as $storedPath) {
                $this->storageService->deleteStoredRelativePath($storedPath);
            }

            throw $exception;
        }
    }

    /**
     * Generic filtered upload list for later controllers.
     */
    public function listUploads(array $filters = []): array
    {
        return $this->uploadRepository->findAll($this->normalizeListFilters($filters));
    }

    /**
     * Creator-scoped upload list with pagination for My Uploads pages.
     */
    public function listCreatorUploads(int $actorUserId, array $filters = [], int $page = 1, int $limit = DEFAULT_PAGE_LIMIT): array
    {
        $normalized = $this->normalizeListFilters($filters);
        $normalized['created_by_user_id'] = $actorUserId;

        $items = $this->uploadRepository->findAll($normalized, $page, $limit);
        $total = $this->uploadRepository->countAll($normalized);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    /**
     * Shared self-delete foundation for My Uploads style pages.
     */
    public function softDeleteUpload(int $uploadId, int $actorUserId): array
    {
        $upload = $this->uploadRepository->findById($uploadId);

        if ($upload === null) {
            throw new InvalidArgumentException('Upload not found.');
        }

        if ((int) $upload['created_by_user_id'] !== $actorUserId) {
            throw new RuntimeException('Forbidden');
        }

        if ((int) $upload['is_deleted'] === 1 || (int) $upload['is_purged'] === 1) {
            throw new InvalidArgumentException('Upload is already deleted or purged.');
        }

        if ($upload['upload_type'] === 'ISSUE') {
            throw new InvalidArgumentException('Issue uploads cannot be self-deleted in v1.');
        }

        $createdAt = new DateTimeImmutable($upload['created_at']);
        $windowEnd = $createdAt->modify('+' . UPLOAD_SELF_DELETE_WINDOW_MINUTES . ' minutes');

        if (new DateTimeImmutable() > $windowEnd) {
            throw new InvalidArgumentException('Upload is outside the self-delete window.');
        }

        $this->uploadRepository->beginTransaction();

        try {
            $this->uploadRepository->softDelete($uploadId, $actorUserId);

            $this->auditService->logAction(
                $actorUserId,
                'UPLOAD_SOFT_DELETED',
                'upload',
                $uploadId,
                [
                    'is_deleted' => (int) $upload['is_deleted'],
                    'deleted_at' => $upload['deleted_at'],
                    'deleted_by_user_id' => $upload['deleted_by_user_id'],
                ],
                [
                    'is_deleted' => 1,
                    'deleted_by_user_id' => $actorUserId,
                ]
            );

            $this->uploadRepository->commit();

            $updated = $this->uploadRepository->findById($uploadId);

            if ($updated === null) {
                throw new RuntimeException('Failed to reload deleted upload.');
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($this->uploadRepository->inTransaction()) {
                $this->uploadRepository->rollback();
            }

            throw $exception;
        }
    }

    /**
     * Review uploads (Approve, Reject, Hidden).
     */
    public function reviewUploads(array $uploadIds, string $decision, int $actorUserId, ?string $comment = null): void
    {
        $uploadIds = array_values(array_unique(array_filter(array_map('intval', $uploadIds), static fn ($id) => $id > 0)));

        if (empty($uploadIds)) {
            throw new InvalidArgumentException('No uploads provided for review.');
        }

        $validDecisions = ['APPROVED', 'REJECTED', 'HIDDEN'];
        if (!in_array($decision, $validDecisions, true)) {
            throw new InvalidArgumentException('Invalid review decision.');
        }

        // Must be Ops role to review
        if (!isset($_SESSION['role_key']) || $_SESSION['role_key'] !== 'OPS_MANAGER') {
            throw new DomainException('Only Ops can review uploads.');
        }

        $this->uploadRepository->beginTransaction();

        try {
            // Store old state for audit
            $oldUploads = $this->uploadRepository->getUploadsForReview($uploadIds);

            if (count($oldUploads) !== count($uploadIds)) {
                throw new InvalidArgumentException('One or more uploads are not available for review.');
            }

            foreach ($oldUploads as $oldUpload) {
                if (($oldUpload['upload_type'] ?? '') !== 'WORK' || ($oldUpload['authority_visibility'] ?? '') === 'NOT_ELIGIBLE') {
                    throw new InvalidArgumentException('Only authority-eligible work uploads can be reviewed.');
                }
            }

            $this->uploadRepository->review($uploadIds, $decision, $actorUserId, $comment);

            foreach ($oldUploads as $old) {
                // If it was already the same state, skip audit spam
                if ($old['authority_visibility'] === $decision) {
                    continue;
                }
                
                $this->auditService->logAction(
                    $actorUserId,
                    'UPLOAD_REVIEWED',
                    'upload',
                    (int) $old['id'],
                    ['authority_visibility' => $old['authority_visibility']],
                    ['authority_visibility' => $decision]
                );
            }

            $this->uploadRepository->commit();
        } catch (Throwable $e) {
            $this->uploadRepository->rollBack();
            throw $e;
        }
    }

    private function normalizeCreateData(string $surface, array $surfaceConfig, array $data): array
    {
        $parentType = strtoupper(trim((string) ($data['parent_type'] ?? '')));
        $uploadType = strtoupper(trim((string) ($data['upload_type'] ?? '')));
        $photoLabel = strtoupper(trim((string) ($data['photo_label'] ?? 'GENERAL')));
        $discoveryMode = filter_var($data['discovery_mode'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($parentType !== $surfaceConfig['parent_type']) {
            throw new InvalidArgumentException('Invalid parent_type for this upload surface.');
        }

        if (empty($data['parent_id']) || !is_numeric($data['parent_id'])) {
            throw new InvalidArgumentException('Valid parent_id is required.');
        }

        if (!in_array($uploadType, $surfaceConfig['allowed_upload_types'], true)) {
            throw new InvalidArgumentException('Invalid upload_type for this upload surface.');
        }

        if ($discoveryMode && !$surfaceConfig['allow_discovery_mode']) {
            throw new InvalidArgumentException('Discovery mode is not allowed on this upload surface.');
        }

        if ($surface !== 'TASK' && $photoLabel !== 'GENERAL') {
            throw new InvalidArgumentException('Only task uploads may use non-general photo labels.');
        }

        if ($surface === 'TASK' && !in_array($photoLabel, ['BEFORE_WORK', 'AFTER_WORK', 'GENERAL'], true)) {
            throw new InvalidArgumentException('Invalid photo_label for task uploads.');
        }

        return [
            'parent_type' => $parentType,
            'parent_id' => (int) $data['parent_id'],
            'upload_type' => $uploadType,
            'work_type' => $this->normalizeOptionalString($data['work_type'] ?? null, 100),
            'photo_label' => $photoLabel,
            'comment_text' => $this->normalizeOptionalString($data['comment_text'] ?? null),
            'is_discovery_mode' => $discoveryMode,
            'gps_latitude' => $this->normalizeOptionalDecimal($data['gps_latitude'] ?? null, -90.0, 90.0),
            'gps_longitude' => $this->normalizeOptionalDecimal($data['gps_longitude'] ?? null, -180.0, 180.0),
            'authority_visibility' => $this->resolveDefaultAuthorityVisibility($surface, $uploadType),
        ];
    }

    private function resolveDefaultAuthorityVisibility(string $surface, string $uploadType): string
    {
        if ($surface === 'SUPERVISOR') {
            return $uploadType === 'WORK' ? 'HIDDEN' : 'NOT_ELIGIBLE';
        }

        return 'NOT_ELIGIBLE';
    }

    private function normalizeListFilters(array $filters): array
    {
        $normalized = [];

        if (!empty($filters['parent_type'])) {
            $normalized['parent_type'] = strtoupper((string) $filters['parent_type']);
        }

        if (!empty($filters['parent_id']) && is_numeric($filters['parent_id'])) {
            $normalized['parent_id'] = (int) $filters['parent_id'];
        }

        if (!empty($filters['upload_type'])) {
            $normalized['upload_type'] = strtoupper((string) $filters['upload_type']);
        }

        if (array_key_exists('discovery_mode', $filters)) {
            $normalized['discovery_mode'] = filter_var($filters['discovery_mode'], FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($filters['authority_visibility'])) {
            $normalized['authority_visibility'] = strtoupper((string) $filters['authority_visibility']);
        }

        if (!empty($filters['date_from'])) {
            $normalized['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $normalized['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['include_deleted'])) {
            $normalized['include_deleted'] = true;
        }

        return $normalized;
    }

    private function verifyRecordScope(string $surface, int $parentId, int $actorUserId): void
    {
        if ($surface === 'SUPERVISOR') {
            require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
            $repo = new BeltAssignmentRepository();
            $active = $repo->findAll('supervisor', [
                'belt_id' => $parentId,
                'user_id' => $actorUserId,
                'active_only' => true,
            ]);
            if (empty($active)) {
                throw new DomainException('You are not currently assigned to this green belt.');
            }
        } elseif ($surface === 'OUTSOURCED') {
            require_once __DIR__ . '/../repositories/BeltAssignmentRepository.php';
            $repo = new BeltAssignmentRepository();
            $active = $repo->findAll('outsourced', [
                'belt_id' => $parentId,
                'user_id' => $actorUserId,
                'active_only' => true,
            ]);
            if (empty($active)) {
                throw new DomainException('You are not currently assigned to this outsourced belt.');
            }
        } elseif ($surface === 'TASK') {
            require_once __DIR__ . '/../repositories/TaskRepository.php';
            $taskRepo = new TaskRepository();
            $task = $taskRepo->findById($parentId);
            if (!$task) {
                throw new DomainException('Task not found.');
            }
            if ((int) ($task['assigned_lead_user_id'] ?? 0) !== $actorUserId) {
                throw new DomainException('You are not the assigned lead for this task.');
            }
        } elseif ($surface === 'MONITORING') {
            // The RBAC middleware already verified session role_key before reaching this point.
            // No per-site assignment table exists in v1 — role-level access is the full scope check.
            $roleKey = $_SESSION['role_key'] ?? '';
            if ($roleKey !== 'MONITORING_TEAM' && $roleKey !== 'OPS_MANAGER') {
                throw new DomainException('Role not authorised to submit monitoring uploads.');
            }
        }
    }

    private function createOrRefreshDiscoveryRecord(int $siteId, int $representativeUploadId): void
    {
        $today = date('Y-m-d');
        $existing = $this->uploadRepository->findDiscoveryFreeMediaBySiteId($siteId);

        if ($existing !== null) {
            $this->uploadRepository->refreshDiscoveryFreeMediaRecord(
                (int) $existing['id'],
                $representativeUploadId,
                $today
            );
            return;
        }

        $this->uploadRepository->createDiscoveryFreeMediaRecord(
            $siteId,
            $representativeUploadId,
            $today
        );
    }

    private function normalizeOptionalString($value, ?int $maxLength = null): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if ($maxLength !== null) {
            $normalized = mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    private function normalizeOptionalDecimal($value, ?float $min = null, ?float $max = null): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException('GPS coordinates must be numeric when supplied.');
        }

        $floatVal = (float) $value;

        if ($min !== null && $floatVal < $min) {
            throw new InvalidArgumentException("Value {$floatVal} is below minimum {$min}.");
        }

        if ($max !== null && $floatVal > $max) {
            throw new InvalidArgumentException("Value {$floatVal} is above maximum {$max}.");
        }

        return (string) $value;
    }

    public function getCleanupList(array $filters, int $page = 1, int $limit = 50): array
    {
        $threshold = $this->getCleanupThreshold();

        return [
            'total' => $this->uploadRepository->countEligibleForCleanup($threshold, $filters),
            'items' => $this->uploadRepository->findEligibleForCleanup($threshold, $filters, $page, $limit),
        ];
    }

    public function purgeUploads(array $uploadIds, int $actorUserId): void
    {
        if (empty($uploadIds)) {
            throw new InvalidArgumentException('No uploads provided for purge.');
        }

        $threshold = $this->getCleanupThreshold();

        $this->uploadRepository->beginTransaction();

        try {
            // Unlink files first (in production, might be queued, but we do it synchronously here)
            // Need to fetch file_paths to unlink them
            $rows = $this->uploadRepository->getUploadsForPurge($uploadIds, $threshold);

            $eligibleIds = [];
            foreach ($rows as $row) {
                 $eligibleIds[] = (int)$row['id'];
            }

            if (empty($eligibleIds)) {
                 $this->uploadRepository->rollBack();
                 throw new DomainException('None of the provided uploads are eligible for purge.');
            }

            $this->uploadRepository->purge($eligibleIds, $actorUserId);

            foreach ($rows as $row) {
                 if ($row['file_path']) {
                     $absolutePath = $this->storageService->getAbsolutePath($row['file_path']);
                     if ($absolutePath && file_exists($absolutePath)) {
                         if (!unlink($absolutePath)) {
                             error_log('[SKITE PURGE] Failed to unlink file: ' . $absolutePath);
                         }
                     }
                 }
            }

            $this->auditService->logAction(
                $actorUserId,
                'PURGE_UPLOADS',
                'upload',
                $eligibleIds[0],
                null,
                ['purged_ids' => $eligibleIds, 'purged_count' => count($eligibleIds)]
            );

            $this->uploadRepository->commit();
        } catch (Exception $e) {
            $this->uploadRepository->rollBack();
            throw $e;
        }
    }

    private function getCleanupThreshold(): int
    {
        $settingsService = new SystemSettingsService();
        $settings = $settingsService->listSettings();
        
        foreach ($settings as $setting) {
             if ($setting['setting_key'] === 'rejected_upload_cleanup_days') {
                 return (int)$setting['setting_value'];
             }
        }
        return 30; // Fallback default
    }
}
