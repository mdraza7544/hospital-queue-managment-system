<?php
// Set consistent application timezone
date_default_timezone_set('Asia/Kolkata');

$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "queue_db";
$port = 3307;

// Attempt connection on port 3307 first (XAMPP MariaDB)
$conn = @new mysqli($servername, $username, $password, $dbname, $port);

// If 3307 is not available, fallback to default 3306
if ($conn->connect_error) {
    $conn = new mysqli($servername, $username, $password, $dbname, 3306);
}

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for better character support
$conn->set_charset("utf8mb4");

// Synchronize MySQL connection session timezone
$conn->query("SET time_zone = '+05:30'");
?>