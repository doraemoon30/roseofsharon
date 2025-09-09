<?php
require_once 'db.php';
require_once 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // get_current_year / set_current_year

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Ensure session + CSRF token
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
}

// Get current Academic Year
$AY = get_current_year($conn);

// Get filters from URL
$classFilter    = $_GET['class_type']   ?? '';
$sessionFilter  = $_GET['session_type'] ?? '';
$activeMonth    = $_GET['month']        ?? ''; // format: YYYY-MM

// ===== Build distinct month list (YYYY-MM) from existing sessions (DESC)
$monthsStmt = $conn->prepare("
    SELECT DISTINCT DATE_FORMAT(session_datetime, '%Y-%m') AS ym
    FROM sessions
    ORDER BY ym DESC
");
$monthsStmt->execute();
$monthsRes = $monthsStmt->get_result();
$monthList = [];
while ($mrow = $monthsRes->fetch_assoc()) { $monthList[] = $mrow['ym']; }
$monthsStmt->close();

// Default month: latest with data, else current month
if ($activeMonth === '') {
    $activeMonth = $monthList[0] ?? date('Y-m');
}

// Compute prev/next months
$prevMonth = $nextMonth = null;
if (!empty($monthList)) {
    $idx = array_search($activeMonth, $monthList, true);
    if ($idx !== false) {
        $prevMonth = $monthList[$idx + 1] ?? null; // list is DESC
        $nextMonth = $monthList[$idx - 1] ?? null;
    } else {
        // Snap to most recent if requested month not found
        $activeMonth = $monthList[0];
        $prevMonth   = $monthList[1] ?? null;
        $nextMonth   = null;
    }
}

// Compute month date range
list($yy, $mm) = explode('-', $activeMonth);
$monthStart = sprintf('%04d-%02d-01', (int)$yy, (int)$mm);
$monthEnd   = date('Y-m-t', strtotime($monthStart)); // last day of the month

// ===== Build session query (namespaced vars to avoid collisions)
$sessionsSql    = "SELECT s.*, sb.title, sb.filename
                   FROM sessions s
                   JOIN storybooks sb ON s.storybook_id = sb.id
                   WHERE 1=1";
$sessionsParams = [];
$sessionsTypes  = "";

// Filter: Class
if (!empty($classFilter)) {
    $sessionsSql     .= " AND s.class_type = ?";
    $sessionsParams[] = $classFilter;
    $sessionsTypes   .= "s";
}

// Filter: Month range (always applied)
$sessionsSql     .= " AND DATE(s.session_datetime) BETWEEN ? AND ?";
$sessionsParams[] = $monthStart;
$sessionsParams[] = $monthEnd;
$sessionsTypes   .= "ss";

// Filter: Type (assessment/recap)
if (!empty($sessionFilter)) {
    $sessionsSql     .= " AND LOWER(s.notes) = LOWER(?)";
    $sessionsParams[] = $sessionFilter;
    $sessionsTypes   .= "s";
}

$sessionsSql  .= " ORDER BY s.session_datetime DESC";
$sessionsStmt  = $conn->prepare($sessionsSql);
if (!$sessionsStmt) die("Query preparation failed: " . $conn->error);
if (!empty($sessionsParams)) $sessionsStmt->bind_param($sessionsTypes, ...$sessionsParams);
if (!$sessionsStmt->execute()) die("Query execution failed: " . $sessionsStmt->error);
$sessionsResult = $sessionsStmt->get_result();

// Helper to rebuild query strings preserving current filters
function linkWithMonth($targetMonth) {
    $params = $_GET;
    $params['month'] = $targetMonth;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>Session List</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: url('assets/images/storytime.png') repeat;
            background-size: 50% auto;
            color: white;
        }
        .container-custom {
            background: rgba(0,0,0,0.55);
            padding: 40px;
            border-radius: 15px;
            max-width: 1200px;
            margin: 40px auto;
        }
        h1.page-title {
            font-size: 2rem;
            font-weight: 800;
            text-transform: uppercase;
            color: white;
        }
        .form-label {
            color: white !important;
            font-weight: 500;
        }
        .filters .form-label { color: #fff !important; }
        table {
            background: rgba(255,255,255,0.9);
            color: black;
        }
        .table thead {
            background-color: #2c3e50;
            color: white;
        }
        .completed-row {
            background-color: #e0e0e0 !important;
            opacity: 0.75;
        }
        .filters .spacer { min-width: 3rem; } /* extra separation */
    </style>
</head>
<body>

<div class="main-content">

  <?php include 'header.php'; ?>

  <!-- FLASH: unified placement -->
  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container-custom">

      <!-- ✅ One-line top bar (left: title • center: AY • right: reset button) -->
      <div class="d-flex align-items-center gap-2 mb-3">
        <!-- Left: title -->
        <div class="flex-grow-0">
          <h1 class="page-title m-0">📋 SESSION LIST</h1>
        </div>

        <!-- Center: Academic Year badge -->
        <div class="flex-grow-1 d-flex justify-content-center">
          <span class="badge bg-primary fs-6 px-3 py-2">
            School Year: <?= htmlspecialchars($AY) ?>
          </span>
        </div>

        <!-- Right: Reset button (opens modal) -->
        <div class="flex-grow-0">
          <button class="btn btn-danger d-flex align-items-center"
                  data-bs-toggle="modal" data-bs-target="#yearResetModal">
            <i class="bi bi-arrow-repeat me-2"></i> Start New School Year
          </button>
        </div>
      </div>

      <!-- 🔽 Modal with the actual reset form -->
      <div class="modal fade" id="yearResetModal" tabindex="-1" aria-labelledby="yearResetLabel" aria-hidden="true">
        <div class="modal-dialog">
          <form class="modal-content" action="helper_year_reset.php" method="post"
                onsubmit="return confirm('Start new school year? This will snapshot usage (if checked), switch Academic Year, and reset assessment usage limits.');">
            <div class="modal-header">
              <h5 class="modal-title" id="yearResetLabel">Start New School Year</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label">Next Academic Year</label>
                <input type="text" name="next_year" class="form-control" placeholder="e.g., 2026-2027" required>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="do_snapshot" id="do_snapshot" checked>
                <label class="form-check-label" for="do_snapshot">
                  Snapshot year-end storybook usage
                </label>
              </div>
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
              <button class="btn btn-danger">
                <i class="bi bi-arrow-repeat me-1"></i> Start New School Year
              </button>
            </div>
          </form>
        </div>
      </div>
      <!-- 🔼 End modal -->

      <!-- One-line Filter Form (Class, Month, Type, Filter ... New Session at far right) -->
      <form class="row g-2 align-items-end mb-4 filters" method="GET">
          <div class="col-auto">
              <label class="form-label mb-0">Class</label>
              <select name="class_type" class="form-select">
                  <option value="">All</option>
                  <option value="Morning Class"   <?= $classFilter == 'Morning Class'   ? 'selected' : '' ?>>Morning Class</option>
                  <option value="Afternoon Class" <?= $classFilter == 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
              </select>
          </div>

          <div class="col-auto">
              <label class="form-label mb-0">Month</label>
              <input type="month" name="month" value="<?= htmlspecialchars($activeMonth) ?>" class="form-control">
          </div>

          <div class="col-auto">
              <label class="form-label mb-0">Type</label>
              <select name="session_type" class="form-select">
                  <option value="">All</option>
                  <option value="assessment" <?= $sessionFilter == 'assessment' ? 'selected' : '' ?>>Assessment</option>
                  <option value="recap"      <?= $sessionFilter == 'recap'      ? 'selected' : '' ?>>Recap</option>
              </select>
          </div>

          <div class="col-auto">
              <button type="submit" class="btn btn-primary px-4">
                  <i class="bi bi-funnel me-1"></i> Filter
              </button>
          </div>

          <!-- big spacer then New Session on the far right -->
          <div class="col d-none d-md-block"></div>
          <div class="col-auto spacer"></div>
          <div class="col-auto">
              <a href="session_form.php" class="btn btn-success px-5">
                  <i class="bi bi-plus-lg me-1"></i> New Session
              </a>
          </div>
      </form>

      <!-- Session Table -->
      <div class="table-responsive">
          <table class="table table-bordered table-striped align-middle">
              <thead>
                  <tr>
                      <th>Date & Time</th>
                      <th>Class</th>
                      <th>Storybook</th>
                      <th>Type</th>
                      <th>Actions</th>
                  </tr>
              </thead>
              <tbody>
              <?php if ($sessionsResult->num_rows === 0): ?>
                <tr><td colspan="5" class="text-center text-muted">No sessions found.</td></tr>
              <?php else: ?>
                <?php while ($row = $sessionsResult->fetch_assoc()):
                    $isRecap  = strtolower($row['notes']) === 'recap';
                    $isLocked = (!$isRecap && (int)$row['assessed'] === 1); // lock only for completed assessments
                ?>
                    <tr class="<?= $isLocked ? 'completed-row' : '' ?>">
                        <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
                        <td><?= htmlspecialchars($row['class_type']) ?></td>
                        <td>
                            <a href="play_video.php?file=<?= urlencode(pathinfo($row['filename'], PATHINFO_FILENAME)) ?>" class="text-decoration-none">
                                <?= htmlspecialchars($row['title']) ?>
                            </a>
                        </td>
                        <td><?= ucfirst(htmlspecialchars($row['notes'])) ?></td>
                        <td class="d-flex gap-2 flex-wrap">
                            <a href="session_edit.php?id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-warning">Edit</a>

                            <?php if ($isLocked): ?>
                                <span class="badge bg-secondary">🔒 Assessment Completed</span>
                            <?php else: ?>
                                <a href="session_video_start.php?session_id=<?= (int)$row['id'] ?>"
                                   class="btn btn-sm <?= $isRecap ? 'btn-info' : 'btn-success' ?>">
                                    <?= $isRecap ? '🔄 Launch Recap' : '🚀 Launch Session' ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
              <?php endif; ?>
              </tbody>
          </table>
      </div>

      <!-- Prev/Next Month Pagination BELOW the table -->
      <div class="d-flex justify-content-between align-items-center mt-3">
        <a class="btn btn-outline-light <?= $prevMonth ? '' : 'disabled' ?>"
           href="<?= $prevMonth ? htmlspecialchars(linkWithMonth($prevMonth)) : '#' ?>">‹ Prev</a>

        <div class="text-white fw-semibold">
          <?= htmlspecialchars(date('F Y', strtotime($monthStart))) ?>
        </div>

        <a class="btn btn-outline-light <?= $nextMonth ? '' : 'disabled' ?>"
           href="<?= $nextMonth ? htmlspecialchars(linkWithMonth($nextMonth)) : '#' ?>">Next ›</a>
      </div>

      <div class="text-center mt-4">
          <a href="dashboard.php" class="btn btn-outline-light">🔙 Back to Dashboard</a>
      </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
