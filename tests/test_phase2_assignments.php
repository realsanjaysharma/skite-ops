<?php
/**
 * Phase 2 Assignment Verification Script
 * Tests supervisor/authority/outsourced assignment create and close.
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_phase2_assignments.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_cookies_p2a.txt';

// Ensure test Ops user exists
$email = 'ops.test.phase2@skite.local';
$password = 'TestPass123!';
$hash = password_hash($password, PASSWORD_DEFAULT);
$roleRow = $db->query("SELECT id FROM roles WHERE role_key = 'OPS_MANAGER' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$opsRoleId = $roleRow['id'];

$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)")
       ->execute([$opsRoleId, 'Phase 2 Test Ops', $email, $hash]);
}

// Create test users for assignments
$testUsers = [
    'supervisor' => ['GREEN_BELT_SUPERVISOR', 'test.supervisor.p2@skite.local', 'Test Supervisor P2'],
    'authority'  => ['AUTHORITY_REPRESENTATIVE', 'test.authority.p2@skite.local', 'Test Authority P2'],
    'outsourced' => ['OUTSOURCED_MAINTAINER', 'test.outsourced.p2@skite.local', 'Test Outsourced P2'],
];

$userIds = [];
foreach ($testUsers as $type => $info) {
    $rRow = $db->prepare("SELECT id FROM roles WHERE role_key = ?");
    $rRow->execute([$info[0]]);
    $rId = $rRow->fetch(PDO::FETCH_ASSOC)['id'];

    $uRow = $db->prepare("SELECT id FROM users WHERE email = ?");
    $uRow->execute([$info[1]]);
    $u = $uRow->fetch(PDO::FETCH_ASSOC);

    if ($u) {
        $userIds[$type] = $u['id'];
    } else {
        $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)")
           ->execute([$rId, $info[2], $info[1], $hash]);
        $userIds[$type] = $db->lastInsertId();
    }
    echo "Test user {$type}: ID {$userIds[$type]}\n";
}

// Get belt ID (from previous test)
$beltRow = $db->query("SELECT id FROM green_belts LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$beltRow) {
    echo "ERROR: No belt found. Run test_phase2.php first.\n";
    exit(1);
}
$beltId = $beltRow['id'];
echo "Using belt ID: {$beltId}\n";

function request($url, $method = 'GET', $data = null, $cookieFile = null, $csrfToken = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    $headers = ['Content-Type: application/json'];
    if ($csrfToken) { $headers[] = 'X-CSRF-Token: ' . $csrfToken; }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); }
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['http_code' => $httpCode, 'body' => json_decode($response, true), 'raw' => $response];
}

$pass = 0; $fail = 0;
function check($label, $result, $expectSuccess = true) {
    global $pass, $fail;
    $ok = ($result['body']['success'] ?? false) === $expectSuccess;
    if ($ok) { $pass++; } else { $fail++; }
    echo "   [" . ($ok ? 'PASS' : 'FAIL') . "] {$label}";
    if (!$ok) { echo " — " . substr($result['raw'] ?? '', 0, 200); }
    echo "\n";
    return $ok;
}

echo "\n=== PHASE 2 ASSIGNMENT VERIFICATION ===\n\n";

// Login
$login = request($baseUrl . 'auth/login', 'POST', ['email' => $email, 'password' => $password], $cookieFile);
if (!($login['body']['success'] ?? false)) { echo "Login failed\n"; exit(1); }
$csrf = $login['body']['data']['csrf_token'] ?? '';
echo "Logged in. CSRF obtained.\n\n";

// --- SUPERVISOR ASSIGNMENT ---
echo "=== SUPERVISOR ASSIGNMENT ===\n";

echo "1. CREATE\n";
$saCreate = request($baseUrl . 'supervisorassignment/create', 'POST', [
    'belt_id' => $beltId,
    'supervisor_user_id' => $userIds['supervisor'],
    'start_date' => '2026-04-01',
], $cookieFile, $csrf);
check('Create supervisor assignment', $saCreate);
$saId = $saCreate['body']['data']['id'] ?? 0;
echo "   Assignment ID: {$saId}\n";

echo "2. LIST\n";
$saList = request($baseUrl . "supervisorassignment/list&belt_id={$beltId}", 'GET', null, $cookieFile);
check('List shows 1+ supervisor assignment', $saList);
echo "   Count: " . count($saList['body']['data'] ?? []) . "\n";

echo "3. CLOSE\n";
if ($saId) {
    $saClose = request($baseUrl . 'supervisorassignment/close', 'POST', [
        'assignment_id' => $saId,
        'end_date' => '2026-04-15',
    ], $cookieFile, $csrf);
    check('Close supervisor assignment', $saClose);
}

echo "4. DOUBLE-CLOSE REJECTION\n";
if ($saId) {
    $saDoubleClose = request($baseUrl . 'supervisorassignment/close', 'POST', [
        'assignment_id' => $saId,
        'end_date' => '2026-04-16',
    ], $cookieFile, $csrf);
    check('Double-close rejected', $saDoubleClose, false);
}

// --- AUTHORITY ASSIGNMENT ---
echo "\n=== AUTHORITY ASSIGNMENT ===\n";

echo "5. CREATE\n";
$aaCreate = request($baseUrl . 'authorityassignment/create', 'POST', [
    'belt_id' => $beltId,
    'authority_user_id' => $userIds['authority'],
    'start_date' => '2026-04-01',
], $cookieFile, $csrf);
check('Create authority assignment', $aaCreate);
$aaId = $aaCreate['body']['data']['id'] ?? 0;

echo "6. CLOSE\n";
if ($aaId) {
    $aaClose = request($baseUrl . 'authorityassignment/close', 'POST', [
        'assignment_id' => $aaId,
        'end_date' => '2026-04-15',
    ], $cookieFile, $csrf);
    check('Close authority assignment', $aaClose);
}

// --- OUTSOURCED ASSIGNMENT ---
echo "\n=== OUTSOURCED ASSIGNMENT ===\n";

echo "7. CREATE\n";
$oaCreate = request($baseUrl . 'outsourcedassignment/create', 'POST', [
    'belt_id' => $beltId,
    'outsourced_user_id' => $userIds['outsourced'],
    'start_date' => '2026-04-01',
], $cookieFile, $csrf);
check('Create outsourced assignment', $oaCreate);
$oaId = $oaCreate['body']['data']['id'] ?? 0;

echo "8. CLOSE\n";
if ($oaId) {
    $oaClose = request($baseUrl . 'outsourcedassignment/close', 'POST', [
        'assignment_id' => $oaId,
        'end_date' => '2026-04-15',
    ], $cookieFile, $csrf);
    check('Close outsourced assignment', $oaClose);
}

// --- VALIDATION TESTS ---
echo "\n=== VALIDATION TESTS ===\n";

echo "9. INVALID BELT ID\n";
$badBelt = request($baseUrl . 'supervisorassignment/create', 'POST', [
    'belt_id' => 99999,
    'supervisor_user_id' => $userIds['supervisor'],
    'start_date' => '2026-04-01',
], $cookieFile, $csrf);
check('Invalid belt_id rejected', $badBelt, false);

echo "10. INVALID DATE FORMAT\n";
$badDate = request($baseUrl . 'supervisorassignment/create', 'POST', [
    'belt_id' => $beltId,
    'supervisor_user_id' => $userIds['supervisor'],
    'start_date' => '04-01-2026',
], $cookieFile, $csrf);
check('Invalid date format rejected', $badDate, false);

echo "11. END DATE BEFORE START DATE\n";
$badRange = request($baseUrl . 'supervisorassignment/create', 'POST', [
    'belt_id' => $beltId,
    'supervisor_user_id' => $userIds['supervisor'],
    'start_date' => '2026-04-15',
    'end_date' => '2026-04-01',
], $cookieFile, $csrf);
check('end_date before start_date rejected', $badRange, false);

echo "12. WRONG ROLE REJECTION\n";
$wrongRole = request($baseUrl . 'supervisorassignment/create', 'POST', [
    'belt_id' => $beltId,
    'supervisor_user_id' => $userIds['authority'],
    'start_date' => '2026-04-20',
], $cookieFile, $csrf);
check('Wrong role for supervisor assignment rejected', $wrongRole, false);

// --- BELT DETAIL CHECK ---
echo "\n=== BELT DETAIL WITH ASSIGNMENTS ===\n";

echo "13. BELT DETAIL\n";
$detail = request($baseUrl . "belt/get&belt_id={$beltId}", 'GET', null, $cookieFile);
check('Belt detail returns assignment histories', $detail);
if ($detail['body']['success'] ?? false) {
    echo "   Supervisor assignments: " . count($detail['body']['data']['supervisor_assignments'] ?? []) . "\n";
    echo "   Authority assignments: " . count($detail['body']['data']['authority_assignments'] ?? []) . "\n";
    echo "   Outsourced assignments: " . count($detail['body']['data']['outsourced_assignments'] ?? []) . "\n";
}

// --- AUDIT CHECK ---
echo "\n=== AUDIT LOG CHECK ===\n";
$audits = $db->query("SELECT action_type, entity_type, entity_id FROM audit_logs WHERE entity_type LIKE 'belt_%_assignment' ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
echo "   Assignment audit entries: " . count($audits) . "\n";
foreach ($audits as $a) {
    echo "   - {$a['action_type']} on {$a['entity_type']} #{$a['entity_id']}\n";
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) { unlink($cookieFile); }
exit($fail > 0 ? 1 : 0);
