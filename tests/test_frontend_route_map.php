<?php
/**
 * Frontend route-map contract test.
 *
 * Keeps the vanilla JS navigation shell aligned with RBAC, landing routes,
 * and the backend route registry.
 */

$root = dirname(__DIR__);
$navFile = $root . '/public/js/core/navigation.js';
$indexFile = $root . '/public/index.html';
$routeRegistry = require $root . '/config/route_registry.php';
$rbac = require $root . '/config/rbac.php';

$pass = 0;
$fail = 0;

function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        echo "[PASS] {$label}\n";
        $pass++;
        return;
    }

    echo "[FAIL] {$label}\n";
    $fail++;
}

function extractNavMap(string $source): array
{
    preg_match_all("/'([^']+)'\\s*:\\s*\\{([^\\n]+)\\}/", $source, $matches, PREG_SET_ORDER);

    $map = [];
    foreach ($matches as $match) {
        $moduleKey = $match[1];
        $config = $match[2];

        preg_match("/route:\\s*'([^']+)'/", $config, $routeMatch);
        preg_match("/hidden:\\s*true/", $config, $hiddenMatch);
        preg_match("/roles:\\s*\\[([^\\]]*)\\]/", $config, $rolesMatch);

        $roles = [];
        if (!empty($rolesMatch[1])) {
            preg_match_all("/'([^']+)'/", $rolesMatch[1], $roleMatches);
            $roles = $roleMatches[1] ?? [];
        }

        $map[$moduleKey] = [
            'route' => $routeMatch[1] ?? null,
            'hidden' => !empty($hiddenMatch),
            'roles' => $roles,
        ];
    }

    return $map;
}

echo "=== FRONTEND ROUTE MAP CONTRACT TEST ===\n\n";

check(file_exists($navFile), 'navigation.js exists');
check(file_exists($indexFile), 'public index shell exists');

$navSource = file_get_contents($navFile);
$indexSource = file_get_contents($indexFile);
$navMap = extractNavMap($navSource);

check(count($navMap) >= 30, 'NavMap exposes expected module coverage');

foreach ($navMap as $moduleKey => $config) {
    check(in_array($moduleKey, $rbac['module_catalog'], true), "NavMap module is in RBAC catalog: {$moduleKey}");
    check(!empty($config['route']), "NavMap route configured: {$moduleKey}");
    if (!empty($config['route'])) {
        check(isset($routeRegistry[$config['route']]), "NavMap route exists in backend registry: {$config['route']}");
    }
}

foreach ($rbac['landing_routes'] as $moduleKey => $landingRoute) {
    check(isset($navMap[$moduleKey]), "Landing module exists in NavMap: {$moduleKey}");
    if (isset($navMap[$moduleKey])) {
        check($navMap[$moduleKey]['route'] === $landingRoute, "Landing route matches NavMap: {$moduleKey}");
    }
}

foreach (['green_belt.detail', 'task.detail'] as $detailModule) {
    check(($navMap[$detailModule]['hidden'] ?? false) === true, "Detail module hidden from sidebar: {$detailModule}");
}

$roleScopedModules = [
    'green_belt.supervisor_upload',
    'green_belt.outsourced_upload',
    'monitoring.upload',
    'green_belt.authority_view',
];
foreach ($roleScopedModules as $moduleKey) {
    check(!empty($navMap[$moduleKey]['roles']), "Role-scoped landing hidden from unrelated roles: {$moduleKey}");
}

$scriptOrder = [
    'js/core/api.js',
    'js/core/auth.js',
    'js/core/ui.js',
    'js/core/navigation.js',
    'js/views/modules.js',
    'js/app.js',
];
$lastPosition = -1;
foreach ($scriptOrder as $script) {
    $position = strpos($indexSource, $script);
    check($position !== false, "Index includes script: {$script}");
    check($position !== false && $position > $lastPosition, "Script order is safe for: {$script}");
    if ($position !== false) {
        $lastPosition = $position;
    }
}

echo "\n=== RESULTS: {$pass} PASSED, {$fail} FAILED ===\n";
exit($fail > 0 ? 1 : 0);

