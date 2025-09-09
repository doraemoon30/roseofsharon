<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';
require_once 'helpers.php'; // ✅ get_current_year()

ini_set('display_errors', 1);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/**
 * Inputs
 */
$classType     = $_GET['class_type']    ?? '';
$sessionType   = $_GET['session_type']  ?? '';
$activeMonth   = $_GET['month']         ?? '';          // YYYY-MM or ''
$startDateIn   = $_GET['start_date']    ?? '';          // YYYY-MM-DD or ''
$endDateIn     = $_GET['end_date']      ?? '';          // YYYY-MM-DD or ''
$ayParam       = trim($_GET['academic_year'] ?? '');    // '' | 'ALL' | '2025-2026' etc.
$currentAY     = get_current_year($conn);

$page   = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit  = 10;
$offset = ($page - 1) * $limit;

/**
 * AY options for dropdown
 */
$ayOptions = [];
$ayStmt = $conn->prepare("
  SELECT DISTINCT academic_year
  FROM sessions
  WHERE academic_year IS NOT NULL AND academic_year <> ''
  ORDER BY academic_year DESC
");
$ayStmt->execute();
$ayRes = $ayStmt->get_result();
while ($r = $ayRes->fetch_assoc()) { $ayOptions[] = $r['academic_year']; }
$ayStmt->close();

// Default AY: if none provided, use current AY; allow 'ALL' to see across years
if ($ayParam === '') {
  $selectedAY = $currentAY;
} else {
  $selectedAY = strtoupper($ayParam) === 'ALL' ? 'ALL' : $ayParam;
}

/**
 * Month list (DESC) for prev/next controls (only used in month mode)
 */
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

/**
 * Decide filtering mode
 */
$mode = 'ALL';
$rangeStart = $rangeEnd = null;
$monthStart = $monthEnd = null;

$hasRange = (!empty($startDateIn) && !empty($endDateIn));
if ($hasRange) {
    $mode = 'RANGE';
    $rangeStart = $startDateIn;
    $rangeEnd   = $endDateIn;
} elseif ($activeMonth !== '' && preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
    $mode = 'MONTH';
    [$yy, $mm] = explode('-', $activeMonth);
    $monthStart = sprintf('%04d-%02d-01', (int)$yy, (int)$mm);
    $monthEnd   = date('Y-m-t', strtotime($monthStart));
} else {
    $activeMonth = ''; // normalize
    $mode = 'ALL';
}

/**
 * Prev/Next month (only in MONTH mode)
 */
$prevMonth = $nextMonth = null;
if ($mode === 'MONTH' && !empty($monthList)) {
    $idx = array_search($activeMonth, $monthList, true); // DESC
    if ($idx !== false) {
        $prevMonth = $monthList[$idx + 1] ?? null; // older
        $nextMonth = $monthList[$idx - 1] ?? null; // newer
    }
}

/**
 * WHERE (shared across count/table/export)
 */
$baseWhere = [];
$params    = [];
$types     = '';

if ($selectedAY !== 'ALL') {
    $baseWhere[] = "sess.academic_year = ?";
    $params[]    = $selectedAY;
    $types      .= 's';
}

if ($mode === 'RANGE') {
    $baseWhere[] = "DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $rangeStart; $params[] = $rangeEnd;
    $types   .= 'ss';
} elseif ($mode === 'MONTH') {
    $baseWhere[] = "DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $monthStart; $params[] = $monthEnd;
    $types   .= 'ss';
}

if (!empty($classType)) {
    $baseWhere[] = "sess.class_type = ?";
    $params[] = $classType;
    $types   .= 's';
}
if (!empty($sessionType)) {
    $baseWhere[] = "LOWER(sess.notes) = LOWER(?)";
    $params[] = $sessionType;
    $types   .= 's';
}
$whereSQL = $baseWhere ? ('WHERE ' . implode(' AND ', $baseWhere)) : '';

/**
 * CSV Export
 */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    $fnamePart =
        ($selectedAY !== 'ALL' ? $selectedAY . '_' : '') .
        ($mode === 'RANGE' ? ($rangeStart . '_to_' . $rangeEnd)
          : ($mode === 'MONTH' ? $activeMonth : 'all'));
    header('Content-Disposition: attachment; filename=assessment_summary_' . $fnamePart . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Academic Year','Date','Class','Type','Storybook','Percentage (%)']);

    $csvSql = "
      SELECT sess.academic_year,
             sess.session_datetime,
             sess.class_type,
             sess.notes AS session_type,
             s.title    AS story_title,
             CASE
               WHEN LOWER(sess.notes) = 'recap' THEN NULL
               ELSE ROUND(
                 SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(ar.id),0) * 100, 2
               )
             END AS percentage
      FROM sessions sess
      JOIN storybooks s ON sess.storybook_id = s.id
      LEFT JOIN assessment_results ar ON sess.id = ar.session_id
      $whereSQL
      GROUP BY sess.id
      ORDER BY sess.session_datetime DESC
    ";
    $csvStmt = $conn->prepare($csvSql);
    if (!empty($params)) { $csvStmt->bind_param($types, ...$params); }
    $csvStmt->execute();
    $csvResult = $csvStmt->get_result();

    while ($row = $csvResult->fetch_assoc()) {
        fputcsv($output, [
            $row['academic_year'] ?: '',
            date("F j, Y g:i A", strtotime($row['session_datetime'])),
            $row['class_type'],
            ucfirst($row['session_type'] ?: 'N/A'),
            $row['story_title'],
            is_null($row['percentage']) ? 'N/A' : $row['percentage']
        ]);
    }
    fclose($output);
    exit;
}

/**
 * Count total rows (distinct sessions)
 */
$countSql = "SELECT COUNT(DISTINCT sess.id) AS total
             FROM sessions sess
             LEFT JOIN assessment_results ar ON sess.id = ar.session_id
             $whereSQL";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$totalSessions = (int)$countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = max(1, (int)ceil($totalSessions / $limit));

/**
 * Table data
 */
$sql = "
  SELECT sess.id,
         sess.academic_year,
         sess.session_datetime,
         sess.class_type,
         sess.notes AS session_type,
         s.title    AS story_title,
         CASE
           WHEN LOWER(sess.notes) = 'recap' THEN NULL
           ELSE ROUND(
             SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(ar.id),0) * 100, 2
           )
         END AS percentage
  FROM sessions sess
  JOIN storybooks s ON sess.storybook_id = s.id
  LEFT JOIN assessment_results ar ON sess.id = ar.session_id
  $whereSQL
  GROUP BY sess.id
  ORDER BY sess.session_datetime DESC
  LIMIT ? OFFSET ?
";
$paramsTbl = $params;
$typesTbl  = $types . 'ii';
$paramsTbl[] = $limit;
$paramsTbl[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($typesTbl, ...$paramsTbl);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

/**
 * Helper to rebuild links preserving current filters
 */
function linkWithMonth($targetMonth) {
    $params = $_GET;
    $params['month'] = $targetMonth;
    // when clicking month pager, clear range fields to keep pure month mode
    unset($params['start_date'], $params['end_date'], $params['page']);
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assessment Report & Session History</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body {
    font-family: 'Segoe UI', sans-serif;
    background: url('assets/images/storytime.png') repeat;
    background-size: 50% auto;
    color: white;
  }
  .container-wrap {
    padding: 40px;
    max-width: 1400px;
    margin: auto;
    background: rgba(0,0,0,0.55);
    border-radius: 12px;
  }
  h2, .form-label { color: white !important; }
  .table-container {
    background: rgba(255,255,255,0.85);
    border-radius: 12px; padding: 20px; margin-bottom: 30px;
  }
  table.table { color: black; }
  .pagination { justify-content: center; }
  .filters .spacer { min-width: 3rem; }
  .ay-badge { background:#0d6efd; }
</style>
</head>
<body>

<div class="main-content">
<?php include 'header.php'; ?>

  <?php if (function_exists('render_flashes')) { render_flashes(); } ?>

  <div class="container-wrap py-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="mb-0">📊 Assessment Report & Session History</h2>
      <span class="badge ay-badge px-3 py-2">
        School Year:
        <strong><?= htmlspecialchars($selectedAY === 'ALL' ? 'All Years' : $selectedAY) ?></strong>
      </span>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 align-items-end mb-4 filters" id="filtersForm">
      <input type="hidden" name="page" value="1">

      <div class="col-auto">
        <label class="form-label mb-0">School Year</label>
        <select name="academic_year" class="form-select">
          <option value="ALL" <?= ($selectedAY === 'ALL') ? 'selected' : '' ?>>All Years</option>
          <?php foreach ($ayOptions as $ay): ?>
            <option value="<?= htmlspecialchars($ay) ?>" <?= ($selectedAY === $ay) ? 'selected' : '' ?>>
              <?= htmlspecialchars($ay) ?><?= ($ay === $currentAY ? ' (Current)' : '') ?>
            </option>
          <?php endforeach; ?>
          <?php if (!in_array($currentAY, $ayOptions ?? [], true)): ?>
            <option value="<?= htmlspecialchars($currentAY) ?>" <?= ($selectedAY === $currentAY) ? 'selected' : '' ?>>
              <?= htmlspecialchars($currentAY) ?> (Current)
            </option>
          <?php endif; ?>
        </select>
      </div>

      <div class="col-auto">
        <label class="form-label mb-0">Class</label>
        <select name="class_type" class="form-select">
          <option value="">All</option>
          <option value="Morning Class"   <?= $classType == 'Morning Class'   ? 'selected' : '' ?>>Morning Class</option>
          <option value="Afternoon Class" <?= $classType == 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
        </select>
      </div>

      <div class="col-auto">
        <label class="form-label mb-0">Month</label>
        <input type="month" name="month" value="<?= htmlspecialchars($activeMonth) ?>" class="form-control">
      </div>

      <div class="col-auto">
        <label class="form-label mb-0">Start Date</label>
        <input type="date" name="start_date" value="<?= htmlspecialchars($startDateIn) ?>" class="form-control" placeholder="YYYY-MM-DD">
      </div>

      <div class="col-auto">
        <label class="form-label mb-0">End Date</label>
        <input type="date" name="end_date" value="<?= htmlspecialchars($endDateIn) ?>" class="form-control" placeholder="YYYY-MM-DD">
      </div>

      <div class="col-auto">
        <label class="form-label mb-0">Type</label>
        <select name="session_type" class="form-select">
          <option value="">All</option>
          <option value="assessment" <?= strtolower($sessionType) == 'assessment' ? 'selected' : '' ?>>Assessment</option>
          <option value="recap"      <?= strtolower($sessionType) == 'recap'      ? 'selected' : '' ?>>Recap</option>
        </select>
      </div>

      <div class="col-auto d-flex gap-2">
        <button type="submit" class="btn btn-primary px-4">Filter</button>
        <a href="report.php" class="btn btn-secondary px-4">Reset</a>
      </div>
    </form>

    <!-- Range/month note -->
    <div class="mb-2 text-muted">
      Showing:
      <strong>
        <?php
          if ($mode === 'RANGE') {
            echo htmlspecialchars(date('M d, Y', strtotime($rangeStart)) . ' – ' . date('M d, Y', strtotime($rangeEnd)));
          } elseif ($mode === 'MONTH') {
            echo htmlspecialchars(date('F Y', strtotime($monthStart)));
          } else {
            echo 'All Dates';
          }
        ?>
      </strong>
      • Year:
      <strong><?= htmlspecialchars($selectedAY === 'ALL' ? 'All Years' : $selectedAY) ?></strong>
    </div>

    <!-- Table -->
    <div class="table-container">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>Date</th>
            <th>Class</th>
            <th>Type</th>
            <th>Story</th>
            <th>Percentage (%)</th>
            <th>Full Details</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($sessions)): ?>
            <tr><td colspan="6" class="text-center text-muted">No sessions found.</td></tr>
          <?php else: ?>
            <?php foreach ($sessions as $row): ?>
  <tr>
    <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
    <td><?= htmlspecialchars($row['class_type']) ?></td>
    <td><?= ucfirst($row['session_type'] ?: 'N/A') ?></td>
    <td><?= htmlspecialchars($row['story_title']) ?></td>
    <td><?= is_null($row['percentage']) ? 'N/A' : $row['percentage'] ?></td>
    <td>
      <?php if (strtolower($row['session_type']) === 'recap'): ?>
        N/A
      <?php else: ?>
        <a href="session_details.php?session_id=<?= (int)$row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
      <?php endif; ?>
    </td>
  </tr>
<?php endforeach; ?>

          <?php endif; ?>
        </tbody>
      </table>

      <!-- Pagination -->
      <nav>
        <ul class="pagination justify-content-center">
          <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query([
                    'page' => $page-1,
                    'academic_year' => $selectedAY,
                    'month' => $activeMonth,
                    'start_date' => $startDateIn,
                    'end_date' => $endDateIn,
                    'class_type' => $classType,
                    'session_type' => $sessionType
              ]) ?>">« Prev</a>
            </li>
          <?php endif; ?>

          <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= ($i === $page) ? 'active' : '' ?>">
              <a class="page-link" href="?<?= http_build_query([
                    'page' => $i,
                    'academic_year' => $selectedAY,
                    'month' => $activeMonth,
                    'start_date' => $startDateIn,
                    'end_date' => $endDateIn,
                    'class_type' => $classType,
                    'session_type' => $sessionType
              ]) ?>"><?= $i ?></a>
            </li>
          <?php endfor; ?>

          <?php if ($page < $totalPages): ?>
            <li class="page-item">
              <a class="page-link" href="?<?= http_build_query([
                    'page' => $page+1,
                    'academic_year' => $selectedAY,
                    'month' => $activeMonth,
                    'start_date' => $startDateIn,
                    'end_date' => $endDateIn,
                    'class_type' => $classType,
                    'session_type' => $sessionType
              ]) ?>">Next »</a>
            </li>
          <?php endif; ?>
        </ul>
      </nav>

      <!-- Prev/Month/Next — ONLY in MONTH mode -->
      <?php if ($mode === 'MONTH'): ?>
      <div class="d-flex justify-content-between align-items-center mt-3">
        <a class="btn btn-light <?= $prevMonth ? '' : 'disabled' ?>"
           href="<?= $prevMonth ? htmlspecialchars(linkWithMonth($prevMonth)) : '#' ?>">‹ Prev</a>

        <div class="text-white fw-semibold">
          <?= htmlspecialchars(date('F Y', strtotime($monthStart))) ?>
        </div>

        <a class="btn btn-light <?= $nextMonth ? '' : 'disabled' ?>"
           href="<?= $nextMonth ? htmlspecialchars(linkWithMonth($nextMonth)) : '#' ?>">Next ›</a>
      </div>
      <?php endif; ?>

      <!-- CSV & PDF -->
      <div class="text-center mt-4">
        <a href="?export=csv&<?= http_build_query([
              'academic_year' => $selectedAY,
              'month'        => $activeMonth,
              'start_date'   => $startDateIn,
              'end_date'     => $endDateIn,
              'class_type'   => $classType,
              'session_type' => $sessionType,
          ]) ?>" class="btn btn-success">
          ⬇ Download CSV
        </a>
        <a href="export_pdf.php?<?= http_build_query([
              'academic_year' => $selectedAY,
              'month'        => $activeMonth,
              'start_date'   => $startDateIn,
              'end_date'     => $endDateIn,
              'class_type'   => $classType,
              'session_type' => $sessionType,
          ]) ?>" class="btn btn-danger ms-2">
          📄 Download PDF
        </a>
      </div>
    </div>

    <div class="text-center mt-4">
      <a href="dashboard.php" class="btn btn-outline-light">← Back to Dashboard</a>
    </div>
  </div>

  <?php include 'footer.php'; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
