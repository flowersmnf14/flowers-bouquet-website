<?php
// Database configuration for XAMPP
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'admin_website';

// Create connection
$conn = new mysqli($host, $user, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
