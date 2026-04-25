<?php
require 'config/database.php';
$conn = Database::getConnection();
$conn->prepare('UPDATE users SET failed_attempt_count = 0, last_failed_attempt_at = NULL, is_active = 1 WHERE email = ?')->execute(['ops.manager@skite.local']);
echo "User reset successful\n";
