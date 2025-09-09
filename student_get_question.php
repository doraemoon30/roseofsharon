<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Inputs
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$q_index    = isset($_GET['q'])          ? (int)$_GET['q']          : 0;

if ($session_id <= 0) {
  http_response_code(400);
  echo json_encode(['error' => 'Invalid session_id.']);
  exit;
}
if ($q_index < 0) $q_index = 0;

// Pull session info
$storybook_id = 0;
$notes = null;

$infoStmt = $conn->prepare("SELECT storybook_id, notes FROM sessions WHERE id = ? LIMIT 1");
$infoStmt->bind_param("i", $session_id);
$infoStmt->execute();
$infoStmt->bind_result($storybook_id, $notes);
$ok = $infoStmt->fetch();
$infoStmt->close();

if (!$ok || !$storybook_id) {
  http_response_code(404);
  echo json_encode(['error' => 'Session not found.']);
  exit;
}

// Normalize set type from session.notes
$type = 'assessment';
if (is_string($notes)) {
  $t = strtolower(trim($notes));
  if ($t === 'recap') $type = 'recap';
}

// Count questions for this storybook & set
$total = 0;
$cnt = $conn->prepare("SELECT COUNT(*) FROM questions WHERE storybook_id = ? AND question_set = ?");
$cnt->bind_param('is', $storybook_id, $type);
$cnt->execute();
$cnt->bind_result($total);
$cnt->fetch();
$cnt->close();

// If beyond last index, signal done
if ($q_index >= (int)$total) {
  echo json_encode([
    'question_text' => '',
    'index' => (int)$q_index,
    'total' => (int)$total,
    'done'  => true
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

// Fetch one question by index (ordered by id ASC)
$question_text = '';
$stmt = $conn->prepare("
  SELECT question_text
  FROM questions
  WHERE storybook_id = ? AND question_set = ?
  ORDER BY id ASC
  LIMIT 1 OFFSET ?
");
$stmt->bind_param("isi", $storybook_id, $type, $q_index);
$stmt->execute();
$stmt->bind_result($question_text);
$stmt->fetch();
$stmt->close();

echo json_encode([
  'question_text' => $question_text ?? '',
  'index' => (int)$q_index,
  'total' => (int)$total,
  'done'  => false
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
