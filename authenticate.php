<?php
session_start();
require 'db.php';
require 'flash.php'; // for toasts

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    flash('warning', 'Please enter username and password.');
    header('Location: login.php');
    exit();
}

// Query only the columns that exist in your users table
$stmt = $conn->prepare("
    SELECT user_id, username, full_name, password, email, profile_photo
    FROM users
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    flash('danger', 'Database error: ' . $conn->error);
    header('Location: login.php');
    exit();
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    flash('danger', 'User not found.');
    header('Location: login.php');
    exit();
}

$user = $result->fetch_assoc();

// Plaintext password check (for now)
if ($password !== $user['password']) {
    flash('danger', 'Incorrect password.');
    header('Location: login.php');
    exit();
}

// Success: set session variables
$_SESSION['user_id']       = (int)$user['user_id'];
$_SESSION['username']      = $user['username'];
$_SESSION['full_name']     = $user['full_name'];
$_SESSION['email']         = $user['email'];
$_SESSION['profile_photo'] = $user['profile_photo'];
$_SESSION['last_activity'] = time(); // for inactivity timeout

flash('success', 'Welcome back, '.$user['full_name'].'!');
header('Location: dashboard.php');
exit();
