<?php
require 'db.php';
require 'auth_check.php';

$storybooks = $conn->query("SELECT id, title FROM storybooks ORDER BY title ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Question – ROSE OF SHARON</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: url('assets/images/storytime.png') repeat;
      background-size: 50% auto;
      font-family: 'Segoe UI', sans-serif;
      color: white;
    }
    .container-custom {
      background: rgba(0,0,0,0.55);
      padding: 40px;
      border-radius: 15px;
      max-width: 800px;
      margin: 40px auto;
    }
    h4 {
      font-size: 1.8rem;
      font-weight: 700;
      color: white;
    }
    label {
      font-weight: 500;
      color: black;
    }
    .form-section {
      background: rgba(255,255,255,0.95);
      padding: 25px;
      border-radius: 10px;
    }
  </style>
</head>
<body>

<div class="container-custom">
  <h4 class="mb-4">➕ Add New Question</h4>

  <form method="POST" action="questions_add_save.php" class="form-section">
    <div class="mb-3">
      <label class="form-label">Storybook</label>
      <select name="storybook_id" class="form-select" required>
        <?php foreach ($storybooks as $book): ?>
          <option value="<?= $book['id'] ?>"><?= htmlspecialchars($book['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Question</label>
      <input type="text" name="question_text" class="form-control" placeholder="Enter question" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Correct Keywords (comma-separated)</label>
      <input type="text" name="correct_keywords" class="form-control" placeholder="e.g., dog, bark, pet" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Language</label>
      <select name="language" class="form-select" required>
        <option value="en">English</option>
        <option value="fil">Tagalog</option>
      </select>
    </div>

    <div class="mb-3">
      <label class="form-label">Question Set</label>
      <select name="question_set" class="form-select" required>
        <option value="assessment">Assessment</option>
        <option value="recap">Recap</option>
      </select>
    </div>

    <div class="d-flex justify-content-between mt-4">
      <button type="submit" class="btn btn-success">💾 Save Question</button>
      <a href="questions.php" class="btn btn-outline-primary">← Back to Questions</a>
    </div>
  </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
