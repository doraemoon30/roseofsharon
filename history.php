<?php
require 'db.php';
require 'auth_check.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Get filter inputs
$startDate = $_POST['start_date'] ?? '';
$endDate = $_POST['end_date'] ?? '';
$classType = $_POST['class_type'] ?? '';

// Build SQL with accurate logic
$filterSql = "
    SELECT 
        sess.id,
        sess.session_datetime,
        sess.class_type,
        s.title AS story_title,
        COUNT(ar.id) AS total_questions,
        ROUND(SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / COUNT(ar.id) * 100, 2) AS average_score

    FROM sessions sess
    JOIN storybooks s ON sess.storybook_id = s.id
    LEFT JOIN assessment_results ar ON sess.id = ar.session_id
    WHERE 1
";

$params = [];
if (!empty($startDate) && !empty($endDate)) {
    $filterSql .= " AND DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
}
if (!empty($classType)) {
    $filterSql .= " AND sess.class_type = ?";
    $params[] = $classType;
}

$filterSql .= " GROUP BY sess.id, s.title ORDER BY sess.session_datetime DESC";

$stmt = $conn->prepare($filterSql);
if ($params) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Session History Log</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
    .container { padding: 40px; }
    h3 { text-align: center; margin-bottom: 30px; color: #2c3e50; }
    .table thead { background-color: #343a40; color: white; }
    .btn-view { padding: 6px 12px; font-size: 14px; border-radius: 6px; }
    .filter-box {
      background: #fff;
      padding: 20px;
      border-radius: 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
      margin-bottom: 30px;
    }
  </style>
</head>
<body>
<div class="container">
  <h3>📚 Session History Logs</h3>

  <!-- Filter Form -->
  <div class="filter-box">
    <form method="POST" class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Start Date</label>
        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">End Date</label>
        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Session Type</label>
        <select name="class_type" class="form-select">
          <option value="">All Classes</option>
          <option value="Morning Class" <?= $classType === 'Morning Class' ? 'selected' : '' ?>>Morning Class</option>
          <option value="Afternoon Class" <?= $classType === 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
        </select>
      </div>
      <div class="col-12 text-end">
        <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
        <a href="history.php" class="btn btn-secondary">⟳ Reset</a>
      </div>
    </form>
  </div>

  <!-- Session Logs Table -->
  <table class="table table-bordered table-striped">
    <thead>
      <tr>
        <th>Date & Time</th>
        <th>Class</th>
        <th>Storybook</th>
        <th>Total Questions</th>
        <th>Average Accuracy</th>
        <th>Action</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($sessions) > 0): ?>
        <?php foreach ($sessions as $row): ?>
          <tr>
            <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
            <td><?= htmlspecialchars($row['class_type']) ?></td>
            <td><?= htmlspecialchars($row['story_title']) ?></td>
            <td><?= $row['total_questions'] ?></td>
            <td><?= is_null($row['average_score']) ? 'N/A' : $row['average_score'] . '%' ?></td>
            <td>
              <a href="session_details.php?session_id=<?= $row['id'] ?>" class="btn btn-sm btn-primary btn-view">🔍 View</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-center text-muted">No session logs found for selected filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <div class="text-center mt-4">
    <a href="dashboard.php" class="btn btn-outline-secondary">← Back to Dashboard</a>
  </div>
</div>
<?php include 'footer.php'; ?>
</body>
</html>
