<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash('warning', 'Missing storybook ID.');
  header('Location: storybooks.php'); 
  exit;
}

// Check if the storybook exists and isn’t already archived
$chk = $conn->prepare("SELECT id FROM storybooks WHERE id = ? AND deleted_at IS NULL");
$chk->bind_param('i', $id);
$chk->execute();
$exists = $chk->get_result()->num_rows === 1;
$chk->close();

if (!$exists) {
  flash('warning', 'Storybook not found or already archived.');
  header('Location: storybooks.php');
  exit;
}

// Soft delete (move to archive)
$upd = $conn->prepare("UPDATE storybooks SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL");
$upd->bind_param('i', $id);
$upd->execute();

if ($upd->affected_rows > 0) {
  flash('success', 'Storybook moved to archive.');
} else {
  flash('danger', 'Could not move to archive. Please try again.');
}
$upd->close();

header('Location: storybooks.php');
exit;
