<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

$session_id = $_POST['session_id'] ?? null;
$from_recap = isset($_POST['recap']); // Just for logging/debug if needed

if (!$session_id) {
    http_response_code(400);
    echo "Missing session_id.";
    exit;
}

// Step 1: Get the storybook_id from the session
$stmt = $conn->prepare("SELECT storybook_id FROM sessions WHERE id = ?");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

if (!$row) {
    http_response_code(404);
    echo "Session not found.";
    exit;
}

$storybook_id = $row['storybook_id'];

// Step 2: Update the used_count (+1)
$update = $conn->prepare("UPDATE storybooks SET used_count = IFNULL(used_count, 0) + 1 WHERE id = ?");
$update->bind_param("i", $storybook_id);
$success = $update->execute();
$update->close();

if ($success) {
    echo "Used count updated.";
} else {
    http_response_code(500);
    echo "Failed to update used count.";
}
?>
