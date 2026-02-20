<?php

$host = 'localhost';
$dbname = 'freelance_marketplace';
$username = 'root';
$password = '';

try {
    $pdo = new PDO( "mysql:host=$host;dbname=$dbname;port=3307;charset=utf8mb4",  $username, $password );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
 
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

?>
