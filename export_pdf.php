<?php
require 'db.php';
require 'auth_check.php';
require 'dompdf/autoload.inc.php';
require_once 'helpers.php'; // ✅ get_current_year()

use Dompdf\Dompdf;

ini_set('display_errors', 1);
error_reporting(E_ALL);

// -------- Inputs (aligned with report.php) --------
$ayParam     = trim($_GET['academic_year'] ?? '');
$currentAY   = get_current_year($conn);
$selectedAY  = ($ayParam === '') ? $currentAY : (strtoupper($ayParam) === 'ALL' ? 'ALL' : $ayParam);

$activeMonth = $_GET['month']        ?? '';   // YYYY-MM
$startDate   = $_GET['start_date']   ?? '';
$endDate     = $_GET['end_date']     ?? '';
$classType   = $_GET['class_type']   ?? '';
$typeFilter  = $_GET['session_type'] ?? '';

$preparedBy  = $_SESSION['full_name'] ?? '';

// --- Helper: map percentage to remark, tolerant to rounding ---
function getEquivalent($percentage) {
    if ($percentage === 'N/A' || $percentage === null || $percentage === '') return 'N/A';
    $p = (float)$percentage;
    $snap = function($val) use ($p) { return abs($p - $val) < 0.01; };
    if ($snap(100.00)) return 'Excellent';
    if ($snap(66.67))  return 'Very Satisfactory';
    if ($snap(33.33))  return 'Needs Improvement';
    if ($snap(0.00))   return 'Unsatisfactory';
    if     ($p >= 90) return 'Excellent';
    elseif ($p >= 60) return 'Very Satisfactory';
    elseif ($p >= 30) return 'Needs Improvement';
    else              return 'Unsatisfactory';
}

// -------- WHERE builder (matches report.php) --------
$where  = [];
$params = [];
$types  = '';

if ($selectedAY !== 'ALL') {
    $where[]  = "sess.academic_year = ?";
    $params[] = $selectedAY;
    $types   .= 's';
}

if ($activeMonth && preg_match('/^\d{4}-\d{2}$/', $activeMonth)) {
    [$yy,$mm] = explode('-', $activeMonth);
    $monthStart = sprintf('%04d-%02d-01', (int)$yy, (int)$mm);
    $monthEnd   = date('Y-m-t', strtotime($monthStart));
    $where[]  = "DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $monthStart; $params[] = $monthEnd;
    $types   .= 'ss';
} elseif (!empty($startDate) && !empty($endDate)) {
    $where[]  = "DATE(sess.session_datetime) BETWEEN ? AND ?";
    $params[] = $startDate; $params[] = $endDate;
    $types   .= 'ss';
} elseif (!empty($startDate)) {
    $where[]  = "DATE(sess.session_datetime) >= ?";
    $params[] = $startDate; $types .= 's';
} elseif (!empty($endDate)) {
    $where[]  = "DATE(sess.session_datetime) <= ?";
    $params[] = $endDate; $types .= 's';
}

if (!empty($classType)) {
    $where[]  = "sess.class_type = ?";
    $params[] = $classType; $types .= 's';
}

if (!empty($typeFilter)) {
    $where[]  = "LOWER(sess.notes) = LOWER(?)";
    $params[] = $typeFilter; $types .= 's';
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// -------- Query (group by session) --------
$sql = "
  SELECT
    sess.session_datetime,
    sess.class_type,
    sess.academic_year,
    s.title AS story_title,
    sess.notes AS type,
    CASE
      WHEN LOWER(sess.notes) = 'recap' THEN NULL
      ELSE ROUND(
        SUM(CASE WHEN ar.match_score = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(ar.id),0) * 100, 2
      )
    END AS percentage
  FROM sessions sess
  JOIN storybooks s ON sess.storybook_id = s.id
  LEFT JOIN assessment_results ar ON sess.id = ar.session_id
  $whereSql
  GROUP BY sess.id
  ORDER BY sess.session_datetime DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// -------- Labels for header --------
if ($activeMonth && isset($monthStart, $monthEnd)) {
    $rangeLabel = date('F Y', strtotime($monthStart));
} elseif (!empty($startDate) || !empty($endDate)) {
    $rangeLabel = ($startDate ? date("F j, Y", strtotime($startDate)) : "—")
                . " - "
                . ($endDate   ? date("F j, Y", strtotime($endDate))   : "—");
} else {
    $rangeLabel = "All Dates";
}
$ayLabel = ($selectedAY === 'ALL') ? 'All Years' : $selectedAY;

// -------- Build PDF HTML --------
ob_start();
?>
<style>
  body { font-family: Arial, sans-serif; }
  .header { text-align: center; }
  .school-name { font-size: 20px; font-weight: bold; margin-top: 5px; }
  .school-info { font-size: 14px; margin-bottom: 20px; }
  h2 { text-align: center; text-transform: uppercase; margin-top: 10px; }
  .report-info { text-align: center; font-size: 14px; margin-bottom: 20px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
  th, td { border: 1px solid black; padding: 8px; font-size: 12px; }
  th { background-color: #f2f2f2; }
  .signature-section { width: 100%; margin-top: 50px; }
  .signature { width: 45%; display: inline-block; text-align: center; vertical-align: top; }
  .signature-line { margin-top: 50px; border-top: 1px solid black; width: 80%; margin-left: auto; margin-right: auto; }
  .name-under-line { margin-top: 5px; font-weight: bold; }
</style>

<div class="header">
  <div class="school-name">ROSE OF SHARON CHILD DEVELOPMENT CENTER</div>
  <div class="school-info">Barangay Crossing Mendez West, Tagaytay City</div>
</div>

<h2><strong>Assessment Report</strong></h2>
<p style="text-align:center;font-size:16px;margin-top:4px;">
  <strong>S.Y. <?= htmlspecialchars($ayLabel) ?></strong>
</p>

<div class="report-info">
  Date Range: <?= htmlspecialchars($rangeLabel) ?><br>
  Class: <?= htmlspecialchars(!empty($classType) ? $classType : "All Classes") ?><br>
  Type: <?= htmlspecialchars(!empty($typeFilter) ? ucfirst($typeFilter) : "All Types") ?>
</div>

<table>
  <thead>
    <tr>
      <th>Date</th>
      <th>Class</th>
      <th>Storybook</th>
      <th>Type</th>
      <th>Percentage (%)</th>
      <th>Remarks</th>
    </tr>
  </thead>
  <tbody>
  <?php if (!empty($sessions)): ?>
    <?php foreach ($sessions as $row):
      $typeLabel  = ucfirst($row['type'] ?: 'Assessment');
      $isRecap    = strtolower((string)$row['type']) === 'recap';
      $percentRaw = $isRecap ? null : ($row['percentage'] ?? null);
      $percentOut = is_null($percentRaw) ? 'N/A' : number_format((float)$percentRaw, 2);
      $remark     = getEquivalent($percentOut);
    ?>
      <tr>
        <td><?= date("F j, Y g:i A", strtotime($row['session_datetime'])) ?></td>
        <td><?= htmlspecialchars($row['class_type']) ?></td>
        <td><?= htmlspecialchars($row['story_title']) ?></td>
        <td><?= htmlspecialchars($typeLabel) ?></td>
        <td><?= htmlspecialchars($percentOut) ?></td>
        <td><?= htmlspecialchars($remark) ?></td>
      </tr>
    <?php endforeach; ?>
  <?php else: ?>
      <tr><td colspan="6" style="text-align:center;">No records found</td></tr>
  <?php endif; ?>
  </tbody>
</table>

<div class="signature-section">
  <div class="signature">
    Prepared By:
    <div class="signature-line"></div>
    <div class="name-under-line"><?= htmlspecialchars($preparedBy) ?></div>
  </div>
  <div class="signature" style="float:right;">
    Approved By:
    <div class="signature-line"></div>
  </div>
</div>
<?php
$html = ob_get_clean();

// -------- Generate PDF --------
$scope =
  ($activeMonth ? $activeMonth :
   (!empty($startDate) || !empty($endDate)
      ? (($startDate ?: '...') . '_to_' . ($endDate ?: '...'))
      : 'all'));

$fname = "assessment_report_"
       . ($selectedAY === 'ALL' ? 'ALL_' : ($selectedAY . '_'))
       . $scope . ".pdf";

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();
$dompdf->stream($fname, ["Attachment" => true]);
exit;
