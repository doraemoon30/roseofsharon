<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

// ✅ Validate inputs
if (
    empty($_POST['id']) ||
    empty($_POST['storybook_id']) ||
    empty($_POST['session_datetime']) ||
    empty($_POST['class_type']) ||
    empty($_POST['notes'])
) {
    set_flash_message('danger', '❌ Missing required fields.');
    header("Location: sessions.php");
    exit();
}

$id               = intval($_POST['id']);
$storybook_id     = intval($_POST['storybook_id']);
$session_datetime = $_POST['session_datetime'];
$class_type       = $_POST['class_type'];
$notes            = strtolower(trim($_POST['notes'])); // assessment or recap

// ✅ Validate session type
if (!in_array($notes, ['assessment', 'recap'])) {
    set_flash_message('danger', '❌ Invalid session type.');
    header("Location: sessions.php");
    exit();
}

// ✅ If assessment mode → make sure this storybook is not already used for this class type
if ($notes === 'assessment') {
    $check = $conn->prepare("
        SELECT COUNT(*) AS cnt 
        FROM sessions 
        WHERE storybook_id = ? 
        AND class_type = ? 
        AND LOWER(notes) = 'assessment'
        AND id != ? -- exclude this current session
    ");
    $check->bind_param("isi", $storybook_id, $class_type, $id);
    $check->execute();
    $result = $check->get_result()->fetch_assoc();
    $check->close();

    if ($result['cnt'] > 0) {
        set_flash_message('danger', '❌ This storybook is already used in assessment mode for this class.');
        header("Location: sessions.php");
        exit();
    }
}

// ✅ Update session
$stmt = $conn->prepare("
    UPDATE sessions 
    SET storybook_id = ?, session_datetime = ?, class_type = ?, notes = ? 
    WHERE id = ?
");
$stmt->bind_param("isssi", $storybook_id, $session_datetime, $class_type, $notes, $id);

if ($stmt->execute()) {
    set_flash_message('success', '✅ Session updated successfully.');
} else {
    set_flash_message('danger', '❌ Failed to update session: ' . $stmt->error);
}

header("Location: sessions.php");
exit();
?>
