<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

// Ensure we have an ID (GET for load, POST for save)
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
  flash('warning','Missing question ID.');
  header('Location: questions.php'); exit();
}

// Load current question
$qstmt = $conn->prepare("SELECT id, storybook_id, question_text, correct_keywords, language, question_set FROM questions WHERE id = ? LIMIT 1");
$qstmt->bind_param('i', $id);
$qstmt->execute();
$question = $qstmt->get_result()->fetch_assoc();
$qstmt->close();

if (!$question) {
  flash('danger','Question not found.');
  header('Location: questions.php'); exit();
}

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $storybook_id     = (int)($_POST['storybook_id'] ?? 0);
  $question_text    = trim($_POST['question_text'] ?? '');
  $correct_keywords = trim($_POST['correct_keywords'] ?? '');
  $language         = trim($_POST['language'] ?? 'en');
  $question_set     = strtolower(trim($_POST['question_set'] ?? 'assessment'));

  // Basic validation
  if ($storybook_id <= 0) {
    flash('warning','Please choose a storybook.'); header('Location: questions_edit.php?id='.$id); exit();
  }
  if ($question_text === '') {
    flash('warning','Question text is required.'); header('Location: questions_edit.php?id='.$id); exit();
  }
  if ($correct_keywords === '') {
    flash('warning','Please provide at least one correct keyword.'); header('Location: questions_edit.php?id='.$id); exit();
  }
  if (!in_array($question_set, ['assessment','recap'], true)) {
    flash('warning','Invalid question set.'); header('Location: questions_edit.php?id='.$id); exit();
  }
  if (!in_array($language, ['en','fil'], true)) {
    $language = 'en'; // default
  }

  // Ensure chosen storybook exists and is active (not archived)
  $bstmt = $conn->prepare("SELECT id FROM storybooks WHERE id = ? AND deleted_at IS NULL");
  $bstmt->bind_param('i', $storybook_id);
  $bstmt->execute();
  $activeBook = $bstmt->get_result()->num_rows === 1;
  $bstmt->close();
  if (!$activeBook) {
    flash('danger','Selected storybook not found or archived.');
    header('Location: questions_edit.php?id='.$id); exit();
  }

  // Enforce max 3 per set per storybook (exclude this question)
  $cstmt = $conn->prepare("SELECT COUNT(*) AS c FROM questions WHERE storybook_id = ? AND question_set = ? AND id <> ?");
  $cstmt->bind_param('isi', $storybook_id, $question_set, $id);
  $cstmt->execute();
  $c = (int)$cstmt->get_result()->fetch_assoc()['c'];
  $cstmt->close();
  if ($c >= 3) {
    flash('warning','You already have 3 questions in '.$question_set.' for this storybook.');
    header('Location: questions_edit.php?id='.$id); exit();
  }

  // Normalize keywords (lowercase, trimmed, no empties)
  $keywords   = array_filter(array_map(fn($s)=>trim(mb_strtolower($s)), explode(',', $correct_keywords)));
  $normalized = implode(',', $keywords);

  // Update
  $ustmt = $conn->prepare("UPDATE questions SET storybook_id = ?, question_text = ?, correct_keywords = ?, language = ?, question_set = ? WHERE id = ?");
  $ustmt->bind_param('issssi', $storybook_id, $question_text, $normalized, $language, $question_set, $id);
  if ($ustmt->execute()) {
    flash('success','✅ Question updated successfully!');
    header('Location: questions.php?storybook_id='.$storybook_id); exit();
  } else {
    flash('danger','Update failed. Please try again.');
    header('Location: questions_edit.php?id='.$id); exit();
  }
}

// Load active storybooks for dropdown
$storybooks = [];
$sres = $conn->query("SELECT id, title FROM storybooks WHERE deleted_at IS NULL ORDER BY title ASC");
if ($sres) $storybooks = $sres->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Question – ROSE OF SHARON</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.css">
  <style>
    .content-wrap { max-width: 900px; margin: 24px auto; }
  </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
  <?php include 'header.php'; ?>

  <!-- FLASH: unified placement -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container content-wrap">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white">
        <strong>✏️ Edit Question</strong>
      </div>
      <div class="card-body">
        <form method="POST">
          <input type="hidden" name="id" value="<?= (int)$question['id'] ?>">

          <div class="mb-3">
            <label class="form-label">Storybook</label>
            <select name="storybook_id" class="form-select" required>
              <?php foreach ($storybooks as $book): ?>
                <option value="<?= (int)$book['id'] ?>" <?= ((int)$book['id'] === (int)$question['storybook_id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($book['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label">Question</label>
            <input type="text" name="question_text" class="form-control"
                   value="<?= htmlspecialchars($question['question_text']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label">Correct Keywords (comma-separated)</label>
            <input type="text" name="correct_keywords" class="form-control"
                   value="<?= htmlspecialchars($question['correct_keywords']) ?>" required>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Language</label>
              <select name="language" class="form-select" required>
                <option value="en"  <?= ($question['language'] === 'en')  ? 'selected' : '' ?>>English</option>
                <option value="fil" <?= ($question['language'] === 'fil') ? 'selected' : '' ?>>Tagalog</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Question Set</label>
              <select name="question_set" class="form-select" required>
                <option value="assessment" <?= ($question['question_set'] === 'assessment') ? 'selected' : '' ?>>Assessment</option>
                <option value="recap"      <?= ($question['question_set'] === 'recap')      ? 'selected' : '' ?>>Recap</option>
              </select>
            </div>
          </div>

          <div class="d-flex justify-content-between mt-4">
            <a href="questions.php?storybook_id=<?= (int)$question['storybook_id'] ?>" class="btn btn-outline-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">💾 Update Question</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
