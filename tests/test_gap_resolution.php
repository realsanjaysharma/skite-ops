<?php
/**
 * Gap Resolution Verification Script
 * Tests the new Dashboard, Oversight, Audit, and Upload Review APIs.
 */

$baseUrl = 'http://127.0.0.1/skite/index.php?route=';
$cookieFile = sys_get_temp_dir() . '/skite_test_cookies_gap.txt';

$email = 'ops.test.phase2@skite.local';
$password = 'TestPass123!';

function request($url, $method = 'GET', $data = null, $cookieFile = null, $csrfToken = null, $isMultipart = false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    
    $headers = [];
    if (!$isMultipart) {
        $headers[] = 'Content-Type: application/json';
    }
    
    if ($csrfToken) {
        $headers[] = 'X-CSRF-Token: ' . $csrfToken;
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isMultipart ? $data : json_encode($data));
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
    echo "   [" . ($ok ? 'PASS' : 'FAIL') . "] {$label}\n";
    if (!$ok) {
        echo " - " . substr($result['raw'] ?? '', 0, 220) . "\n";
    }
    return $ok;
}

echo "\n=== GAP RESOLUTION API VERIFICATION ===\n\n";

$login = request($baseUrl . 'auth/login', 'POST', [
    'email' => $email,
    'password' => $password,
], $cookieFile);

if (!($login['body']['success'] ?? false)) {
    echo "Login failed.\n";
    exit(1);
}

$csrf = $login['body']['data']['csrf_token'] ?? '';
echo "Logged in as Ops. CSRF obtained.\n\n";

echo "1. MASTER DASHBOARD\n";
$dashboard = request($baseUrl . 'dashboard/master', 'GET', null, $cookieFile);
check('Fetch Master Dashboard', $dashboard);
if ($dashboard['body']['success'] ?? false) {
    echo "   Keys: " . implode(', ', array_keys($dashboard['body']['data'])) . "\n";
}

echo "\n2. MANAGEMENT DASHBOARD\n";
// OPS_MANAGER has all modules per RBAC spec, so management dashboard should succeed
$mgmt = request($baseUrl . 'dashboard/management', 'GET', null, $cookieFile);
check('Management Dashboard Accessible by Ops', $mgmt, true);

echo "\n3. OVERSIGHT WATERING\n";
$oversight = request($baseUrl . 'oversight/watering', 'GET', null, $cookieFile);
check('Fetch Oversight Watering', $oversight);

echo "\n4. AUDIT LIST\n";
$auditList = request($baseUrl . 'audit/list&limit=5', 'GET', null, $cookieFile);
check('Fetch Audit List', $auditList);

echo "\n5. UPLOAD LIST & REVIEW\n";
// List uploads
$uploads = request($baseUrl . 'upload/list&limit=10', 'GET', null, $cookieFile);
check('Fetch Uploads List', $uploads);

// We need an upload ID to review
$items = $uploads['body']['data']['items'] ?? [];
if (count($items) > 0) {
    $reviewableItems = array_values(array_filter($items, static function ($item) {
        return ($item['upload_type'] ?? null) === 'WORK'
            && ($item['authority_visibility'] ?? null) !== 'NOT_ELIGIBLE';
    }));

    if (count($reviewableItems) === 0) {
        echo "   [SKIP] Uploads exist, but none are authority-eligible WORK uploads.\n";
    } else {
        $uploadIdToReview = $reviewableItems[0]['id'];
        echo "   Found upload ID $uploadIdToReview to review.\n";

        // Check if it's already approved/rejected, if so, we can just review it again
        $reviewReq = request($baseUrl . 'upload/review', 'POST', [
            'upload_ids' => [$uploadIdToReview],
            'decision' => 'APPROVED',
            'comment' => 'Integration test approval'
        ], $cookieFile, $csrf);

        check('Review Upload as APPROVED', $reviewReq);
    }
} else {
    echo "   [SKIP] No uploads found to review in the database.\n";
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";

if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

exit($fail > 0 ? 1 : 0);
