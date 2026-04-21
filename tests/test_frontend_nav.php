<?php
/**
 * Frontend Shell Navigation Test
 * Verifies that the correct landing_route is returned for each of the 5 main roles.
 */

$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_nav.txt';

$roles = [
    'OPS_MANAGER' => [
        'email' => 'ops.test.phase2@skite.local',
        'expected_landing' => 'dashboard/master'
    ],
    'HEAD_SUPERVISOR' => [
        'email' => 'headsupervisor.phase2@skite.local',
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
