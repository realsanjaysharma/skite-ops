<?php
/**
 * Frontend Shell Navigation Test
 * Verifies that the correct landing_route is returned for each of the 5 main roles.
 */

$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_nav.txt';

// Ensure test users exist with known passwords (idempotent seed)
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
$navTestUsers = [
    ['role' => 'OPS_MANAGER',              'email' => 'ops.test.phase2@skite.local'],
    ['role' => 'HEAD_SUPERVISOR',          'email' => 'head.nav.test@skite.local'],
    ['role' => 'GREEN_BELT_SUPERVISOR',    'email' => 'gbs.phase1@skite.local'],
    ['role' => 'AUTHORITY_REPRESENTATIVE', 'email' => 'test.authority.p2@skite.local'],
    ['role' => 'FABRICATION_LEAD',         'email' => 'lead.upload.foundation@skite.local'],
];
$navTestPwd = password_hash('TestPass123!', PASSWORD_DEFAULT);
foreach ($navTestUsers as $u) {
    $roleId = $db->query("SELECT id FROM roles WHERE role_key = '{$u['role']}' LIMIT 1")->fetchColumn();
    $existing = $db->prepare("SELECT id FROM users WHERE email = ?")->execute([$u['email']]) ? $db->query("SELECT id FROM users WHERE email = '{$u['email']}' LIMIT 1")->fetchColumn() : null;
    if ($existing) {
        $db->prepare("UPDATE users SET password_hash = ?, is_active = 1, is_deleted = 0, force_password_reset = 0, failed_attempt_count = 0 WHERE email = ?")->execute([$navTestPwd, $u['email']]);
    } else {
        $db->prepare("INSERT INTO users (role_id, full_name, email, password_hash, is_active, force_password_reset) VALUES (?, ?, ?, ?, 1, 0)")->execute([$roleId, 'Nav Test ' . $u['role'], $u['email'], $navTestPwd]);
    }
}

$roles = [
    'OPS_MANAGER' => [
        'email' => 'ops.test.phase2@skite.local',
        'expected_landing' => 'dashboard/master'
    ],
    'HEAD_SUPERVISOR' => [
        'email' => 'head.nav.test@skite.local',
        'expected_landing' => 'oversight/watering'
    ],
    'GREEN_BELT_SUPERVISOR' => [
        'email' => 'gbs.phase1@skite.local',
        'expected_landing' => 'upload/supervisor'
    ],
    'AUTHORITY_REPRESENTATIVE' => [
        'email' => 'test.authority.p2@skite.local',
        'expected_landing' => 'authority/view'
    ],
    'FABRICATION_LEAD' => [
        'email' => 'lead.upload.foundation@skite.local',
        'expected_landing' => 'task/my'
    ]
];

function request($url, $data) {
    global $cookieFile;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400 || !$response) {
        return ['success' => false, 'error' => $response];
    }
    return json_decode($response, true) ?? ['success' => false, 'error' => 'Invalid JSON'];
}

echo "=== FRONTEND SHELL ROLE MAPPING VERIFICATION ===\n\n";

$pass = 0;
$fail = 0;

foreach ($roles as $roleKey => $config) {
    if (file_exists($cookieFile)) unlink($cookieFile);
    
    $res = request($baseUrl . 'auth/login', [
        'email' => $config['email'],
        'password' => 'TestPass123!'
    ]);
    
    if (!isset($res['success']) || !$res['success']) {
        echo "[$roleKey] FAIL: Login failed.\n";
        if (isset($res['error'])) echo "  Response: " . substr(print_r($res['error'], true), 0, 100) . "\n";
        $fail++;
        continue;
    }
    
    $actualLanding = $res['data']['landing_route'] ?? 'MISSING';
    
    if ($actualLanding === $config['expected_landing']) {
        echo "[$roleKey] PASS: Landing route is correctly mapped to -> $actualLanding\n";
        $pass++;
    } else {
        echo "[$roleKey] FAIL: Expected {$config['expected_landing']}, but got $actualLanding\n";
        $fail++;
    }
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) unlink($cookieFile);

exit($fail > 0 ? 1 : 0);
