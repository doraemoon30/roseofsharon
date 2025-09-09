<?php
require 'db.php'; // assumes you have db.php with the connection

$username = 'CDWglaiza';
$password = '123456a';
$full_name = 'GLAIZA CARURUCAN';

// Hash the password for security
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Prepare and execute the insert
$stmt = $conn->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $hashed_password, $full_name);

if ($stmt->execute()) {
    echo "User inserted successfully.";
} else {
    echo "Error inserting user: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
