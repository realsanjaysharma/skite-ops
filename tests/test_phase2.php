<?php
/**
 * Phase 2 Live Verification Script
 * 
 * This script first creates a test Ops user with a known password,
 * then tests belt CRUD and assignment endpoints.
 * 
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_phase2.php
 */

// Direct DB setup for test user
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

// Create a test Ops user with known password
$email = 'ops.test.phase2@skite.local';
$password = 'TestPass123!';
$hash = password_hash($password, PASSWORD_DEFAULT);

// Get OPS_MANAGER role_id
$roleRow = $db->query("SELECT id FROM roles WHERE role_key = 'OPS_MANAGER' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
if (!$roleRow) {
    echo "ERROR: OPS_MANAGER role not found in DB. Run the seed first.\n";
    exit(1);
}
$opsRoleId = $roleRow['id'];

// Insert or update test user
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $db->prepare("UPDATE users SET password_hash = ?, is_active = 1, is_deleted = 0, failed_attempt_count = 0, force_password_reset = 0 WHERE id = ?")
       ->execute([$hash, $existing['id']]);
    echo "Test user reset: {$email}\n";
} else {
    $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, is_active) VALUES (?, ?, ?, ?, 1)")
       ->execute([$opsRoleId, 'Phase 2 Test Ops', $email, $hash]);
    echo "Test user created: {$email}\n";
}

// Now test via HTTP
$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_cookies_p2.txt';

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
    $status = $ok ? 'PASS' : 'FAIL';
    if ($ok) { $pass++; } else { $fail++; }
    $msg = $expectSuccess
        ? ($result['body']['error'] ?? '')
        : ($result['body']['error'] ?? 'no error returned');
    echo "   [{$status}] {$label}";
    if (!$ok) {
        echo " — " . ($result['raw'] ?? '');
    }
    echo "\n";
    return $ok;
}

echo "\n=== PHASE 2 LIVE VERIFICATION ===\n\n";

// 1. LOGIN
echo "1. LOGIN\n";
$login = request($baseUrl . 'auth/login', 'POST', [
    'email' => $email,
    'password' => $password,
], $cookieFile);

if (!check('Login as test Ops user', $login)) {
    echo "Cannot continue without auth. Raw: {$login['raw']}\n";
    exit(1);
}
$csrfToken = $login['body']['data']['csrf_token'] ?? '';

// 2. BELT LIST (empty initially)
echo "\n2. BELT LIST\n";
$list = request($baseUrl . 'belt/list', 'GET', null, $cookieFile);
check('GET belt/list returns success', $list);

// 3. CREATE BELT
echo "\n3. CREATE BELT\n";
$beltData = [
    'belt_code' => 'P2-TEST-' . time(),
    'common_name' => 'Phase 2 Test Belt',
    'authority_name' => 'Test Authority',
    'zone' => 'Test Zone',
    'location_text' => 'Near Roundabout',
    'latitude' => 28.6139391,
    'longitude' => 77.2090212,
    'permission_start_date' => '2026-01-01',
    'permission_end_date' => '2027-01-01',
    'permission_status' => 'AGREEMENT_SIGNED',
    'maintenance_mode' => 'MAINTAINED',
    'watering_frequency' => 'DAILY',
    'is_hidden' => 0,
];
$create = request($baseUrl . 'belt/create', 'POST', $beltData, $cookieFile, $csrfToken);
check('POST belt/create', $create);
$newBeltId = $create['body']['data']['id'] ?? 0;
echo "   Belt ID: {$newBeltId}\n";

// 4. GET BELT DETAIL
echo "\n4. GET BELT DETAIL\n";
if ($newBeltId) {
    $detail = request($baseUrl . "belt/get&belt_id={$newBeltId}", 'GET', null, $cookieFile);
    check('GET belt/get returns belt + assignments', $detail);
    if ($detail['body']['success'] ?? false) {
        $hasBelt = isset($detail['body']['data']['belt']);
        $hasSA = isset($detail['body']['data']['supervisor_assignments']);
        $hasAA = isset($detail['body']['data']['authority_assignments']);
        $hasCycleSummary = isset($detail['body']['data']['recent_cycle_summary']);
        $hasWateringSummary = isset($detail['body']['data']['recent_watering_summary']);
        $hasCycleHistory = isset($detail['body']['data']['cycle_history']);
        $hasWateringHistory = isset($detail['body']['data']['watering_history']);
        $hasUploads = isset($detail['body']['data']['uploads']);
        $hasIssues = isset($detail['body']['data']['issues']);
        echo "   belt present: " . ($hasBelt ? 'yes' : 'no') . "\n";
        echo "   supervisor_assignments present: " . ($hasSA ? 'yes' : 'no') . "\n";
        echo "   authority_assignments present: " . ($hasAA ? 'yes' : 'no') . "\n";
        echo "   recent_cycle_summary present: " . ($hasCycleSummary ? 'yes' : 'no') . "\n";
        echo "   recent_watering_summary present: " . ($hasWateringSummary ? 'yes' : 'no') . "\n";
        echo "   cycle_history present: " . ($hasCycleHistory ? 'yes' : 'no') . "\n";
        echo "   watering_history present: " . ($hasWateringHistory ? 'yes' : 'no') . "\n";
        echo "   uploads present: " . ($hasUploads ? 'yes' : 'no') . "\n";
        echo "   issues present: " . ($hasIssues ? 'yes' : 'no') . "\n";
    }
}

// 5. UPDATE BELT
echo "\n5. UPDATE BELT\n";
if ($newBeltId) {
    $update = request($baseUrl . 'belt/update', 'POST', [
        'belt_id' => $newBeltId,
        'common_name' => 'Updated Phase 2 Belt',
        'authority_name' => 'Updated Authority',
        'zone' => 'Zone B',
        'location_text' => 'Updated location',
        'latitude' => 28.62,
        'longitude' => 77.21,
        'permission_start_date' => '2026-01-01',
        'permission_end_date' => '2027-06-01',
        'permission_status' => 'AGREEMENT_SIGNED',
        'maintenance_mode' => 'MAINTAINED',
        'watering_frequency' => 'ALTERNATE_DAY',
        'is_hidden' => 0,
    ], $cookieFile, $csrfToken);
    check('POST belt/update', $update);
}

// 6. DUPLICATE BELT CODE REJECTION
echo "\n6. DUPLICATE BELT CODE\n";
$dup = request($baseUrl . 'belt/create', 'POST', $beltData, $cookieFile, $csrfToken);
check('Duplicate belt_code rejected', $dup, false);

// 7. GPS PAIR VALIDATION
echo "\n7. GPS PAIR VALIDATION\n";
$gpsBad = $beltData;
$gpsBad['belt_code'] = 'P2-GPS-' . time();
$gpsBad['latitude'] = 28.5;
unset($gpsBad['longitude']);
$gpsResult = request($baseUrl . 'belt/create', 'POST', $gpsBad, $cookieFile, $csrfToken);
check('GPS without pair rejected', $gpsResult, false);

// 8. INVALID ENUM REJECTION
echo "\n8. INVALID ENUM\n";
$badEnum = $beltData;
$badEnum['belt_code'] = 'P2-ENUM-' . time();
$badEnum['permission_status'] = 'INVALID';
$enumResult = request($baseUrl . 'belt/create', 'POST', $badEnum, $cookieFile, $csrfToken);
check('Invalid enum rejected', $enumResult, false);

// 9. SUPERVISOR ASSIGNMENT LIST
echo "\n9. SUPERVISOR ASSIGNMENT LIST\n";
if ($newBeltId) {
    $saList = request($baseUrl . "supervisorassignment/list&belt_id={$newBeltId}", 'GET', null, $cookieFile);
    check('GET supervisorassignment/list', $saList);
}

// 10. AUTHORITY ASSIGNMENT LIST
echo "\n10. AUTHORITY ASSIGNMENT LIST\n";
if ($newBeltId) {
    $aaList = request($baseUrl . "authorityassignment/list&belt_id={$newBeltId}", 'GET', null, $cookieFile);
    check('GET authorityassignment/list', $aaList);
}

// 11. OUTSOURCED ASSIGNMENT LIST
echo "\n11. OUTSOURCED ASSIGNMENT LIST\n";
if ($newBeltId) {
    $oaList = request($baseUrl . "outsourcedassignment/list&belt_id={$newBeltId}", 'GET', null, $cookieFile);
    check('GET outsourcedassignment/list', $oaList);
}

// 12. BELT LIST WITH FILTER
echo "\n12. BELT LIST WITH ZONE FILTER\n";
$filtered = request($baseUrl . 'belt/list&zone=Zone+B', 'GET', null, $cookieFile);
check('GET belt/list with zone filter', $filtered);
if ($filtered['body']['success'] ?? false) {
    echo "   Total matching: " . ($filtered['body']['data']['pagination']['total'] ?? 0) . "\n";
}

// 13. AUDIT LOG CHECK
echo "\n13. AUDIT LOG CHECK\n";
$audits = $db->query("SELECT action_type, entity_type, entity_id FROM audit_logs WHERE entity_type = 'green_belt' ORDER BY id DESC LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
echo "   Recent green_belt audit entries: " . count($audits) . "\n";
foreach ($audits as $a) {
    echo "   - {$a['action_type']} on {$a['entity_type']} #{$a['entity_id']}\n";
}

// 14. CLEANUP TEST ARTIFACTS
echo "\n14. CLEANUP\n";
if (!empty($newBeltId)) {
    $db->exec("DELETE FROM audit_logs WHERE entity_type = 'green_belt' AND entity_id = " . (int)$newBeltId);
    $db->exec("DELETE FROM belt_supervisor_assignments WHERE belt_id = " . (int)$newBeltId);
    $db->exec("DELETE FROM belt_authority_assignments WHERE belt_id = " . (int)$newBeltId);
    $db->exec("DELETE FROM belt_outsourced_assignments WHERE belt_id = " . (int)$newBeltId);
    $db->exec("DELETE FROM green_belts WHERE id = " . (int)$newBeltId);
    echo "   [OK] Cleaned up belt artifacts for #{$newBeltId}\n";
}
// Test user is intentionally preserved for use by subsequent phase test scripts.

// Summary
echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

exit($fail > 0 ? 1 : 0);
