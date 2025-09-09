<?php
require 'db.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get all active sessions
$sessions = $conn->query("
  SELECT s.id, sb.title, s.session_datetime
  FROM sessions s
  JOIN storybooks sb ON s.storybook_id = sb.id
  ORDER BY s.session_datetime DESC
")->fetch_all(MYSQLI_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $session_id = $_POST['session_id'];
    $question_index = $_POST['current_q_index'];
    $feedback = $_POST['feedback'];

    // Update student_view_state table
    $stmt = $conn->prepare("REPLACE INTO student_view_state (session_id, current_q_index, feedback) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $session_id, $question_index, $feedback);
    $stmt->execute();
    $stmt->close();

    echo "<div style='background:#d4edda;padding:10px;border:1px solid #155724;margin-bottom:10px;'>✅ State updated successfully!</div>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student View Tester – Dev Tool</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light p-4">
  <div class="container">
    <h2 class="mb-4">🧪 Student View Simulator</h2>

    <form method="POST" class="bg-white p-4 rounded shadow-sm border">
      <div class="mb-3">
        <label for="session_id" class="form-label">Select Session</label>
        <select class="form-select" id="session_id" name="session_id" required>
          <?php foreach ($sessions as $s): ?>
            <option value="<?= $s['id'] ?>">
              <?= htmlspecialchars($s['title']) ?> – <?= date("M d, Y H:i", strtotime($s['session_datetime'])) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="mb-3">
        <label for="current_q_index" class="form-label">Question Index (0–2)</label>
        <input type="number" class="form-control" id="current_q_index" name="current_q_index" min="0" max="2" value="0" required>
      </div>

      <div class="mb-3">
        <label for="feedback" class="form-label">Feedback</label>
        <select class="form-select" id="feedback" name="feedback">
          <option value="none">none (reset)</option>
          <option value="correct">correct</option>
          <option value="wrong">wrong</option>
        </select>
      </div>

      <button type="submit" class="btn btn-primary">🔄 Update Student View</button>
    </form>
  </div>
</body>
</html>
