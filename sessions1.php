<?php
require 'db.php';
session_start();

if (!isset($_SESSION['username']) || !isset($_SESSION['full_name'])) {
    header('Location: login.php');
    exit();
}

$dcw_name = $_SESSION['full_name'];
$photo = $_SESSION['photo'] ?? 'default.png';

// Get filters from URL
$classFilter = $_GET['class_type'] ?? '';
$dateFilter = $_GET['session_date'] ?? '';

// Build query (include filename)
$query = "SELECT s.*, sb.title, sb.filename FROM sessions s JOIN storybooks sb ON s.storybook_id = sb.id WHERE 1=1";
$params = [];
$types = "";

if (!empty($classFilter)) {
    $query .= " AND s.class_type = ?";
    $params[] = $classFilter;
    $types .= "s";
}

if (!empty($dateFilter)) {
    $query .= " AND DATE(s.session_datetime) = ?";
    $params[] = $dateFilter;
    $types .= "s";
}

$query .= " ORDER BY s.session_datetime DESC";

// Prepare the query
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

if (!$stmt->execute()) {
    die("Query execution failed: " . $stmt->error);
}

$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sessions – ROSE OF SHARON DAY CARE CENTER</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    html, body {
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', sans-serif;
      height: 100%;
    }

    .wrapper {
      display: flex;
      height: 100vh;
    }

    .main-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .content-area {
      flex-grow: 1;
      overflow-y: auto;
      background: #f8f9fa;
      padding: 30px;
    }

    footer {
      background-color: #2c3e50;
      color: white;
      text-align: center;
      padding: 15px 10px;
    }
  </style>
</head>
<body>
<div class="wrapper">
  <!-- Sidebar -->
  <div class="sidebar-wrapper">
    <?php include 'sidebar.php'; ?>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <?php include 'header.php'; ?>

    <div class="content-area">
      <h2 class="mb-4">📅 Session List</h2>

      <!-- Filter Form -->
      <form class="row g-3 mb-4" method="GET">
        <div class="col-md-4">
          <label class="form-label">Filter by Class</label>
          <select name="class_type" class="form-select">
            <option value="">All Classes</option>
            <option value="Morning Class" <?= $classFilter == 'Morning Class' ? 'selected' : '' ?>>Morning Class</option>
            <option value="Afternoon Class" <?= $classFilter == 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Filter by Date</label>
          <input type="date" name="session_date" value="<?= htmlspecialchars($dateFilter) ?>" class="form-control">
        </div>
        <div class="col-md-4 align-self-end">
          <button type="submit" class="btn btn-primary">Apply Filters</button>
          <a href="session_form.php" class="btn btn-success">+ New Session</a>
        </div>
      </form>

      <!-- Session Table -->
      <table class="table table-bordered bg-white">
        <thead class="table-dark">
          <tr>
            <th>Date & Time</th>
            <th>Class</th>
            <th>Storybook</th>
            <th>Notes</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
            <td><?= htmlspecialchars($row['class_type']) ?></td>
            <td>
              <a href="play_video.php?file=<?= urlencode(pathinfo($row['filename'], PATHINFO_FILENAME)) ?>" class="text-decoration-none">
                <?= htmlspecialchars($row['title']) ?>
              </a>
            </td>
            <td><?= nl2br(htmlspecialchars($row['notes'])) ?></td>
            <td>
              <a href="session_edit.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
              <a href="session_delete.php?id=<?= $row['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this session?')">Delete</a>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>

      <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-outline-primary">🔙 Back to Dashboard</a>
      </div>
    </div>

    <?php include 'footer.php'; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
