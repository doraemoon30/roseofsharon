<?php
require 'db.php';
require 'auth_check.php';
require 'flash.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  flash('danger', '⚠️ Missing storybook ID.');
  header('Location: storybooks_trashbin.php');
  exit;
}

// Fetch filename and ensure item is actually in Trash
$stmt = $conn->prepare("SELECT filename FROM storybooks WHERE id = ? AND deleted_at IS NOT NULL");
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
  flash('warning', 'Item not found in Trash or already deleted.');
  header('Location: storybooks_trashbin.php');
  exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$videoPath = __DIR__ . '/assets/storybooks/' . $row['filename'];

// Delete from database (permanent)
$del = $conn->prepare("DELETE FROM storybooks WHERE id = ? AND deleted_at IS NOT NULL");
$del->bind_param("i", $id);
$del->execute();

if ($del->affected_rows > 0) {
  // Best-effort: remove file if it exists
  if (is_file($videoPath)) {
    @unlink($videoPath);
  }
  flash('success', '🧹 Storybook permanently deleted.');
} else {
  flash('danger', '❌ Failed to delete storybook permanently.');
}
$del->close();

header('Location: storybooks_trashbin.php');
exit;
