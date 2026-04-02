<?php
$host = 'localhost';
$db   = 'tutoring_management_db';
$user = 'root';
$pass = ''; 

try {
    // Using PDO for secure prepared statements
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die("Database connection failed.");
}
?>