<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash('danger', '⚠️ Missing storybook ID.');
  header('Location: storybooks_trashbin.php');
  exit;
}

// Check if the storybook is soft-deleted
$stmt = $conn->prepare("SELECT id FROM storybooks WHERE id = ? AND deleted_at IS NOT NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows === 1;
$stmt->close();

if (!$exists) {
  flash('warning', 'Storybook not found or already restored.');
  header('Location: storybooks_trashbin.php');
  exit;
}

// Restore the storybook
$restore = $conn->prepare("UPDATE storybooks SET deleted_at = NULL WHERE id = ? AND deleted_at IS NOT NULL");
$restore->bind_param("i", $id);
$restore->execute();

if ($restore->affected_rows > 0) {
  flash('success', '♻️ Storybook restored successfully!');
} else {
  flash('danger', 'Restore failed. Please try again.');
}
$restore->close();

header('Location: storybooks_trashbin.php');
exit;
