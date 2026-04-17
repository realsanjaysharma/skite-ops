<?php
/**
 * Phase 2 Cycle Verification Script
 * Tests cycle list/start/close plus governed auto-close on belt update.
 * Run: C:\xampp\php\php.exe c:\xampp\htdocs\skite\tests\test_phase2_cycles.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_cookies_p2c.txt';

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

echo "\n=== PHASE 2 CYCLE VERIFICATION ===\n\n";

$login = request($baseUrl . 'auth/login', 'POST', [
    'email' => $email,
    'password' => $password,
], $cookieFile);

if (!($login['body']['success'] ?? false)) {
    echo "Login failed\n";
    exit(1);
}

$csrf = $login['body']['data']['csrf_token'] ?? '';
echo "Logged in. CSRF obtained.\n\n";

$beltCode = 'P2-CYCLE-' . time();
$createBelt = request($baseUrl . 'belt/create', 'POST', [
    'belt_code' => $beltCode,
    'common_name' => 'Phase 2 Cycle Belt',
    'authority_name' => 'Cycle Test Authority',
    'zone' => 'Cycle Zone',
    'location_text' => 'Cycle Test Location',
    'permission_start_date' => '2026-01-01',
    'permission_end_date' => '2027-01-01',
    'permission_status' => 'AGREEMENT_SIGNED',
    'maintenance_mode' => 'MAINTAINED',
    'watering_frequency' => 'DAILY',
    'is_hidden' => 0,
], $cookieFile, $csrf);
check('Create maintained belt for cycle tests', $createBelt);

$beltId = $createBelt['body']['data']['id'] ?? 0;
echo "   Belt ID: {$beltId}\n";

echo "\n1. START CYCLE\n";
$start = request($baseUrl . 'cycle/start', 'POST', [
    'belt_id' => $beltId,
    'start_date' => '2026-04-17',
], $cookieFile, $csrf);
check('Start cycle on maintained active belt', $start);
$cycleId = $start['body']['data']['id'] ?? 0;
echo "   Cycle ID: {$cycleId}\n";

echo "\n2. DOUBLE START REJECTION\n";
$doubleStart = request($baseUrl . 'cycle/start', 'POST', [
    'belt_id' => $beltId,
    'start_date' => '2026-04-18',
], $cookieFile, $csrf);
check('Second active cycle rejected', $doubleStart, false);

echo "\n3. CYCLE LIST\n";
$list = request($baseUrl . "cycle/list&belt_id={$beltId}&status=ACTIVE", 'GET', null, $cookieFile);
check('List active cycles by belt', $list);
echo "   Active cycle count: " . count($list['body']['data'] ?? []) . "\n";

echo "\n4. CLOSE CYCLE\n";
$close = request($baseUrl . 'cycle/close', 'POST', [
    'cycle_id' => $cycleId,
    'end_date' => '2026-04-18',
    'close_reason' => 'Normal cycle close test',
], $cookieFile, $csrf);
check('Close active cycle', $close);

echo "\n5. AUTO-CLOSE ON BELT HIDE\n";
$startTwo = request($baseUrl . 'cycle/start', 'POST', [
    'belt_id' => $beltId,
    'start_date' => '2026-04-19',
], $cookieFile, $csrf);
check('Restart cycle for auto-close test', $startTwo);
$cycleTwoId = $startTwo['body']['data']['id'] ?? 0;
echo "   Auto-close test cycle ID: {$cycleTwoId}\n";

$hideBelt = request($baseUrl . 'belt/update', 'POST', [
    'belt_id' => $beltId,
    'common_name' => 'Phase 2 Cycle Belt',
    'authority_name' => 'Cycle Test Authority',
    'zone' => 'Cycle Zone',
    'location_text' => 'Cycle Test Location',
    'permission_start_date' => '2026-01-01',
    'permission_end_date' => '2027-01-01',
    'permission_status' => 'AGREEMENT_SIGNED',
    'maintenance_mode' => 'MAINTAINED',
    'watering_frequency' => 'DAILY',
    'is_hidden' => 1,
], $cookieFile, $csrf);
check('Hide belt to trigger governed auto-close', $hideBelt);

$cycleAfterHide = $db->prepare("SELECT end_date, close_reason FROM maintenance_cycles WHERE id = ?");
$cycleAfterHide->execute([$cycleTwoId]);
$cycleAfterHideRow = $cycleAfterHide->fetch(PDO::FETCH_ASSOC);
$autoClosed = !empty($cycleAfterHideRow['end_date']) && $cycleAfterHideRow['close_reason'] === 'AUTO_CLOSED_BELT_HIDDEN';
if ($autoClosed) {
    $pass++;
    echo "   [PASS] Active cycle auto-closed when belt hidden\n";
} else {
    $fail++;
    echo "   [FAIL] Active cycle auto-closed when belt hidden\n";
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

exit($fail > 0 ? 1 : 0);
