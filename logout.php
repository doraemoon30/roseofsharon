<?php
declare(strict_types=1);

session_start();

// Was this triggered by idle timeout?
$timeout = isset($_GET['timeout']) ? 1 : 0;

// Clear session data
$_SESSION = [];

// Delete the session cookie (if any)
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

// Destroy the session
session_destroy();

// Redirect with context so login.php can show the right notice
header('Location: ' . ($timeout ? 'login.php?timeout=1' : 'login.php?logged_out=1'));
exit;
