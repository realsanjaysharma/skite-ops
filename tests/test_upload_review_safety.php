<?php
/**
 * Verifies that authority review cannot promote internal/non-eligible uploads.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../app/services/UploadService.php';

$db = Database::getConnection();
$service = new UploadService();
$createdBeltId = null;
$createdUploadIds = [];

$pass = 0;
$fail = 0;

function assertCheck(string $label, bool $condition): void
{
    global $pass, $fail;
    echo '   [' . ($condition ? 'PASS' : 'FAIL') . "] {$label}\n";
    if ($condition) {
        $pass++;
    } else {
        $fail++;
    }
}

try {
    $opsUserId = (int) $db->query(
        "SELECT u.id
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE r.role_key = 'OPS_MANAGER'
         LIMIT 1"
    )->fetchColumn();

    if ($opsUserId <= 0) {
        throw new RuntimeException('No OPS_MANAGER user exists for upload review safety test.');
    }

    $beltId = (int) $db->query('SELECT id FROM green_belts LIMIT 1')->fetchColumn();
    if ($beltId <= 0) {
        $code = 'TEST_REVIEW_SAFE_' . date('YmdHis');
        $stmt = $db->prepare(
            "INSERT INTO green_belts
             (belt_code, common_name, authority_name, permission_status, maintenance_mode, watering_frequency)
             VALUES (?, 'Review Safety Belt', 'Noida Authority', 'AGREEMENT_SIGNED', 'MAINTAINED', 'DAILY')"
        );
        $stmt->execute([$code]);
        $beltId = (int) $db->lastInsertId();
        $createdBeltId = $beltId;
    }

    $insertUpload = $db->prepare(
        "INSERT INTO uploads
         (parent_type, parent_id, upload_type, file_path, original_file_name, mime_type,
          file_size_bytes, authority_visibility, created_by_user_id, created_at, updated_at)
         VALUES ('GREEN_BELT', ?, ?, NULL, ?, 'image/jpeg', 123, ?, ?, NOW(), NOW())"
    );

    $insertUpload->execute([$beltId, 'ISSUE', 'review-safety-issue.jpg', 'NOT_ELIGIBLE', $opsUserId]);
    $issueUploadId = (int) $db->lastInsertId();
    $createdUploadIds[] = $issueUploadId;

    $insertUpload->execute([$beltId, 'WORK', 'review-safety-work.jpg', 'HIDDEN', $opsUserId]);
    $workUploadId = (int) $db->lastInsertId();
    $createdUploadIds[] = $workUploadId;

    $_SESSION['role_key'] = 'OPS_MANAGER';

    echo "\n=== UPLOAD REVIEW SAFETY TEST ===\n\n";

    $blockedMixedReview = false;
    try {
        $service->reviewUploads([$issueUploadId, $workUploadId], 'APPROVED', $opsUserId, 'Safety test');
    } catch (InvalidArgumentException $e) {
        $blockedMixedReview = true;
    }
    assertCheck('Mixed ISSUE + WORK approval is blocked', $blockedMixedReview);

    $issueVisibility = $db->query("SELECT authority_visibility FROM uploads WHERE id = {$issueUploadId}")->fetchColumn();
    $workVisibilityAfterBlocked = $db->query("SELECT authority_visibility FROM uploads WHERE id = {$workUploadId}")->fetchColumn();
    assertCheck('Issue upload remains NOT_ELIGIBLE', $issueVisibility === 'NOT_ELIGIBLE');
    assertCheck('Work upload remains HIDDEN after blocked mixed review', $workVisibilityAfterBlocked === 'HIDDEN');

    $service->reviewUploads([$workUploadId], 'APPROVED', $opsUserId, 'Safety test');
    $workVisibility = $db->query("SELECT authority_visibility FROM uploads WHERE id = {$workUploadId}")->fetchColumn();
    assertCheck('Eligible WORK upload can still be approved', $workVisibility === 'APPROVED');
} finally {
    if (!empty($createdUploadIds)) {
        $placeholders = implode(',', array_fill(0, count($createdUploadIds), '?'));
        $db->prepare("DELETE FROM audit_logs WHERE entity_type = 'upload' AND entity_id IN ({$placeholders})")->execute($createdUploadIds);
        $db->prepare("DELETE FROM uploads WHERE id IN ({$placeholders})")->execute($createdUploadIds);
    }

    if ($createdBeltId !== null) {
        $db->prepare('DELETE FROM green_belts WHERE id = ?')->execute([$createdBeltId]);
    }
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";
exit($fail > 0 ? 1 : 0);

