<?php
/**
 * Phase 2 Scope Verification Script
 * Verifies Head Supervisor scope on maintained vs outsourced belts.
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_phase2_scope.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$opsCookieFile = sys_get_temp_dir() . '/skite_test_cookies_p2s_ops.txt';
$hsCookieFile = sys_get_temp_dir() . '/skite_test_cookies_p2s_hs.txt';

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

function check($label, $result, $expectSuccess = true, $expectedHttp = null) {
    global $pass, $fail;
    $successMatches = ($result['body']['success'] ?? false) === $expectSuccess;
    $httpMatches = $expectedHttp === null || $result['http_code'] === $expectedHttp;
    $ok = $successMatches && $httpMatches;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
    }
    echo "   [" . ($ok ? 'PASS' : 'FAIL') . "] {$label}";
    if (!$ok) {
        echo " - HTTP {$result['http_code']} - " . substr($result['raw'] ?? '', 0, 220);
    }
    echo "\n";
    return $ok;
}

echo "\n=== PHASE 2 SCOPE VERIFICATION ===\n\n";

$headSupervisorEmail = 'headsupervisor.phase2@skite.local';
$headSupervisorPassword = 'HeadPass123!';
$headSupervisorHash = password_hash($headSupervisorPassword, PASSWORD_DEFAULT);

$roleStmt = $db->prepare("SELECT id FROM roles WHERE role_key = ?");
$roleStmt->execute(['HEAD_SUPERVISOR']);
$headSupervisorRoleId = $roleStmt->fetch(PDO::FETCH_ASSOC)['id'] ?? null;

if (!$headSupervisorRoleId) {
    echo "HEAD_SUPERVISOR role missing.\n";
    exit(1);
}

$existingHs = $db->prepare("SELECT id FROM users WHERE email = ?");
$existingHs->execute([$headSupervisorEmail]);
$headSupervisorUser = $existingHs->fetch(PDO::FETCH_ASSOC);

if ($headSupervisorUser) {
    $db->prepare(
        "UPDATE users
         SET password_hash = ?, role_id = ?, is_active = 1, is_deleted = 0, failed_attempt_count = 0, force_password_reset = 0
         WHERE id = ?"
    )->execute([$headSupervisorHash, $headSupervisorRoleId, $headSupervisorUser['id']]);
} else {
    $db->prepare(
        "INSERT INTO users (role_id, full_name, email, password_hash, is_active, force_password_reset)
         VALUES (?, ?, ?, ?, 1, 0)"
    )->execute([$headSupervisorRoleId, 'Phase 2 Head Supervisor', $headSupervisorEmail, $headSupervisorHash]);
}

$opsLogin = request($baseUrl . 'auth/login', 'POST', [
    'email' => 'ops.test.phase2@skite.local',
    'password' => 'TestPass123!',
], $opsCookieFile);

if (!($opsLogin['body']['success'] ?? false)) {
    echo "Ops login failed.\n";
    exit(1);
}

$opsCsrf = $opsLogin['body']['data']['csrf_token'] ?? '';

$maintainedCreate = request($baseUrl . 'belt/create', 'POST', [
    'belt_code' => 'P2-SCOPE-M-' . time(),
    'common_name' => 'Scope Maintained Belt',
    'authority_name' => 'Scope Authority',
    'permission_status' => 'AGREEMENT_SIGNED',
    'maintenance_mode' => 'MAINTAINED',
    'watering_frequency' => 'DAILY',
    'is_hidden' => 0,
], $opsCookieFile, $opsCsrf);
check('Ops can create maintained belt for scope test', $maintainedCreate);
$maintainedBeltId = $maintainedCreate['body']['data']['id'] ?? 0;

$outsourcedCreate = request($baseUrl . 'belt/create', 'POST', [
    'belt_code' => 'P2-SCOPE-O-' . time(),
    'common_name' => 'Scope Outsourced Belt',
    'authority_name' => 'Scope Authority',
    'permission_status' => 'AGREEMENT_SIGNED',
    'maintenance_mode' => 'OUTSOURCED',
    'watering_frequency' => 'NOT_REQUIRED',
    'is_hidden' => 0,
], $opsCookieFile, $opsCsrf);
check('Ops can create outsourced belt for scope test', $outsourcedCreate);
$outsourcedBeltId = $outsourcedCreate['body']['data']['id'] ?? 0;

$hsLogin = request($baseUrl . 'auth/login', 'POST', [
    'email' => $headSupervisorEmail,
    'password' => $headSupervisorPassword,
], $hsCookieFile);

if (!($hsLogin['body']['success'] ?? false)) {
    echo "Head Supervisor login failed.\n";
    exit(1);
}

$hsCsrf = $hsLogin['body']['data']['csrf_token'] ?? '';

echo "\n1. BELT DETAIL SCOPE\n";
$maintainedDetail = request($baseUrl . "belt/get&belt_id={$maintainedBeltId}", 'GET', null, $hsCookieFile);
check('Head Supervisor can read maintained belt detail', $maintainedDetail, true, 200);

$outsourcedDetail = request($baseUrl . "belt/get&belt_id={$outsourcedBeltId}", 'GET', null, $hsCookieFile);
check('Head Supervisor cannot read outsourced belt detail', $outsourcedDetail, false, 403);

echo "\n2. CYCLE SCOPE\n";
$maintainedCycle = request($baseUrl . 'cycle/start', 'POST', [
    'belt_id' => $maintainedBeltId,
    'start_date' => '2026-04-17',
], $hsCookieFile, $hsCsrf);
check('Head Supervisor can start cycle on maintained belt', $maintainedCycle, true, 200);

$outsourcedCycle = request($baseUrl . 'cycle/start', 'POST', [
    'belt_id' => $outsourcedBeltId,
    'start_date' => '2026-04-17',
], $hsCookieFile, $hsCsrf);
check('Head Supervisor cannot start cycle on outsourced belt', $outsourcedCycle, false, 403);

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

foreach ([$opsCookieFile, $hsCookieFile] as $cookieFile) {
    if (file_exists($cookieFile)) {
        unlink($cookieFile);
    }
}

exit($fail > 0 ? 1 : 0);
