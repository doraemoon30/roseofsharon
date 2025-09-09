<?php
require 'db.php';
require 'auth_check.php';
require_once 'header.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['session_id'])) {
    echo "Session ID not provided.";
    exit;
}

$session_id = intval($_GET['session_id']);

// Fetch session info including notes (session type)
$sessionQuery = $conn->query("
    SELECT sess.session_datetime, sess.class_type, sess.notes, s.title AS story_title
    FROM sessions sess
    JOIN storybooks s ON sess.storybook_id = s.id
    WHERE sess.id = $session_id
");

if (!$sessionQuery) {
    die("Session query failed: " . $conn->error);
}

$sessionInfo = $sessionQuery->fetch_assoc();
if (!$sessionInfo) {
    echo "Session not found.";
    exit;
}

// Fetch question-answer records
$qaSql = "
    SELECT q.question_text, ar.recognized_answer, ar.match_score
    FROM assessment_results ar
    JOIN questions q ON ar.question_id = q.id
    WHERE ar.session_id = $session_id
";
$qaQuery = $conn->query($qaSql);
if (!$qaQuery) {
    die("Assessment query failed: " . $conn->error);
}

$qaResults = $qaQuery->fetch_all(MYSQLI_ASSOC);
$totalQuestions = count($qaResults);
$correctAnswers = 0;

foreach ($qaResults as $qa) {
    if ($qa['match_score'] == 1) {
        $correctAnswers++;
    }
}

// Percentage (0–100)
$percentage = $totalQuestions ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
$isRecap = (strtolower(trim($sessionInfo['notes'])) === 'recap');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Session Details</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: url('assets/images/storytime.png') repeat;
        background-size: 50% auto;
        color: white;
    }
    .container {
        max-width: 1100px;
        padding: 50px;
        background: rgba(0,0,0,0.55);
        border-radius: 15px;
    }
    h3 {
        color: white;
        font-size: 2rem;
        font-weight: 800;
        text-transform: uppercase;
    }
    table {
        background: rgba(255,255,255,0.9);
        color: black;
        font-size: 1rem;
    }
    .table-dark th {
        color: white !important;
    }
    .details-label {
        font-size: 1.05rem;
    }
    .progress {
        height: 20px;
        background: rgba(255,255,255,0.35);
    }
  </style>
</head>
<body>

<div class="container mt-5">
  <h3 class="mb-4 text-center">📄 Session Details</h3>
  <div class="mb-4">
    <p class="details-label"><strong>Date:</strong> <?= date("F j, Y g:i A", strtotime($sessionInfo['session_datetime'])) ?></p>
    <p class="details-label"><strong>Class:</strong> <?= htmlspecialchars($sessionInfo['class_type']) ?></p>
    <p class="details-label"><strong>Storybook:</strong> <?= htmlspecialchars($sessionInfo['story_title']) ?></p>
    <p class="details-label"><strong>Session Type:</strong> <?= ucfirst(htmlspecialchars($sessionInfo['notes'] ?: 'Assessment')) ?></p>
    <?php if (!$isRecap): ?>
        <p class="details-label"><strong>Total Questions:</strong> <?= $totalQuestions ?></p>
        <p class="details-label">
          <strong>Percentage:</strong>
          <span class="badge bg-info text-dark"><?= $percentage ?>%</span>
        </p>
        <!-- Optional: visual bar for quick glance -->
        <div class="progress mb-3">
          <div class="progress-bar bg-success" role="progressbar" style="width: <?= (float)$percentage ?>%;" aria-valuenow="<?= (float)$percentage ?>" aria-valuemin="0" aria-valuemax="100">
            <?= $percentage ?>%
          </div>
        </div>
        <p class="details-label text-muted">
          (<?= $correctAnswers ?> out of <?= $totalQuestions ?> correct)
        </p>
    <?php endif; ?>
  </div>

  <?php if ($isRecap): ?>
    <div class="alert alert-info text-center">
      📌 This was a <strong>recap session</strong>. No answers were recorded.
    </div>
  <?php elseif ($totalQuestions > 0): ?>
    <table class="table table-bordered">
      <thead class="table-dark text-center">
        <tr>
          <th>Question</th>
          <th>Student Answer</th>
          <th>Result</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($qaResults as $qa): ?>
          <tr>
            <td><?= htmlspecialchars($qa['question_text']) ?></td>
            <td><?= htmlspecialchars($qa['recognized_answer']) ?></td>
            <td class="text-center">
              <?php if ($qa['match_score'] == 1): ?>
                <span class="badge bg-success">RIGHT</span>
              <?php else: ?>
                <span class="badge bg-danger">WRONG</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="alert alert-warning text-center">
      ⚠ No recorded answers for this session.
    </div>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a href="report.php" class="btn btn-outline-light btn-lg">← Back to Report</a>
  </div>
</div>
</body>
</html>
