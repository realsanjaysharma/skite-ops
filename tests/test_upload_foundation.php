<?php
/**
 * Upload foundation verification script.
 * Tests storage, metadata defaults, list support, discovery side-effects, and self-delete.
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_upload_foundation.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/services/UploadService.php';

$db = Database::getConnection();
$uploadService = new UploadService();
$pass = 0;
$fail = 0;
$createdUploadIds = [];
$createdFreeMediaIds = [];
$createdTaskIds = [];
$createdSiteIds = [];
$createdBeltIds = [];
$storedPaths = [];

function passFail(bool $ok, string $label, string $details = ''): void
{
    global $pass, $fail;

    if ($ok) {
        $pass++;
        echo "   [PASS] {$label}\n";
        return;
    }

    $fail++;
    echo "   [FAIL] {$label}";

    if ($details !== '') {
        echo " - {$details}";
    }

    echo "\n";
}

function makeTempUpload(string $baseName, string $extension, string $binary): array
{
    $tmpPath = tempnam(sys_get_temp_dir(), 'skite_upload_');
    file_put_contents($tmpPath, $binary);

    return [
        'name' => "{$baseName}.{$extension}",
        'tmp_name' => $tmpPath,
        'type' => '',
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tmpPath),
    ];
}

function wrapFiles(array $fileRows): array
{
    return [
        'name' => array_column($fileRows, 'name'),
        'tmp_name' => array_column($fileRows, 'tmp_name'),
        'type' => array_column($fileRows, 'type'),
        'error' => array_column($fileRows, 'error'),
        'size' => array_column($fileRows, 'size'),
    ];
}

function ensureUser(PDO $db, string $roleKey, string $email, string $fullName): int
{
    $roleStmt = $db->prepare("SELECT id FROM roles WHERE role_key = ?");
    $roleStmt->execute([$roleKey]);
    $roleId = $roleStmt->fetch(PDO::FETCH_ASSOC)['id'] ?? null;

    if (!$roleId) {
        throw new RuntimeException("Role {$roleKey} not found.");
    }

    $existingStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $existingStmt->execute([$email]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $db->prepare(
            "UPDATE users
             SET role_id = ?, full_name = ?, is_active = 1, is_deleted = 0, force_password_reset = 0
             WHERE id = ?"
        )->execute([$roleId, $fullName, $existing['id']]);

        return (int) $existing['id'];
    }

    $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password_hash, is_active, force_password_reset)
         VALUES (?, ?, ?, ?, 1, 0)"
    )->execute([$roleId, $fullName, $email, password_hash('TempPass123!', PASSWORD_DEFAULT)]);

    return (int) $db->lastInsertId();
}

echo "\n=== UPLOAD FOUNDATION VERIFICATION ===\n\n";

try {
    $opsUserId = ensureUser($db, 'OPS_MANAGER', 'ops.upload.foundation@skite.local', 'Upload Foundation Ops');
    $supervisorUserId = ensureUser($db, 'GREEN_BELT_SUPERVISOR', 'gbs.upload.foundation@skite.local', 'Upload Foundation Supervisor');
    $outsourcedUserId = ensureUser($db, 'OUTSOURCED_MAINTAINER', 'out.upload.foundation@skite.local', 'Upload Foundation Outsourced');
    $leadUserId = ensureUser($db, 'FABRICATION_LEAD', 'lead.upload.foundation@skite.local', 'Upload Foundation Lead');

    $beltCode = 'UPF-GB-' . time();
    $db->prepare(
        "INSERT INTO green_belts
        (belt_code, common_name, authority_name, permission_status, maintenance_mode, watering_frequency, is_hidden)
        VALUES (?, ?, ?, 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY', 0)"
    )->execute([$beltCode, 'Upload Foundation Belt', 'Upload Authority']);
    $beltId = (int) $db->lastInsertId();
    $createdBeltIds[] = $beltId;

    $db->prepare(
        "INSERT INTO belt_supervisor_assignments (belt_id, supervisor_user_id, start_date)
        VALUES (?, ?, CURDATE())"
    )->execute([$beltId, $supervisorUserId]);

    $db->prepare(
        "INSERT INTO belt_outsourced_assignments (belt_id, outsourced_user_id, start_date)
        VALUES (?, ?, CURDATE())"
    )->execute([$beltId, $outsourcedUserId]);

    $siteCode = 'UPF-SITE-' . time();
    $db->prepare(
        "INSERT INTO sites
        (site_code, location_text, site_category, green_belt_id, lighting_type, is_active)
        VALUES (?, ?, 'CITY', ?, 'NON_LIT', 1)"
    )->execute([$siteCode, 'Upload Foundation Site', $beltId]);
    $siteId = (int) $db->lastInsertId();
    $createdSiteIds[] = $siteId;

    $db->prepare(
        "INSERT INTO tasks
        (task_source_type, assigned_by_user_id, assigned_lead_user_id, task_category, vertical_type, work_description, location_text, priority, start_date, status, progress_percent, is_archived)
        VALUES ('MANUAL_TEST', ?, ?, 'INSTALLATION', 'ADVERTISEMENT', 'Upload foundation task', 'Upload Foundation Task Location', 'MEDIUM', CURDATE(), 'OPEN', 0, 0)"
    )->execute([$opsUserId, $leadUserId]);
    $taskId = (int) $db->lastInsertId();
    $createdTaskIds[] = $taskId;

    $jpegBinary = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBAQEA8QDw8PEA8PDw8PDw8QDxAQFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGy0lICUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAAEAAQMBIgACEQEDEQH/xAAXAAADAQAAAAAAAAAAAAAAAAAAAQID/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAwDAQACEAMQAAAByA//xAAXEAEBAQEAAAAAAAAAAAAAAAABEQAS/9oACAEBAAEFAk5b/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAwEBPwEf/8QAFBEBAAAAAAAAAAAAAAAAAAAAEP/aAAgBAgEBPwEf/8QAGhAAAwADAQAAAAAAAAAAAAAAAQIRAyExQf/aAAgBAQAGPwK6mU3Vv//EABsQAQADAQEBAQAAAAAAAAAAAAEAESExQVFh/9oACAEBAAE/IbDzii0x2qJ4iM5LJv/Z');
    $pngBinary = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6kXioAAAAASUVORK5CYII=');

    echo "1. SUPERVISOR WORK MULTI-UPLOAD\n";
    $supervisorWork = $uploadService->createUploadsForSurface(
        'SUPERVISOR',
        [
            'parent_type' => 'GREEN_BELT',
            'parent_id' => $beltId,
            'upload_type' => 'WORK',
            'work_type' => 'CLEANING',
            'comment_text' => 'Supervisor work proof',
            'gps_latitude' => '28.6139',
            'gps_longitude' => '77.2090',
        ],
        wrapFiles([
            makeTempUpload('supervisor-one', 'jpg', $jpegBinary),
            makeTempUpload('supervisor-two', 'png', $pngBinary),
        ]),
        $supervisorUserId
    );
    $supervisorCreated = $supervisorWork['created_uploads'] ?? [];
    $createdUploadIds = array_merge($createdUploadIds, array_column($supervisorCreated, 'id'));
    passFail(count($supervisorCreated) === 2, 'Supervisor work creates two upload rows');
    passFail(($supervisorCreated[0]['authority_visibility'] ?? null) === 'HIDDEN', 'Supervisor work defaults to HIDDEN');

    $uploadRowStmt = $db->prepare("SELECT file_path FROM uploads WHERE id = ?");
    $uploadRowStmt->execute([(int) ($supervisorCreated[0]['id'] ?? 0)]);
    $storedPath = $uploadRowStmt->fetch(PDO::FETCH_ASSOC)['file_path'] ?? '';
    $storedPaths[] = $storedPath;
    $absoluteStoredPath = __DIR__ . '/../storage/' . str_replace('/', DIRECTORY_SEPARATOR, $storedPath);
    passFail(strpos($storedPath, 'uploads/green_belt/') === 0, 'Stored path uses lowercase parent folder');
    passFail(is_file($absoluteStoredPath), 'Stored upload file exists on disk');

    echo "\n2. SUPERVISOR ISSUE DEFAULT\n";
    $supervisorIssue = $uploadService->createUploadsForSurface(
        'SUPERVISOR',
        [
            'parent_type' => 'GREEN_BELT',
            'parent_id' => $beltId,
            'upload_type' => 'ISSUE',
            'comment_text' => 'Issue proof',
        ],
        wrapFiles([
            makeTempUpload('supervisor-issue', 'jpg', $jpegBinary),
        ]),
        $supervisorUserId
    );
    $issueId = (int) ($supervisorIssue['created_uploads'][0]['id'] ?? 0);
    $createdUploadIds[] = $issueId;
    passFail(($supervisorIssue['created_uploads'][0]['authority_visibility'] ?? null) === 'NOT_ELIGIBLE', 'Supervisor issue defaults to NOT_ELIGIBLE');

    echo "\n3. OUTSOURCED WORK DEFAULT\n";
    $outsourcedWork = $uploadService->createUploadsForSurface(
        'OUTSOURCED',
        [
            'parent_type' => 'GREEN_BELT',
            'parent_id' => $beltId,
            'upload_type' => 'WORK',
        ],
        wrapFiles([
            makeTempUpload('outsourced-work', 'png', $pngBinary),
        ]),
        $outsourcedUserId
    );
    $outsourcedId = (int) ($outsourcedWork['created_uploads'][0]['id'] ?? 0);
    $createdUploadIds[] = $outsourcedId;
    passFail(($outsourcedWork['created_uploads'][0]['authority_visibility'] ?? null) === 'NOT_ELIGIBLE', 'Outsourced work defaults to NOT_ELIGIBLE');

    echo "\n4. MONITORING DISCOVERY SIDE EFFECT\n";
    $monitoringDiscovery = $uploadService->createUploadsForSurface(
        'MONITORING',
        [
            'parent_type' => 'SITE',
            'parent_id' => $siteId,
            'upload_type' => 'WORK',
            'discovery_mode' => true,
        ],
        wrapFiles([
            makeTempUpload('monitoring-discovery', 'jpg', $jpegBinary),
        ]),
        $opsUserId
    );
    $monitoringId = (int) ($monitoringDiscovery['created_uploads'][0]['id'] ?? 0);
    $createdUploadIds[] = $monitoringId;
    passFail(($monitoringDiscovery['created_uploads'][0]['is_discovery_mode'] ?? null) === 1, 'Monitoring discovery flag is persisted');

    $freeMediaStmt = $db->prepare(
        "SELECT id, source_reference_id, status
         FROM free_media_records
         WHERE site_id = ?
           AND source_type = 'MONITORING_DISCOVERY'
         ORDER BY id DESC
         LIMIT 1"
    );
    $freeMediaStmt->execute([$siteId]);
    $freeMediaRow = $freeMediaStmt->fetch(PDO::FETCH_ASSOC);
    if ($freeMediaRow) {
        $createdFreeMediaIds[] = (int) $freeMediaRow['id'];
    }
    passFail(
        $freeMediaRow !== false
            && (int) $freeMediaRow['source_reference_id'] === $monitoringId
            && ($freeMediaRow['status'] ?? null) === 'DISCOVERED',
        'Monitoring discovery creates or refreshes DISCOVERED free-media row'
    );

    echo "\n5. TASK AFTER-WORK LABEL\n";
    $taskUpload = $uploadService->createUploadsForSurface(
        'TASK',
        [
            'parent_type' => 'TASK',
            'parent_id' => $taskId,
            'upload_type' => 'WORK',
            'photo_label' => 'AFTER_WORK',
        ],
        wrapFiles([
            makeTempUpload('task-after', 'jpg', $jpegBinary),
        ]),
        $leadUserId
    );
    $taskUploadId = (int) ($taskUpload['created_uploads'][0]['id'] ?? 0);
    $createdUploadIds[] = $taskUploadId;
    passFail(($taskUpload['created_uploads'][0]['photo_label'] ?? null) === 'AFTER_WORK', 'Task upload keeps AFTER_WORK label');
    passFail(($taskUpload['created_uploads'][0]['authority_visibility'] ?? null) === 'NOT_ELIGIBLE', 'Task upload defaults to NOT_ELIGIBLE');

    echo "\n6. CREATOR LIST AND SELF-DELETE\n";
    $myUploadsBeforeDelete = $uploadService->listCreatorUploads($supervisorUserId);
    // listCreatorUploads returns a paginated wrapper: ['items' => [...], 'pagination' => [...]]
    $supervisorListBefore = $myUploadsBeforeDelete['items'] ?? [];
    passFail(count($supervisorListBefore) >= 3, 'Creator upload list returns supervisor-created uploads');

    $deletedUpload = $uploadService->softDeleteUpload((int) ($supervisorCreated[0]['id'] ?? 0), $supervisorUserId);
    passFail((int) ($deletedUpload['is_deleted'] ?? 0) === 1, 'Upload self-delete marks row deleted');

    $myUploadsAfterDelete = $uploadService->listCreatorUploads($supervisorUserId);
    $afterItems = $myUploadsAfterDelete['items'] ?? [];
    $deletedStillVisible = false;
    foreach ($afterItems as $row) {
        if ((int) $row['id'] === (int) ($supervisorCreated[0]['id'] ?? 0)) {
            $deletedStillVisible = true;
            break;
        }
    }
    passFail(!$deletedStillVisible, 'Deleted upload disappears from normal creator list');

    echo "\n7. INVALID MIME REJECTION\n";
    try {
        $uploadService->createUploadsForSurface(
            'SUPERVISOR',
            [
                'parent_type' => 'GREEN_BELT',
                'parent_id' => $beltId,
                'upload_type' => 'WORK',
            ],
            wrapFiles([
                makeTempUpload('bad-file', 'png', 'not an image'),
            ]),
            $supervisorUserId
        );
        passFail(false, 'Invalid MIME upload rejected');
    } catch (Throwable $exception) {
        passFail(true, 'Invalid MIME upload rejected');
    }

    echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";
} catch (Throwable $exception) {
    echo "\nFATAL: " . $exception->getMessage() . "\n";
    exit(1);
} finally {
    foreach ($createdFreeMediaIds as $freeMediaId) {
        $db->prepare("DELETE FROM free_media_records WHERE id = ?")->execute([$freeMediaId]);
    }

    foreach ($createdUploadIds as $uploadId) {
        $stmt = $db->prepare("SELECT file_path FROM uploads WHERE id = ?");
        $stmt->execute([$uploadId]);
        $filePath = $stmt->fetch(PDO::FETCH_ASSOC)['file_path'] ?? null;

        if ($filePath) {
            $absolutePath = __DIR__ . '/../storage/' . str_replace('/', DIRECTORY_SEPARATOR, $filePath);

            if (is_file($absolutePath)) {
                unlink($absolutePath);
            }
        }

        $db->prepare("DELETE FROM uploads WHERE id = ?")->execute([$uploadId]);
    }

    foreach ($createdTaskIds as $taskId) {
        $db->prepare("DELETE FROM tasks WHERE id = ?")->execute([$taskId]);
    }

    foreach ($createdSiteIds as $siteId) {
        $db->prepare("DELETE FROM sites WHERE id = ?")->execute([$siteId]);
    }

    foreach ($createdBeltIds as $beltId) {
        // Must delete child assignment rows before the belt (FK constraints)
        $db->prepare("DELETE FROM belt_supervisor_assignments WHERE belt_id = ?")->execute([$beltId]);
        $db->prepare("DELETE FROM belt_outsourced_assignments WHERE belt_id = ?")->execute([$beltId]);
        $db->prepare("DELETE FROM green_belts WHERE id = ?")->execute([$beltId]);
    }

}

exit($fail > 0 ? 1 : 0);
