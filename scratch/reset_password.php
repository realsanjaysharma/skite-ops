<?php
require 'config/database.php';
$conn = Database::getConnection();
$password = password_hash('admin123', PASSWORD_DEFAULT);
$conn->prepare('UPDATE users SET password_hash = ? WHERE email = ?')->execute([$password, 'ops.manager@skite.local']);
echo "Password reset to admin123\n";
