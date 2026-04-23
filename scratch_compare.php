<?php
$old = require 'c:\xampp\htdocs\skite\config\route_registry.php';
$new = require 'C:\Users\radhi\Downloads\route_registry.php';

$newly_protected = [];
foreach ($new as $route => $config) {
    if (isset($config['module_key'])) {
        if (!isset($old[$route]['module_key'])) {
            $newly_protected[] = $route;
        }
    }
}
echo "Newly Protected Routes:\n";
print_r($newly_protected);
