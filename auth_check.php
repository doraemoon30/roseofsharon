<?php
// Always include this file BEFORE any HTML output
if (!isset($_SESSION)) session_start();
require_once 'flash.php';

// Require login
if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
  flash('warning', 'Login required to continue.');
  header('Location: login.php');
  exit();
}

// Inactivity timeout (5 minutes)
$timeoutSeconds = 300; // 5 * 60
$now = time();

if (isset($_SESSION['last_activity']) && ($now - $_SESSION['last_activity']) > $timeoutSeconds) {
  // End old session and start a fresh one just to flash the message
  session_unset();
  session_destroy();
  session_start();
  flash('warning', 'Session expired due to inactivity. Please log in again.');
  header('Location: login.php');
  exit();
}

// Refresh activity timestamp
$_SESSION['last_activity'] = $now;
