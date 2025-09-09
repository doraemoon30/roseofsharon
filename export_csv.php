<?php
require 'db.php';
require_once 'helpers.php'; // ✅ get_current_year()
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ---- Inputs (mirror report.php) ----
$ayParam     = trim($_GET['academic_year'] ?? '');
$currentAY   = get_current_year($conn);
$selectedAY  = ($ayParam === '') ? $currentAY : (strtoupper($ayParam) === 'ALL' ? 'ALL' : $ayParam);

$activeMonth = $_GET['month']        ?? ''; // YYYY-MM
$startDate   = $_GET['start_date']   ?? '';
$endDate     = $_GET['end_date']     ?? '';
$classType   = $_GET['class_type']   ?? '';
$sessionType = $_GET['session_type'] ?? '';

// ---- Build WHERE like report.php ----
$where  = [];
$params = [];
$types  = '';

if ($selectedAY !== 'ALL') {
  $where[]  = "s.academic_year = ?";
  $params[] = $selectedAY;
  $types   .= 's';
}

// Date scope: month (YYYY-MM) OR explicit range
if ($activeMonth && preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
  [$yy,$mm] = explode('-', $activeMonth);
  $monthStart = sprintf('%04d-%02d-01', (int)$yy, (int)$mm);
  $monthEnd   = date('Y-m-t', strtotime($monthStart));
  $where[]  = "DATE(s.session_datetime) BETWEEN ? AND ?";
  $params[] = $monthStart;
  $params[] = $monthEnd;
  $types   .= 'ss';
} elseif (!empty($startDate) && !empty($endDate)) {
  $where[]  = "DATE(s.session_datetime) BETWEEN ? AND ?";
  $params[] = $startDate;
  $params[] = $endDate;
  $types   .= 'ss';
} elseif (!empty($startDate)) {
  $where[]  = "DATE(s.session_datetime) >= ?";
  $params[] = $startDate;
  $types   .= 's';
} elseif (!empty($endDate)) {
  $where[]  = "DATE(s.session_datetime) <= ?";
  $params[] = $endDate;
  $types   .= 's';
}

if (!empty($classType)) {
  $where[]  = "s.class_type = ?";
  $params[] = $classType;
  $types   .= 's';
}

if (!empty($sessionType)) {
  $where[]  = "LOWER(TRIM(s.notes)) = ?";
  $params[] = strtolower(trim($sessionType));
  $types   .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ---- Filename (AY + scope) ----
$scope =
  ($activeMonth ? $activeMonth :
   (!empty($startDate) || !empty($endDate)
      ? (($startDate ?: '...') . '_to_' . ($endDate ?: '...'))
      : 'all'));

$fname = 'assessment_summary_'
       . ($selectedAY === 'ALL' ? 'ALL_' : ($selectedAY . '_'))
       . $scope . '.csv';

// ---- Headers + UTF-8 BOM ----
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename=' . $fname);
echo "\xEF\xBB\xBF";

// ---- Query (mirrors report.php grouping) ----
$sql = "
  SELECT 
    s.id,
    s.academic_year,
    s.session_datetime,
    s.class_type,
    sb.title AS story_title,
    s.notes  AS session_type,
    CASE
      WHEN LOWER(TRIM(s.notes)) = 'recap' THEN NULL
      ELSE ROUND(
        SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) 
        / NULLIF(COUNT(ar.id),0) * 100, 2
      )
    END AS percentage
  FROM sessions s
  JOIN storybooks sb       ON s.storybook_id = sb.id
  LEFT JOIN assessment_results ar ON s.id = ar.session_id
  $whereSql
  GROUP BY s.id
  ORDER BY s.session_datetime DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// ---- CSV header (includes AY) ----
$out = fopen('php://output', 'w');
fputcsv($out, ['Academic Year','Date','Class','Storybook','Type','Percentage (%)']);

// ---- Rows ----
while ($row = $res->fetch_assoc()) {
  fputcsv($out, [
    $row['academic_year'] ?: '',
    date("F j, Y g:i A", strtotime($row['session_datetime'])),
    $row['class_type'],
    $row['story_title'],
    ucfirst($row['session_type'] ?: 'Assessment'),
    is_null($row['percentage']) ? 'N/A' : $row['percentage']
  ]);
}

fclose($out);
exit;
