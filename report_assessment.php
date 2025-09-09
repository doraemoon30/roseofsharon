<?php
require 'db.php';
require 'auth_check.php';
require_once 'flash.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Filters
$startDate   = $_GET['start_date'] ?? '';
$endDate     = $_GET['end_date'] ?? '';
$classType   = $_GET['class_type'] ?? '';
$sessionType = $_GET['session_type'] ?? '';
$page        = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit       = 10;
$offset      = ($page - 1) * $limit;

// 📊 Handle AJAX Chart Data Request
if (isset($_GET['chart_data'])) {
    $validRanges = ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'];
    $range = in_array($_GET['range'] ?? 'daily', $validRanges) ? $_GET['range'] : 'daily';

    switch ($range) {
        case 'weekly':
            $group = "YEARWEEK(sess.session_datetime, 1)";
            $labelField = "CONCAT('Week ', WEEK(sess.session_datetime, 1), ' - ', YEAR(sess.session_datetime))";
            break;
        case 'monthly':
            $group = "DATE_FORMAT(sess.session_datetime, '%Y-%m')";
            $labelField = $group;
            break;
        case 'quarterly':
            $group = "CONCAT(YEAR(sess.session_datetime), '-Q', QUARTER(sess.session_datetime))";
            $labelField = $group;
            break;
        case 'yearly':
            $group = "DATE_FORMAT(sess.session_datetime, '%Y')";
            $labelField = $group;
            break;
        case 'daily':
        default:
            $group = "DATE(sess.session_datetime)";
            $labelField = $group;
            break;
    }

    $filterConditions = ["LOWER(sess.notes) != 'recap'"];
    if (!empty($startDate) && !empty($endDate)) {
        $filterConditions[] = "DATE(sess.session_datetime) BETWEEN '$startDate' AND '$endDate'";
    }
    if (!empty($classType)) {
        $filterConditions[] = "sess.class_type = '$classType'";
    }
    if (!empty($sessionType)) {
        $filterConditions[] = "LOWER(sess.notes) = LOWER('$sessionType')";
    }
    $filterSql = 'WHERE ' . implode(' AND ', $filterConditions);

    $sql = "
        SELECT 
          $labelField AS label,
          ROUND(SUM(CASE WHEN sess.class_type = 'Morning Class' AND ar.match_score = 1 THEN 1 ELSE 0 END) / 
                NULLIF(COUNT(CASE WHEN sess.class_type = 'Morning Class' THEN ar.id END), 0) * 100, 2) AS morning_accuracy,
          ROUND(SUM(CASE WHEN sess.class_type = 'Afternoon Class' AND ar.match_score = 1 THEN 1 ELSE 0 END) / 
                NULLIF(COUNT(CASE WHEN sess.class_type = 'Afternoon Class' THEN ar.id END), 0) * 100, 2) AS afternoon_accuracy
        FROM assessment_results ar
        JOIN sessions sess ON ar.session_id = sess.id
        $filterSql
        GROUP BY $group
        ORDER BY MIN(sess.session_datetime)
    ";

    $data = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    echo json_encode($data);
    exit;
}

// Filters for main table query
$baseWhere = [];
$params    = [];
$types     = '';

if (!empty($startDate) && !empty($endDate)) {
    $baseWhere[] = "DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
}
if (!empty($classType)) {
    $baseWhere[] = "sess.class_type = ?";
    $params[] = $classType;
    $types .= 's';
}
if (!empty($sessionType)) {
    $baseWhere[] = "LOWER(sess.notes) = LOWER(?)";
    $params[] = $sessionType;
    $types .= 's';
}
$whereSQL = $baseWhere ? 'WHERE ' . implode(' AND ', $baseWhere) : '';

// 📂 CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=assessment_summary_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Class', 'Type', 'Storybook', 'Percentage (%)']);

    $csvSql = "
        SELECT sess.session_datetime, sess.class_type, sess.notes AS session_type, s.title AS story_title,
               CASE 
                 WHEN LOWER(sess.notes) = 'recap' THEN NULL
                 ELSE ROUND(SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / COUNT(ar.id) * 100, 2)
               END AS percentage
        FROM sessions sess
        JOIN storybooks s ON sess.storybook_id = s.id
        LEFT JOIN assessment_results ar ON sess.id = ar.session_id
        $whereSQL
        GROUP BY sess.id
        ORDER BY sess.session_datetime DESC
    ";
    $csvStmt = $conn->prepare($csvSql);
    if (!empty($params)) $csvStmt->bind_param($types, ...$params);
    $csvStmt->execute();
    $csvResult = $csvStmt->get_result();
    while ($row = $csvResult->fetch_assoc()) {
        fputcsv($output, [
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

// Count total
$countSql = "SELECT COUNT(DISTINCT sess.id) AS total
             FROM sessions sess
             LEFT JOIN assessment_results ar ON sess.id = ar.session_id
             $whereSQL";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalSessions = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages    = ceil($totalSessions / $limit);

// Fetch data
$sql = "
    SELECT sess.id, sess.session_datetime, sess.class_type, sess.notes AS session_type, s.title AS story_title,
           CASE 
             WHEN LOWER(sess.notes) = 'recap' THEN NULL
             ELSE ROUND(SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / COUNT(ar.id) * 100, 2)
           END AS percentage
    FROM sessions sess
    JOIN storybooks s ON sess.storybook_id = s.id
    LEFT JOIN assessment_results ar ON sess.id = ar.session_id
    $whereSQL
    GROUP BY sess.id
    ORDER BY sess.session_datetime DESC
    LIMIT ? OFFSET ?
";
$params[] = $limit;
$params[] = $offset;
$types   .= 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Assessment Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: url('assets/images/storytime.png') repeat;
        background-size: 50% auto;
        color: white;
    }
    .container {
        padding: 40px;
        max-width: 1400px;
        margin: auto;
        background: rgba(0,0,0,0.55);
        border-radius: 12px;
    }
    h2, .form-label {
        color: white !important;
    }
    .table-container {
        background: rgba(255,255,255,0.85);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }
    table.table {
        color: black;
    }
    .pagination {
        justify-content: center;
    }
</style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">📊 Assessment Report & Session History</h2>
    <?php display_flash_message(); ?>
    
    <!-- Filters -->
    <form method="GET" class="row g-3 mb-4">
        <input type="hidden" name="page" value="1">
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label">Class</label>
            <select name="class_type" class="form-select">
                <option value="">All</option>
                <option value="Morning Class" <?= $classType == 'Morning Class' ? 'selected' : '' ?>>Morning Class</option>
                <option value="Afternoon Class" <?= $classType == 'Afternoon Class' ? 'selected' : '' ?>>Afternoon Class</option>
            </select>
        </div>
        <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="session_type" class="form-select">
                <option value="">All</option>
                <option value="assessment" <?= strtolower($sessionType) == 'assessment' ? 'selected' : '' ?>>Assessment</option>
                <option value="recap" <?= strtolower($sessionType) == 'recap' ? 'selected' : '' ?>>Recap</option>
            </select>
        </div>
        <div class="col-md-2 text-end align-self-end">
            <button type="submit" class="btn btn-primary w-100 mb-2">🔍 Filter</button>
            <a href="report.php" class="btn btn-secondary w-100">⟳ Reset</a>
        </div>
    </form>

    <!-- Bar Graph -->
    <div class="table-container mb-4">
        <h5 class="text-white">📈 Class Accuracy Overview (Bar Chart)</h5>
        <canvas id="accuracyChart" height="100"></canvas>
    </div>

    <!-- Session Table -->
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
            <?php foreach ($sessions as $row): ?>
                <tr>
                    <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
                    <td><?= htmlspecialchars($row['class_type']) ?></td>
                    <td><?= ucfirst($row['session_type'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($row['story_title']) ?></td>
                    <td><?= is_null($row['percentage']) ? 'N/A' : $row['percentage'] ?></td>
                    <td><a href="session_details.php?session_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Pagination -->
        <nav>
            <ul class="pagination">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page-1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&class_type=<?= urlencode($classType) ?>&session_type=<?= urlencode($sessionType) ?>">« Prev</a>
                    </li>
                <?php endif; ?>
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&class_type=<?= urlencode($classType) ?>&session_type=<?= urlencode($sessionType) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page+1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&class_type=<?= urlencode($classType) ?>&session_type=<?= urlencode($sessionType) ?>">Next »</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- CSV & PDF Download -->
        <div class="text-center mt-3">
            <a href="?export=csv&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&class_type=<?= urlencode($classType) ?>&session_type=<?= urlencode($sessionType) ?>" class="btn btn-success">⬇ Download CSV</a>
            <a href="export_pdf.php?start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&class_type=<?= urlencode($classType) ?>&session_type=<?= urlencode($sessionType) ?>" class="btn btn-danger ms-2">📄 Download PDF</a>
        </div>
    </div>

    <div class="text-center mt-4">
        <a href="dashboard.php" class="btn btn-outline-light">← Back to Dashboard</a>
    </div>
</div>

<!-- Chart rendering -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    fetchChartData('<?= $startDate ?>', '<?= $endDate ?>', '<?= $classType ?>', '<?= $sessionType ?>');

    function fetchChartData(startDate, endDate, classType, sessionType) {
        const params = new URLSearchParams({
            chart_data: 1,
            range: 'monthly',
            start_date: startDate,
            end_date: endDate,
            class_type: classType,
            session_type: sessionType
        });

        fetch('report.php?' + params.toString())
            .then(res => res.json())
            .then(data => {
                const labels = data.map(row => row.label);
                const morning = data.map(row => parseFloat(row.morning_accuracy ?? 0));
                const afternoon = data.map(row => parseFloat(row.afternoon_accuracy ?? 0));

                new Chart(document.getElementById('accuracyChart'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [
                            {
                                label: 'Morning Class',
                                data: morning,
                                backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            },
                            {
                                label: 'Afternoon Class',
                                data: afternoon,
                                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true,
                                max: 100,
                                title: {
                                    display: true,
                                    text: 'Accuracy (%)'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Session Period'
                                }
                            }
                        }
                    }
                });
            });
    }
});
</script>
</body>
</html>
