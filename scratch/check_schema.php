<?php
require 'config/database.php';
$conn = Database::getConnection();
$stmt = $conn->query('DESCRIBE users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
