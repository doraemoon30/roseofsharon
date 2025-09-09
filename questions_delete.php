<?php
require 'db.php';
require 'auth_check.php';

if (!isset($_GET['id'])) {
    header("Location: questions.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("DELETE FROM questions WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: questions.php?deleted=1");
    exit();
} else {
    echo "❌ Error deleting question.";
}
?>
