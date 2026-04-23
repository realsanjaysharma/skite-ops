<?php
$rbac = require 'c:\xampp\htdocs\skite\config\rbac.php';
$registry = require 'C:\Users\radhi\Downloads\route_registry.php';
$catalog = $rbac['module_catalog'];

$missing = [];
$capabilities = [];

foreach ($registry as $route => $config) {
    if (isset($config['module_key']) && !in_array($config['module_key'], $catalog)) {
        $missing[] = "Route '$route' uses unknown module_key '{$config['module_key']}'";
    }
    if (isset($config['capability'])) {
        $capabilities[$route] = $config['capability'];
    }
}

echo "MISSING MODULE KEYS:\n";
print_r($missing);

echo "\nCAPABILITY MAPPINGS (Sample):\n";
print_r(array_slice($capabilities, 0, 10));
