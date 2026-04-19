<?php
/**
 * Phase 3 Issues and Requests Verification Script
 * Tests Issue Management and Task Request logic.
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_phase3_issues_requests.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_cookies_p3ir.txt';

$email = 'ops.test.phase2@skite.local';
$password = 'TestPass123!';

function request($url, $method = 'GET', $data = null, $cookieFile = null, $csrfToken = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $headers = ['Content-Type: application/json'];
    if ($csrfToken) {
        $headers[] = 'X-CSRF-Token: ' . $csrfToken;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [
        'http_code' => $httpCode,
        'body' => json_decode($response, true),
        'raw' => $response,
    ];
}

$pass = 0;
$fail = 0;

function check($label, $result, $expectSuccess = true) {
    global $pass, $fail;
    $ok = ($result['body']['success'] ?? false) === $expectSuccess;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
    }
    echo "   [" . ($ok ? 'PASS' : 'FAIL') . "] {$label}";
    if (!$ok) {
        echo " - " . substr($result['raw'] ?? '', 0, 220);
    }
    echo "\n";
    return $ok;
}

echo "\n=== PHASE 3 ISSUES & REQUESTS VERIFICATION ===\n\n";

$login = request($baseUrl . 'auth/login', 'POST', [
    'email' => $email,
    'password' => $password,
], $cookieFile);

if (!($login['body']['success'] ?? false)) {
    echo "Login failed.\n";
    exit(1);
}

$csrf = $login['body']['data']['csrf_token'] ?? '';
echo "Logged in. CSRF obtained.\n\n";

echo "1. CREATE ISSUE\n";
$createIssue = request($baseUrl . 'issue/create', 'POST', [
    'title' => 'Integration Test Damage Issue',
    'priority' => 'HIGH',
    'issue_type' => 'DAMAGE',
    'description' => 'Test Issue created by integration test',
    'site_category' => 'CITY'
], $cookieFile, $csrf);
check('Create issue successfully', $createIssue);
$issueId = $createIssue['body']['data']['id'] ?? 0;
echo "   Issue ID: {$issueId}\n";

echo "\n2. MARK ISSUE IN PROGRESS\n";
if ($issueId) {
    $inProgress = request($baseUrl . 'issue/in-progress', 'POST', [
        'issue_id' => $issueId
    ], $cookieFile, $csrf);
    check('Mark issue in progress', $inProgress);
} else {
    check('Mark issue in progress (skipped)', ['body' => ['success' => false]], true);
}

echo "\n3. CLOSE ISSUE\n";
if ($issueId) {
    $closeIssue = request($baseUrl . 'issue/close', 'POST', [
        'issue_id' => $issueId,
        'resolution_notes' => 'Fixed during integration test.'
    ], $cookieFile, $csrf);
    check('Close issue successfully', $closeIssue);
} else {
    check('Close issue successfully (skipped)', ['body' => ['success' => false]], true);
}

echo "\n4. SEED TASK REQUEST IN DB\n";
// Insert a task request directly since OPS_MANAGER cannot create them via HTTP
$stmt = $db->prepare("INSERT INTO task_requests (request_type, request_source_role, description, status, requester_user_id, created_at, updated_at) VALUES ('FABRICATION', 'SALES_TEAM', 'Test Task Request from integration tests', 'SUBMITTED', (SELECT id FROM users WHERE email='ops.test.phase2@skite.local'), NOW(), NOW())");
$stmt->execute();
$requestId = $db->lastInsertId();
echo "   Seeded Request ID: {$requestId}\n";

echo "\n5. APPROVE TASK REQUEST\n";
if ($requestId) {
    $approveReq = request($baseUrl . 'request/approve', 'POST', [
        'request_id' => $requestId,
        'review_notes' => 'Approved by system tests'
    ], $cookieFile, $csrf);
    check('Approve Request', $approveReq);
} else {
    check('Approve Request (skipped)', ['body' => ['success' => false]], true);
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

exit($fail > 0 ? 1 : 0);
