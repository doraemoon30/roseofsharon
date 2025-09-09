<?php
$host = '127.0.0.1';          // Localhost IP
$user = 'root';               // Default MySQL user in XAMPP
$password = '';               // Blank password (default in XAMPP)
$dbname = 'roseofsharon';     // Your database name

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
