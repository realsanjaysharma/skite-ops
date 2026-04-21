<?php
require_once 'app/services/DashboardService.php';
$s = new DashboardService();
print_r($s->getManagementSummary());
