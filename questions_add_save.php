<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  flash('warning','Invalid request.');
  header('Location: questions.php'); exit();
}

$storybook_id     = (int)($_POST['storybook_id'] ?? 0);
$question_text    = trim($_POST['question_text'] ?? '');
$correct_keywords = trim($_POST['correct_keywords'] ?? '');
$language         = trim($_POST['language'] ?? 'en');
$question_set     = strtolower(trim($_POST['question_set'] ?? 'assessment')); // 'assessment'|'recap'

// Basic validation
if ($storybook_id <= 0) {
  flash('warning','Please choose a storybook.');
  header('Location: questions.php'); exit();
}
if ($question_text === '') {
  flash('warning','Question text is required.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
}
if ($correct_keywords === '') {
  flash('warning','Please provide at least one correct keyword.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
}
if (!in_array($question_set, ['assessment','recap'], true)) {
  flash('warning','Invalid question set.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
}
if (!in_array($language, ['en','fil'], true)) {
  $language = 'en'; // default
}

// Ensure storybook exists and is not archived
$chk = $conn->prepare("SELECT id FROM storybooks WHERE id = ? AND deleted_at IS NULL");
$chk->bind_param('i', $storybook_id);
$chk->execute();
$exists = $chk->get_result()->num_rows === 1;
$chk->close();
if (!$exists) {
  flash('danger','Selected storybook not found or archived.');
  header('Location: questions.php'); exit();
}

// Enforce max 3 per set per storybook
$cnt = $conn->prepare("SELECT COUNT(*) AS c FROM questions WHERE storybook_id = ? AND question_set = ?");
$cnt->bind_param('is', $storybook_id, $question_set);
$cnt->execute();
$row = $cnt->get_result()->fetch_assoc();
$cnt->close();
if ((int)$row['c'] >= 3) {
  flash('warning','You already have 3 questions in '.$question_set.' for this storybook.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
}

// Normalize keywords: lowercase, trimmed, no empties
$keywords = array_filter(array_map(fn($s)=>trim(mb_strtolower($s)), explode(',', $correct_keywords)));
$normalized_keywords = implode(',', $keywords);

// Insert
$ins = $conn->prepare("INSERT INTO questions (storybook_id, question_text, correct_keywords, language, question_set) VALUES (?, ?, ?, ?, ?)");
$ins->bind_param('issss', $storybook_id, $question_text, $normalized_keywords, $language, $question_set);

if ($ins->execute()) {
  flash('success','Question added to '.$question_set.'.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
} else {
  flash('danger','Failed to add question. Please try again.');
  header('Location: questions.php?storybook_id='.$storybook_id); exit();
}
