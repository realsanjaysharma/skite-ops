<?php
$_SERVER['REQUEST_URI'] = '/api?route=task/list';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_X_ROLE_KEY'] = 'OPS_MANAGER';
$_SERVER['HTTP_X_USER_ID'] = '1';
$_GET['route'] = 'task/list';

require_once 'index.php';
