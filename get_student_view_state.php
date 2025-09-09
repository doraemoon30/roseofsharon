<?php
require 'db.php';
$session_id = $_GET['session_id'] ?? 0;

$stmt = $conn->prepare("SELECT current_q_index, feedback FROM student_view_state WHERE session_id = ?");
$stmt->bind_param("i", $session_id);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_assoc();

echo json_encode($data ?: ['current_q_index' => 0, 'feedback' => 'none']);
?>
